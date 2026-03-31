/**
 * Mind Wars Arena — Status Effects & Synergy System
 * Floating 3D status icons above units + class-based team synergies.
 */
(function () {
  'use strict';

  /* ══════════════════════════════════════════════════════════════════
     STATUS EFFECT DEFINITIONS
     ══════════════════════════════════════════════════════════════════ */
  var STATUS = {
    defending:    { label: 'DEF',   color: 0x00ccff, icon: '🛡', desc: 'Damage reduced 50%' },
    dmgReduction: { label: 'ARMOR', color: 0x4488aa, icon: '🔷', desc: 'Passive damage reduction' },
    lifesteal:    { label: 'VAMP',  color: 0xcc0033, icon: '🩸', desc: 'Heals from damage dealt' },
    radiation:    { label: 'RAD',   color: 0x44ff00, icon: '☢',  desc: 'Enemies take +5% damage' },
    goldenAura:   { label: 'CRIT+', color: 0xffcc00, icon: '✦',  desc: 'Crit chance +5%' },
    stun:         { label: 'STUN',  color: 0xaa66ff, icon: '💫', desc: 'Cannot act this turn' },
    synergy:      { label: 'SYNC',  color: 0x00ffaa, icon: '⬡',  desc: 'Team synergy active' }
  };

  /* ══════════════════════════════════════════════════════════════════
     SYNERGY DEFINITIONS — class-based team combos
     ══════════════════════════════════════════════════════════════════ */
  var SYNERGIES = [
    {
      id: 'vanguard',
      name: 'VANGUARD',
      desc: 'Tank defending → Striker +25% crit chance',
      requires: ['Tank', 'Striker'],
      icon: '⚔🛡',
      color: '#ff6644',
      apply: function (allies) {
        var tank = allies.find(function (u) { return u.class === 'Tank' && u.hp > 0; });
        var striker = allies.find(function (u) { return u.class === 'Striker' && u.hp > 0; });
        if (tank && striker && tank.defending) {
          if (!striker._synergyBonuses) striker._synergyBonuses = {};
          striker._synergyBonuses.critBonus = 0.25;
          return { active: true, units: [tank.id, striker.id], buff: 'Striker +25% CRIT' };
        }
        if (striker && striker._synergyBonuses) delete striker._synergyBonuses.critBonus;
        return { active: false };
      }
    },
    {
      id: 'neural_link',
      name: 'NEURAL LINK',
      desc: 'Controller + Strategist → Strategist +15% damage',
      requires: ['Controller', 'Strategist'],
      icon: '🔮⚡',
      color: '#aa44ff',
      apply: function (allies) {
        var ctrl = allies.find(function (u) { return u.class === 'Controller' && u.hp > 0; });
        var strat = allies.find(function (u) { return u.class === 'Strategist' && u.hp > 0; });
        if (ctrl && strat) {
          if (!strat._synergyBonuses) strat._synergyBonuses = {};
          strat._synergyBonuses.dmgBonus = 0.15;
          return { active: true, units: [ctrl.id, strat.id], buff: 'Strategist +15% DMG' };
        }
        if (strat && strat._synergyBonuses) delete strat._synergyBonuses.dmgBonus;
        return { active: false };
      }
    },
    {
      id: 'predator',
      name: 'PREDATOR',
      desc: 'Striker kill → Controller cooldowns -1',
      requires: ['Striker', 'Controller'],
      icon: '🩸🔮',
      color: '#ff2266',
      check: 'on_kill', // triggered, not passive
      apply: function (allies, context) {
        var striker = allies.find(function (u) { return u.class === 'Striker' && u.hp > 0; });
        var ctrl = allies.find(function (u) { return u.class === 'Controller' && u.hp > 0; });
        if (striker && ctrl && context && context.killerClass === 'Striker') {
          ctrl.abilities.forEach(function (a) { if (a.cd > 0) a.cd = Math.max(0, a.cd - 1); });
          return { active: true, units: [striker.id, ctrl.id], buff: 'Controller CDs -1' };
        }
        return { active: false };
      }
    },
    {
      id: 'fortress',
      name: 'FORTRESS',
      desc: 'Tank + Controller alive → Team takes 8% less damage',
      requires: ['Tank', 'Controller'],
      icon: '🛡🔮',
      color: '#4488ff',
      apply: function (allies) {
        var tank = allies.find(function (u) { return u.class === 'Tank' && u.hp > 0; });
        var ctrl = allies.find(function (u) { return u.class === 'Controller' && u.hp > 0; });
        if (tank && ctrl) {
          allies.forEach(function (u) {
            if (!u._synergyBonuses) u._synergyBonuses = {};
            u._synergyBonuses.teamDmgReduction = 0.08;
          });
          return { active: true, units: [tank.id, ctrl.id], buff: 'Team -8% DMG taken' };
        }
        allies.forEach(function (u) {
          if (u._synergyBonuses) delete u._synergyBonuses.teamDmgReduction;
        });
        return { active: false };
      }
    },
    {
      id: 'diversity',
      name: 'DIVERSITY',
      desc: '3 different classes → All units +5% stats',
      requires: null, // special check
      icon: '⬡⬡⬡',
      color: '#00ffaa',
      apply: function (allies) {
        var classes = {};
        allies.forEach(function (u) { if (u.hp > 0 && u.class) classes[u.class] = true; });
        var uniqueCount = Object.keys(classes).length;
        if (uniqueCount >= 3) {
          allies.forEach(function (u) {
            if (!u._synergyBonuses) u._synergyBonuses = {};
            u._synergyBonuses.statBonus = 0.05;
          });
          return { active: true, units: allies.filter(function (u) { return u.hp > 0; }).map(function (u) { return u.id; }), buff: 'All +5% stats' };
        }
        allies.forEach(function (u) {
          if (u._synergyBonuses) delete u._synergyBonuses.statBonus;
        });
        return { active: false };
      }
    }
  ];

  /* ══════════════════════════════════════════════════════════════════
     STATUS ICON RENDERING — HTML overlay badges above units
     ══════════════════════════════════════════════════════════════════ */
  var _statusHost = null;

  function getOrCreateHost() {
    if (_statusHost) return _statusHost;
    _statusHost = document.getElementById('mw-status-overlay');
    if (!_statusHost) {
      _statusHost = document.createElement('div');
      _statusHost.id = 'mw-status-overlay';
      _statusHost.style.cssText = 'position:absolute;inset:0;pointer-events:none;z-index:14;overflow:hidden;';
      var arena = document.getElementById('canvas-container') || document.getElementById('three-container');
      if (arena) arena.appendChild(_statusHost);
    }
    return _statusHost;
  }

  /**
   * Update status icons for all units.
   * Call after each action/turn to reflect current state.
   * @param {Array} allies — G.allies
   * @param {Array} enemies — G.enemies
   */
  function updateStatusIcons(allies, enemies) {
    var host = getOrCreateHost();
    if (!host) return;
    host.innerHTML = '';

    var camera = null;
    var container = null;
    if (window.MWArenaThree) {
      var ctx = MWArenaThree.getActionContext();
      camera = ctx ? ctx.camera : null;
      container = document.getElementById('three-container');
    }
    if (!camera || !container) return;
    var W = container.clientWidth;
    var H = container.clientHeight;

    function projectToScreen(unit3d) {
      if (!unit3d || !camera) return null;
      var v = new THREE.Vector3();
      unit3d.getWorldPosition(v);
      v.y += 1.9; // above head
      v.project(camera);
      return {
        x: (v.x * 0.5 + 0.5) * W,
        y: (-v.y * 0.5 + 0.5) * H
      };
    }

    function getUnit3d(unitId, isEnemy) {
      if (!window.MWArenaThree) return null;
      var ctx = MWArenaThree.getActionContext();
      var arr = isEnemy ? ctx.enemy : ctx.player;
      if (!arr) return null;
      for (var i = 0; i < arr.length; i++) {
        if (arr[i] && arr[i].userData && arr[i].userData.unitId === unitId) return arr[i];
      }
      return null;
    }

    function renderUnitStatus(unit, isEnemy) {
      if (!unit || unit.hp <= 0) return;
      var unit3d = getUnit3d(unit.id, isEnemy);
      var screen = projectToScreen(unit3d);
      if (!screen) return;

      var effects = [];
      if (unit.defending) effects.push(STATUS.defending);
      if (unit.passiveData && unit.passiveData.dmgReduction) effects.push(STATUS.dmgReduction);
      if (unit.passiveData && unit.passiveData.lifesteal) effects.push(STATUS.lifesteal);
      if (unit._activeSynergies && unit._activeSynergies.length) effects.push(STATUS.synergy);
      // Stun
      if (unit._stunned) effects.push(STATUS.stun);

      if (!effects.length) return;

      var wrapper = document.createElement('div');
      wrapper.style.cssText = 'position:absolute;left:' + screen.x + 'px;top:' + screen.y + 'px;transform:translate(-50%,-100%);display:flex;gap:3px;align-items:center;';

      effects.forEach(function (eff) {
        var badge = document.createElement('div');
        badge.style.cssText = 'background:rgba(0,0,0,0.75);border:1px solid #' + eff.color.toString(16).padStart(6, '0') + ';border-radius:2px;padding:1px 4px;font-family:"Orbitron",monospace;font-size:7px;font-weight:700;letter-spacing:1px;color:#' + eff.color.toString(16).padStart(6, '0') + ';text-shadow:0 0 6px #' + eff.color.toString(16).padStart(6, '0') + '40;white-space:nowrap;animation:statusPulse 2s ease-in-out infinite;';
        badge.textContent = eff.icon + ' ' + eff.label;
        wrapper.appendChild(badge);
      });

      host.appendChild(wrapper);
    }

    allies.forEach(function (u) { renderUnitStatus(u, false); });
    enemies.forEach(function (u) { renderUnitStatus(u, true); });
  }

  /* ══════════════════════════════════════════════════════════════════
     SYNERGY ENGINE — evaluate and apply team synergies
     ══════════════════════════════════════════════════════════════════ */
  var _activeSynergies = [];
  var _synergyBannerEl = null;

  function evaluateSynergies(allies, enemies) {
    _activeSynergies = [];

    // Clear previous bonuses
    allies.concat(enemies || []).forEach(function (u) {
      u._synergyBonuses = {};
      u._activeSynergies = [];
    });

    // Evaluate for allies
    SYNERGIES.forEach(function (syn) {
      if (syn.check === 'on_kill') return; // triggered separately
      var result = syn.apply(allies);
      if (result.active) {
        _activeSynergies.push({ def: syn, result: result, side: 'player' });
        result.units.forEach(function (uid) {
          var u = allies.find(function (a) { return a.id === uid; });
          if (u) {
            if (!u._activeSynergies) u._activeSynergies = [];
            u._activeSynergies.push(syn.id);
          }
        });
      }
    });

    // Evaluate for enemies
    SYNERGIES.forEach(function (syn) {
      if (syn.check === 'on_kill') return;
      var result = syn.apply(enemies || []);
      if (result.active) {
        _activeSynergies.push({ def: syn, result: result, side: 'enemy' });
      }
    });

    return _activeSynergies;
  }

  /** Call when a kill happens — checks triggered synergies */
  function onKill(allies, killerUnit) {
    if (!killerUnit || !killerUnit.class) return;
    SYNERGIES.forEach(function (syn) {
      if (syn.check !== 'on_kill') return;
      var result = syn.apply(allies, { killerClass: killerUnit.class });
      if (result.active) {
        showSynergyPopup(syn.name + ': ' + result.buff, syn.color);
      }
    });
  }

  /* ══════════════════════════════════════════════════════════════════
     SYNERGY HUD — banner showing active synergies
     ══════════════════════════════════════════════════════════════════ */
  function renderSynergyBanner(allies) {
    if (!_synergyBannerEl) {
      _synergyBannerEl = document.getElementById('mw-synergy-banner');
      if (!_synergyBannerEl) {
        _synergyBannerEl = document.createElement('div');
        _synergyBannerEl.id = 'mw-synergy-banner';
        _synergyBannerEl.style.cssText = 'position:absolute;top:56px;left:50%;transform:translateX(-50%);z-index:16;display:flex;gap:6px;pointer-events:none;';
        var arena = document.getElementById('arena');
        if (arena) arena.appendChild(_synergyBannerEl);
      }
    }
    _synergyBannerEl.innerHTML = '';

    var playerSynergies = _activeSynergies.filter(function (s) { return s.side === 'player'; });
    if (!playerSynergies.length) return;

    playerSynergies.forEach(function (s) {
      var el = document.createElement('div');
      el.style.cssText = 'background:rgba(0,0,0,0.8);border:1px solid ' + s.def.color + ';border-radius:2px;padding:3px 10px;font-family:"Orbitron",monospace;font-size:8px;font-weight:700;letter-spacing:2px;color:' + s.def.color + ';text-shadow:0 0 8px ' + s.def.color + '60;display:flex;align-items:center;gap:5px;';
      el.innerHTML = '<span>' + s.def.icon + '</span><span>' + s.def.name + '</span><span style="font-family:\'Share Tech Mono\',monospace;font-size:7px;opacity:0.7;letter-spacing:1px;">' + s.result.buff + '</span>';
      _synergyBannerEl.appendChild(el);
    });
  }

  /** Flash popup for triggered synergies */
  function showSynergyPopup(text, color) {
    var host = document.getElementById('arena');
    if (!host) return;
    var el = document.createElement('div');
    el.style.cssText = 'position:absolute;top:40%;left:50%;transform:translate(-50%,-50%);z-index:100;background:rgba(0,0,0,0.85);border:1px solid ' + color + ';border-radius:3px;padding:6px 18px;font-family:"Orbitron",monospace;font-size:10px;font-weight:700;letter-spacing:3px;color:' + color + ';text-shadow:0 0 12px ' + color + ';pointer-events:none;animation:synergyPop 1.5s ease-out forwards;';
    el.textContent = '⬡ ' + text;
    host.appendChild(el);
    setTimeout(function () { el.remove(); }, 1600);
  }

  /* ══════════════════════════════════════════════════════════════════
     DAMAGE CALC INTEGRATION — apply synergy bonuses
     ══════════════════════════════════════════════════════════════════ */

  /**
   * Get total synergy damage multiplier for an attacker.
   * Call from calcDmg or before applying damage.
   * @returns {number} multiplier (e.g. 1.15 for +15%)
   */
  function getSynergyDmgMultiplier(unit) {
    if (!unit || !unit._synergyBonuses) return 1;
    var bonus = unit._synergyBonuses.dmgBonus || 0;
    return 1 + bonus;
  }

  /**
   * Get total synergy crit bonus for an attacker.
   * @returns {number} additive crit chance (e.g. 0.25)
   */
  function getSynergyCritBonus(unit) {
    if (!unit || !unit._synergyBonuses) return 0;
    return unit._synergyBonuses.critBonus || 0;
  }

  /**
   * Get total damage reduction from synergies for a defender.
   * @returns {number} reduction factor (e.g. 0.08)
   */
  function getSynergyDmgReduction(unit) {
    if (!unit || !unit._synergyBonuses) return 0;
    return unit._synergyBonuses.teamDmgReduction || 0;
  }

  /* ══════════════════════════════════════════════════════════════════
     CSS INJECTION
     ══════════════════════════════════════════════════════════════════ */
  var style = document.createElement('style');
  style.textContent = '@keyframes statusPulse{0%,100%{opacity:.85}50%{opacity:1}}@keyframes synergyPop{0%{opacity:0;transform:translate(-50%,-50%) scale(0.7)}15%{opacity:1;transform:translate(-50%,-50%) scale(1.1)}30%{transform:translate(-50%,-50%) scale(1)}80%{opacity:1}100%{opacity:0;transform:translate(-50%,-60%) scale(0.9)}}';
  document.head.appendChild(style);

  /* ══════════════════════════════════════════════════════════════════
     PUBLIC API
     ══════════════════════════════════════════════════════════════════ */
  window.MWArenaStatus = {
    STATUS: STATUS,
    SYNERGIES: SYNERGIES,
    updateStatusIcons: updateStatusIcons,
    evaluateSynergies: evaluateSynergies,
    renderSynergyBanner: renderSynergyBanner,
    onKill: onKill,
    showSynergyPopup: showSynergyPopup,
    getSynergyDmgMultiplier: getSynergyDmgMultiplier,
    getSynergyCritBonus: getSynergyCritBonus,
    getSynergyDmgReduction: getSynergyDmgReduction
  };
})();
