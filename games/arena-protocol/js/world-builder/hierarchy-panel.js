/**
 * HierarchyPanel — scene tree view with parent/child relationships.
 * Displayed as a floating panel (not inside the left builder panel).
 *
 * Features:
 *   - Tree nodes for all world objects
 *   - Click to select in 3D scene
 *   - Drag-and-drop to reparent
 *   - Context menu: rename, group, ungroup
 *   - Group command: wrap selected objects under a new empty Group
 */
import * as THREE from 'three';
import { ReparentCommand } from './undo-redo.js';

export class HierarchyPanel {
  /** @param {import('./index.js').WorldBuilderPro} builder */
  constructor(builder) {
    this.builder  = builder;
    this.ctx      = builder.ctx;
    this._isOpen  = false;
    this._panel   = null;

    /** Map of entry.id → parent entry.id (null = scene root) */
    this._parentMap = new Map();
    /** Map of entry.id → label override */
    this._labels    = new Map();

    this._dragSrc   = null; // entry being dragged

    this._injectStyles();
    this._buildPanel();
  }

  // ─────────────────────────────────────────────────────────
  // PANEL
  // ─────────────────────────────────────────────────────────

  _injectStyles() {
    if (document.getElementById('wb-hier-styles')) return;
    const s = document.createElement('style');
    s.id = 'wb-hier-styles';
    s.textContent = `
#wb-hier-panel{
  position:fixed;right:0;top:48px;bottom:56px;
  width:220px;z-index:65;
  background:rgba(3,6,20,.97);
  border-left:1px solid rgba(0,232,255,.1);
  backdrop-filter:blur(14px);
  display:flex;flex-direction:column;
  font-family:"Share Tech Mono",monospace;
  transform:translateX(100%);
  transition:transform .28s cubic-bezier(.2,.8,.2,1);
}
#wb-hier-panel.open{ transform:translateX(0); }
.wbh-hdr{
  padding:10px 12px 8px;border-bottom:1px solid rgba(0,232,255,.08);
  display:flex;align-items:center;gap:8px;flex-shrink:0;
}
.wbh-title{
  font-family:"Orbitron",sans-serif;font-size:8.5px;font-weight:700;
  letter-spacing:.14em;color:rgba(200,175,255,.9);
}
.wbh-close{
  margin-left:auto;width:18px;height:18px;border-radius:50%;
  background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.07);
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:8px;color:rgba(255,255,255,.3);transition:all .15s;
}
.wbh-close:hover{ background:rgba(255,61,86,.1);color:#ff3d56; }
.wbh-toolbar{
  padding:6px 10px;border-bottom:1px solid rgba(0,232,255,.05);
  display:flex;gap:5px;flex-shrink:0;
}
.wbh-tbtn{
  flex:1;padding:5px 4px;border-radius:4px;cursor:pointer;font-size:7px;
  border:1px solid rgba(0,232,255,.15);background:rgba(0,232,255,.05);
  color:rgba(0,232,255,.7);text-align:center;transition:all .15s;
  font-family:"Orbitron",sans-serif;letter-spacing:.06em;
}
.wbh-tbtn:hover{ background:rgba(0,232,255,.12);color:#00e8ff; }
.wbh-tree{
  flex:1;overflow-y:auto;padding:6px 4px;
}
.wbh-tree::-webkit-scrollbar{ width:3px; }
.wbh-tree::-webkit-scrollbar-thumb{ background:rgba(0,232,255,.12); }
.wbh-node{
  display:flex;align-items:center;gap:5px;
  padding:4px 6px;border-radius:4px;cursor:pointer;
  font-size:8px;color:rgba(155,215,235,.55);
  border:1px solid transparent;
  transition:all .12s;user-select:none;
  position:relative;
}
.wbh-node:hover{ background:rgba(0,232,255,.05);color:rgba(155,215,235,.85); }
.wbh-node.on{
  background:rgba(0,232,255,.1);border-color:rgba(0,232,255,.22);color:#00e8ff;
}
.wbh-node.drag-over{
  border-color:rgba(155,48,255,.6);background:rgba(155,48,255,.12);
}
.wbh-node-icon{ font-size:10px;flex-shrink:0; }
.wbh-node-label{
  flex:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-size:7.5px;
}
.wbh-node-id{ font-size:6px;color:rgba(90,165,190,.3);flex-shrink:0; }
.wbh-children{ margin-left:14px; }
.wbh-expand{
  width:12px;height:12px;display:flex;align-items:center;justify-content:center;
  font-size:7px;color:rgba(90,165,190,.35);flex-shrink:0;cursor:pointer;
}
.wbh-empty{
  text-align:center;padding:20px 10px;
  font-size:8px;color:rgba(90,165,190,.3);line-height:1.7;
}
`;
    document.head.appendChild(s);
  }

  _buildPanel() {
    const panel = document.createElement('div');
    panel.id = 'wb-hier-panel';
    panel.innerHTML = `
<div class="wbh-hdr">
  <span style="font-size:13px">🌳</span>
  <div class="wbh-title">SCENE HIERARCHY</div>
  <div class="wbh-close" id="wbh-close">✕</div>
</div>
<div class="wbh-toolbar">
  <div class="wbh-tbtn" id="wbh-group-btn">⊞ GROUP</div>
  <div class="wbh-tbtn" id="wbh-ungroup-btn">⊟ UNGROUP</div>
  <div class="wbh-tbtn" id="wbh-refresh-btn">↺</div>
</div>
<div class="wbh-tree" id="wbh-tree"></div>
`;
    document.body.appendChild(panel);
    this._panel = panel;

    panel.querySelector('#wbh-close').onclick    = () => this.close();
    panel.querySelector('#wbh-refresh-btn').onclick = () => this.refresh();
    panel.querySelector('#wbh-group-btn').onclick   = () => this._groupSelected();
    panel.querySelector('#wbh-ungroup-btn').onclick = () => this._ungroupSelected();
  }

  // ─────────────────────────────────────────────────────────
  // OPEN / CLOSE
  // ─────────────────────────────────────────────────────────

  open()  { this._isOpen = true;  this._panel.classList.add('open');    this.refresh(); }
  close() { this._isOpen = false; this._panel.classList.remove('open'); }
  toggle(){ this._isOpen ? this.close() : this.open(); }

  // ─────────────────────────────────────────────────────────
  // TREE RENDERING
  // ─────────────────────────────────────────────────────────

  refresh() {
    const tree = document.getElementById('wbh-tree');
    if (!tree) return;
    tree.innerHTML = '';

    const objects = this.builder.catalogSystem.getObjects();
    if (!objects.length) {
      tree.innerHTML = '<div class="wbh-empty">No objects in scene.<br>Place some objects first.</div>';
      return;
    }

    // Build parent → children map
    const childrenOf = new Map(); // parentId → [entry]
    const roots = [];
    objects.forEach(entry => {
      const parentId = this._parentMap.get(entry.id) || null;
      if (parentId) {
        if (!childrenOf.has(parentId)) childrenOf.set(parentId, []);
        childrenOf.get(parentId).push(entry);
      } else {
        roots.push(entry);
      }
    });

    const renderNode = (entry, depth = 0) => {
      const children = childrenOf.get(entry.id) || [];
      const hasChildren = children.length > 0;
      const label = this._labels.get(entry.id) || entry.item_id || entry.id;
      const isSel = this.builder.transformSystem.selectedEntry?.id === entry.id ||
                    this.builder.multiSelect.isSelected(entry);
      const catEntry = this.builder.catalogSystem.findCatalogEntry(entry.item_id);
      const icon  = catEntry?.icon || '🧱';
      const idShort = String(entry.id).startsWith('tmp_') ? 'tmp' : String(entry.id).slice(-4);

      const node = document.createElement('div');
      node.style.marginLeft = `${depth * 12}px`;
      node.className = `wbh-node${isSel ? ' on' : ''}`;
      node.dataset.entryId = entry.id;
      node.draggable = true;

      node.innerHTML = `
        <span class="wbh-expand">${hasChildren ? '▾' : '·'}</span>
        <span class="wbh-node-icon">${icon}</span>
        <span class="wbh-node-label" title="${label}">${label}</span>
        <span class="wbh-node-id">${idShort}</span>
      `;

      // Click to select in 3D
      node.onclick = e => {
        e.stopPropagation();
        if (e.shiftKey) {
          this.builder.multiSelect.add(entry);
        } else {
          this.builder.transformSystem.select(entry);
        }
        this.refresh(); // update highlighting
      };

      // Double-click to rename
      node.ondblclick = e => {
        e.stopPropagation();
        const newLabel = prompt('Rename object:', label);
        if (newLabel) { this._labels.set(entry.id, newLabel); this.refresh(); }
      };

      // Drag-and-drop for reparenting
      node.ondragstart = e => {
        this._dragSrc = entry;
        e.dataTransfer.effectAllowed = 'move';
      };
      node.ondragover = e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        node.classList.add('drag-over');
      };
      node.ondragleave = () => node.classList.remove('drag-over');
      node.ondrop = async e => {
        e.preventDefault();
        node.classList.remove('drag-over');
        if (!this._dragSrc || this._dragSrc.id === entry.id) return;
        const oldParent = this._parentMap.get(this._dragSrc.id) || null;
        const newParent = entry.id;
        const cmd = new ReparentCommand(this.builder, this._dragSrc, oldParent, newParent);
        await this.builder.undoRedo.execute(cmd);
        this._parentMap.set(this._dragSrc.id, newParent);
        this._dragSrc = null;
        this.refresh();
      };

      tree.appendChild(node);

      // Render children recursively
      children.forEach(child => renderNode(child, depth + 1));
    };

    roots.forEach(entry => renderNode(entry, 0));
  }

  // ─────────────────────────────────────────────────────────
  // GROUP / UNGROUP
  // ─────────────────────────────────────────────────────────

  _groupSelected() {
    const sel = this.builder.multiSelect.selection;
    if (sel.size < 2) {
      this.builder.ui.setStatus('Select 2+ objects (Shift+click) to group.');
      return;
    }

    // Create a virtual group id
    const groupId = `group_${Date.now()}`;
    this._labels.set(groupId, 'GROUP');

    // Set all selected objects as children of the group
    sel.forEach(entry => {
      this._parentMap.set(entry.id, groupId);
    });

    this.builder.multiSelect.clear();
    this.refresh();
    this.builder.ui.setStatus(`Grouped ${sel.size} objects.`);
  }

  _ungroupSelected() {
    const entry = this.builder.transformSystem.selectedEntry;
    if (!entry) return;
    const parentId = this._parentMap.get(entry.id);
    if (!parentId) { this.builder.ui.setStatus('Object has no parent group.'); return; }

    // Find all siblings (same parent) and remove parent
    this.builder.catalogSystem.getObjects().forEach(o => {
      if (this._parentMap.get(o.id) === parentId) this._parentMap.delete(o.id);
    });
    this.refresh();
    this.builder.ui.setStatus('Ungrouped.');
  }

  getParentId(entryId) { return this._parentMap.get(entryId) || null; }
  getLabel(entryId)    { return this._labels.get(entryId) || null; }
  setLabel(entryId, l) { this._labels.set(entryId, l); }

  isOpen() { return this._isOpen; }
}
