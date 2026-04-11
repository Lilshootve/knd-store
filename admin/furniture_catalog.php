<?php
/**
 * Admin — Nexus Furniture Catalog (Sanctum items)
 */
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/_guard.php';
admin_require_login();
admin_require_perm('nexus.catalog.edit');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Furniture Catalog — KND Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<style>
  body{background:#0d1117;color:#c9d1d9}
  .card{background:#161b22;border-color:#30363d}
  .table{--bs-table-bg:#161b22;--bs-table-color:#c9d1d9;--bs-table-border-color:#30363d}
  .table-hover tbody tr:hover{--bs-table-accent-bg:#1c2230}
  .modal-content{background:#161b22;border-color:#30363d}
  .modal-header,.modal-footer{border-color:#30363d}
  .form-control,.form-select{background:#0d1117;color:#c9d1d9;border-color:#30363d}
  .form-control:focus,.form-select:focus{background:#0d1117;color:#c9d1d9;border-color:#58a6ff;box-shadow:none}
  .form-check-input{background-color:#0d1117;border-color:#30363d}
  .badge-common{background:#6e7681}.badge-uncommon{background:#3fb950}
  .badge-rare{background:#58a6ff}.badge-epic{background:#bc8cff}
  .badge-legendary{background:#f0883e}
  textarea.json-field{font-family:monospace;font-size:12px}
  .admin-header{background:#161b22;border-bottom:1px solid #30363d;padding:1rem 1.5rem;margin-bottom:1.5rem}
  .admin-header h1{font-size:1.25rem;margin:0;color:#58a6ff}
  .status-dot{width:8px;height:8px;border-radius:50%;display:inline-block}
  .dot-active{background:#3fb950}.dot-inactive{background:#6e7681}
</style>
</head>
<body>
<div class="admin-header d-flex align-items-center justify-content-between">
  <div>
    <a href="/admin/" class="text-secondary text-decoration-none me-3">← Admin</a>
    <h1 class="d-inline">🪑 Furniture Catalog <small class="text-secondary fs-6">(Sanctum)</small></h1>
  </div>
  <div class="d-flex gap-2">
    <div class="form-check form-switch mt-1 me-2">
      <input class="form-check-input" type="checkbox" id="showAll">
      <label class="form-check-label text-secondary" for="showAll">Show disabled</label>
    </div>
    <button class="btn btn-primary btn-sm" onclick="openModal()">+ Add Item</button>
  </div>
</div>

<div class="container-fluid px-4">
  <div id="alertBox"></div>

  <!-- Search -->
  <div class="row mb-3">
    <div class="col-md-4">
      <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search name, code, category…">
    </div>
  </div>

  <div class="card">
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-hover mb-0" id="catalogTable">
          <thead class="text-secondary" style="font-size:12px;border-bottom:1px solid #30363d">
            <tr>
              <th class="ps-3">ID</th>
              <th>Code</th>
              <th>Name</th>
              <th>Category</th>
              <th>Rarity</th>
              <th>Size</th>
              <th>Price (KP)</th>
              <th>Status</th>
              <th class="pe-3 text-end">Actions</th>
            </tr>
          </thead>
          <tbody id="catalogBody">
            <tr><td colspan="9" class="text-center text-secondary py-4">Loading…</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="text-secondary mt-2" style="font-size:12px" id="countLabel"></div>
</div>

<!-- Create / Edit Modal -->
<div class="modal fade" id="itemModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Add Item</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="fId">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label text-secondary">Code *</label>
            <input type="text" class="form-control" id="fCode" placeholder="cyber_chair_01">
          </div>
          <div class="col-md-4">
            <label class="form-label text-secondary">Name *</label>
            <input type="text" class="form-control" id="fName" placeholder="Cyber Chair">
          </div>
          <div class="col-md-4">
            <label class="form-label text-secondary">Category *</label>
            <input type="text" class="form-control" id="fCategory" placeholder="seating">
          </div>
          <div class="col-md-3">
            <label class="form-label text-secondary">Rarity</label>
            <select class="form-select" id="fRarity">
              <option value="common">Common</option>
              <option value="uncommon">Uncommon</option>
              <option value="rare">Rare</option>
              <option value="epic">Epic</option>
              <option value="legendary">Legendary</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label text-secondary">Width</label>
            <input type="number" class="form-control" id="fWidth" min="1" max="10" value="1">
          </div>
          <div class="col-md-2">
            <label class="form-label text-secondary">Depth</label>
            <input type="number" class="form-control" id="fDepth" min="1" max="10" value="1">
          </div>
          <div class="col-md-3">
            <label class="form-label text-secondary">Price (KP)</label>
            <input type="number" class="form-control" id="fPrice" min="0" value="0">
          </div>
          <div class="col-md-2 d-flex align-items-end pb-1">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" id="fActive" checked>
              <label class="form-check-label text-secondary" for="fActive">Active</label>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label text-secondary">Asset Data <span class="text-secondary">(JSON)</span></label>
            <textarea class="form-control json-field" id="fAsset" rows="6" placeholder='{"model":"/assets/models/chair.glb","scale":1}'></textarea>
            <div class="text-danger mt-1" style="font-size:11px" id="jsonError"></div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm" onclick="saveItem()">Save</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const API = '/api/admin/furniture_catalog.php';
let allItems = [];
let modal;

document.addEventListener('DOMContentLoaded', () => {
  modal = new bootstrap.Modal(document.getElementById('itemModal'));
  loadItems();
  document.getElementById('showAll').addEventListener('change', loadItems);
  document.getElementById('searchInput').addEventListener('input', renderTable);
  document.getElementById('fAsset').addEventListener('input', validateJson);
});

function loadItems() {
  const showAll = document.getElementById('showAll').checked;
  fetch(API + (showAll ? '?all=1' : ''))
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { showAlert('Failed to load: ' + d.error, 'danger'); return; }
      allItems = d.items || [];
      renderTable();
    })
    .catch(e => showAlert('Network error: ' + e, 'danger'));
}

function renderTable() {
  const q = document.getElementById('searchInput').value.toLowerCase();
  const rows = allItems.filter(i =>
    !q || i.name.toLowerCase().includes(q) ||
    i.code.toLowerCase().includes(q) ||
    i.category.toLowerCase().includes(q)
  );
  document.getElementById('countLabel').textContent = rows.length + ' item(s)';
  const tbody = document.getElementById('catalogBody');
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="9" class="text-center text-secondary py-4">No items found.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(i => `
    <tr class="${!parseInt(i.is_active) ? 'opacity-50' : ''}">
      <td class="ps-3 text-secondary">${i.id}</td>
      <td><code class="text-info">${esc(i.code)}</code></td>
      <td>${esc(i.name)}</td>
      <td><span class="text-secondary">${esc(i.category)}</span></td>
      <td><span class="badge badge-${i.rarity}">${i.rarity}</span></td>
      <td class="text-secondary">${i.width}×${i.depth}</td>
      <td>${i.price_kp} KP</td>
      <td>
        <span class="status-dot ${parseInt(i.is_active) ? 'dot-active' : 'dot-inactive'} me-1"></span>
        ${parseInt(i.is_active) ? 'Active' : 'Disabled'}
      </td>
      <td class="pe-3 text-end">
        <button class="btn btn-outline-secondary btn-sm py-0" onclick="openModal(${i.id})">Edit</button>
        ${parseInt(i.is_active) ? `<button class="btn btn-outline-danger btn-sm py-0 ms-1" onclick="disableItem(${i.id})">Disable</button>` : ''}
      </td>
    </tr>
  `).join('');
}

function openModal(id) {
  clearModal();
  document.getElementById('modalTitle').textContent = id ? 'Edit Item' : 'Add Item';
  if (id) {
    const item = allItems.find(i => i.id == id);
    if (!item) return;
    document.getElementById('fId').value       = item.id;
    document.getElementById('fCode').value     = item.code;
    document.getElementById('fName').value     = item.name;
    document.getElementById('fCategory').value = item.category;
    document.getElementById('fRarity').value   = item.rarity;
    document.getElementById('fWidth').value    = item.width;
    document.getElementById('fDepth').value    = item.depth;
    document.getElementById('fPrice').value    = item.price_kp;
    document.getElementById('fActive').checked = !!parseInt(item.is_active);
    document.getElementById('fAsset').value    = item.asset_data ? prettyJson(item.asset_data) : '';
  }
  modal.show();
}

function clearModal() {
  ['fId','fCode','fName','fCategory','fAsset'].forEach(id => document.getElementById(id).value = '');
  document.getElementById('fRarity').value = 'common';
  document.getElementById('fWidth').value = 1;
  document.getElementById('fDepth').value = 1;
  document.getElementById('fPrice').value = 0;
  document.getElementById('fActive').checked = true;
  document.getElementById('jsonError').textContent = '';
}

function validateJson() {
  const raw = document.getElementById('fAsset').value.trim();
  const el  = document.getElementById('jsonError');
  if (!raw) { el.textContent = ''; return true; }
  try { JSON.parse(raw); el.textContent = ''; return true; }
  catch(e) { el.textContent = 'Invalid JSON: ' + e.message; return false; }
}

function saveItem() {
  if (!validateJson()) return;
  const id = document.getElementById('fId').value;
  const payload = {
    code:       document.getElementById('fCode').value.trim(),
    name:       document.getElementById('fName').value.trim(),
    category:   document.getElementById('fCategory').value.trim(),
    rarity:     document.getElementById('fRarity').value,
    width:      parseInt(document.getElementById('fWidth').value),
    depth:      parseInt(document.getElementById('fDepth').value),
    price_kp:   parseInt(document.getElementById('fPrice').value),
    is_active:  document.getElementById('fActive').checked ? 1 : 0,
    asset_data: document.getElementById('fAsset').value.trim() || null,
  };
  if (id) payload.id = parseInt(id);

  fetch(API, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(payload) })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { showAlert('Error: ' + d.error, 'danger'); return; }
      modal.hide();
      showAlert(id ? 'Item updated.' : 'Item created.', 'success');
      loadItems();
    })
    .catch(e => showAlert('Network error: ' + e, 'danger'));
}

function disableItem(id) {
  if (!confirm('Disable this item? It will be hidden from users but data is preserved.')) return;
  fetch(API + '?id=' + id, { method: 'DELETE' })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) { showAlert('Error: ' + d.error, 'danger'); return; }
      showAlert('Item disabled.', 'warning');
      loadItems();
    });
}

function showAlert(msg, type) {
  const box = document.getElementById('alertBox');
  box.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show py-2" role="alert">
    ${esc(msg)}<button type="button" class="btn-close btn-close-white py-2" data-bs-dismiss="alert"></button>
  </div>`;
}

function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function prettyJson(v) {
  if (typeof v === 'string') {
    try { v = JSON.parse(v); } catch(e) { return v; }
  }
  return JSON.stringify(v, null, 2);
}
</script>
</body>
</html>
