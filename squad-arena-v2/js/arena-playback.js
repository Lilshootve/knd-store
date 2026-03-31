/**
 * Mind Wars Arena - Playback Dispatcher
 * Consumes backend combat results and executes animations sequentially
 * Maps action_results payload to extracted arena-actions.js handlers
 */

/**
 * Execute a sequence of combat actions with visual playback
 * @param {Array} actionResults - Array of action objects from backend
 * @param {Object} context - Animation context from arena
 * @returns {Promise} - Resolves when all animations complete
 */
function playbackCombatSequence(actionResults, context) {
  return new Promise(function(resolve, reject) {
    if (!actionResults || !Array.isArray(actionResults) || actionResults.length === 0) {
      console.warn('No actions to playback');
      resolve();
      return;
    }

    var index = 0;
    
    function executeNext() {
      if (index >= actionResults.length) {
        console.log('Combat sequence complete');
        resolve();
        return;
      }

      var action = actionResults[index];
      index++;

      executeAction(action, context)
        .then(function() {
          // Small delay between actions for readability
          setTimeout(executeNext, 400);
        })
        .catch(function(err) {
          console.error('Action execution error:', err);
          // Continue despite errors
          setTimeout(executeNext, 400);
        });
    }

    executeNext();
  });
}

/**
 * Execute a single action based on its type
 * @param {Object} action - Action object from backend
 * @param {Object} context - Animation context
 * @returns {Promise}
 */
function executeAction(action, context) {
  var actionType = action.action_type || action.type;
  
  console.log('Executing action:', actionType, action);

  switch (actionType) {
    case 'attack':
      return handleAttackAction(action, context);
    
    case 'ability':
      return handleAbilityAction(action, context);
    
    case 'special':
    case 'ultimate':
      return handleSpecialAction(action, context);
    
    case 'defend':
    case 'guard':
      return handleDefendAction(action, context);
    
    case 'heal':
      return handleHealAction(action, context);
    
    case 'death':
    case 'unit_death':
      return handleDeathAction(action, context);
    
    case 'status_effect':
      return handleStatusEffectAction(action, context);
    
    default:
      console.warn('Unknown action type:', actionType);
      return Promise.resolve();
  }
}

/**
 * Handle basic attack action
 * Expected payload:
 * {
 *   action_type: 'attack',
 *   actor_side: 'player' | 'enemy',
 *   actor_slot: 0-2,
 *   target_slot: 0-2,
 *   damage: number
 * }
 */
function handleAttackAction(action, context) {
  var actorSide = action.actor_side || 'player';
  var actorSlot = parseInt(action.actor_slot || 0);
  var targetSlot = parseInt(action.target_slot || 0);
  var damage = parseInt(action.damage || 0);

  return executeAttack(actorSlot, targetSlot, damage, actorSide, context);
}

/**
 * Handle ability action
 * Expected payload:
 * {
 *   action_type: 'ability',
 *   actor_side: 'player' | 'enemy',
 *   actor_slot: 0-2,
 *   target_slot: 0-2,
 *   skill_code: string,
 *   damage: number,
 *   effects: ['stun', 'poison', etc]
 * }
 */
function handleAbilityAction(action, context) {
  var actorSide = action.actor_side || 'player';
  var actorSlot = parseInt(action.actor_slot || 0);
  var targetSlot = parseInt(action.target_slot || 0);
  var skillCode = action.skill_code || action.ability_code || 'unknown_ability';
  var damage = parseInt(action.damage || 0);
  var effects = action.effects || action.status_effects || [];

  return executeAbility(actorSlot, targetSlot, skillCode, damage, effects, actorSide, context);
}

/**
 * Handle special/ultimate action
 * Expected payload:
 * {
 *   action_type: 'special',
 *   actor_side: 'player' | 'enemy',
 *   actor_slot: 0-2,
 *   target_slot: 0-2,
 *   skill_code: string,
 *   damage: number,
 *   is_aoe: boolean,
 *   aoe_targets: [{slot: 0, damage: 100}, ...]
 * }
 */
function handleSpecialAction(action, context) {
  var actorSide = action.actor_side || 'player';
  var actorSlot = parseInt(action.actor_slot || 0);
  var targetSlot = parseInt(action.target_slot || 0);
  var skillCode = action.skill_code || action.ability_code || 'ultimate';
  var damage = parseInt(action.damage || 0);
  var isAoE = action.is_aoe || action.aoe || false;
  var aoeTargets = action.aoe_targets || [];

  return executeSpecial(actorSlot, targetSlot, skillCode, damage, isAoE, aoeTargets, actorSide, context);
}

/**
 * Handle defend action
 * Expected payload:
 * {
 *   action_type: 'defend',
 *   actor_side: 'player' | 'enemy',
 *   actor_slot: 0-2
 * }
 */
function handleDefendAction(action, context) {
  var actorSide = action.actor_side || 'player';
  var actorSlot = parseInt(action.actor_slot || 0);

  return executeDefend(actorSlot, actorSide, context);
}

/**
 * Handle heal action
 * Expected payload:
 * {
 *   action_type: 'heal',
 *   actor_side: 'player' | 'enemy',
 *   actor_slot: 0-2,
 *   heal_amount: number
 * }
 */
function handleHealAction(action, context) {
  var actorSide = action.actor_side || 'player';
  var actorSlot = parseInt(action.actor_slot || 0);
  var healAmount = parseInt(action.heal_amount || action.amount || 0);

  return executeHeal(actorSlot, healAmount, actorSide, context);
}

/**
 * Handle unit death action
 * Expected payload:
 * {
 *   action_type: 'death',
 *   side: 'player' | 'enemy',
 *   slot: 0-2
 * }
 */
function handleDeathAction(action, context) {
  var side = action.side || action.actor_side || 'enemy';
  var slot = parseInt(action.slot || action.actor_slot || 0);

  return executeUnitDeath(slot, side, context);
}

/**
 * Handle status effect application (visual only, no damage)
 * Expected payload:
 * {
 *   action_type: 'status_effect',
 *   target_side: 'player' | 'enemy',
 *   target_slot: 0-2,
 *   effect: 'stun' | 'poison' | 'burn' | etc
 * }
 */
function handleStatusEffectAction(action, context) {
  // For now, just log it - could add visual indicators later
  var targetSide = action.target_side || 'enemy';
  var targetSlot = parseInt(action.target_slot || 0);
  var effect = action.effect || 'unknown';

  context.addLog('Status effect applied: ' + effect.toUpperCase(), 'system-log');
  
  return Promise.resolve();
}

/**
 * Convert Mind Wars log format to action_results format
 * @param {Array} logEntries - Array of log entries from backend state.log
 * @returns {Array} - Normalized action_results array
 */
function convertLogToActionResults(logEntries) {
  if (!Array.isArray(logEntries)) {
    return [];
  }

  var actions = [];
  var currentAction = null;

  for (var i = 0; i < logEntries.length; i++) {
    var entry = logEntries[i];
    var type = entry.type || '';
    var actionType = entry.action_type || '';
    var actor = entry.actor || 'player';
    var actorName = entry.actor_name || (actor === 'player' ? 'Player' : 'Enemy');
    var targetName = entry.target || '';
    var skillCode = entry.skill_code || '';
    var damage = parseInt(entry.damage || entry.value || 0);

    // Skip pure info/status messages unless they're critical
    if ((type === 'info' || type === 'status') && !actionType) {
      continue;
    }

    // Detect action starts
    if (actionType && (type === 'damage' || type === 'crit' || type === 'heal' || type === 'evade' || actionType === 'defend')) {
      // Finalize previous action if exists
      if (currentAction) {
        actions.push(currentAction);
      }

      // Determine actor_slot and target_slot (simplified - assumes single unit for now)
      var actorSlot = 0;
      var targetSlot = 0;

      // Start new action
      currentAction = {
        action_type: actionType,
        actor_side: actor,
        actor_slot: actorSlot,
        target_slot: targetSlot,
        skill_code: skillCode || null,
        damage: damage,
        effects: []
      };
    }

    // Handle status effects
    if (type === 'cc' && currentAction) {
      var effect = extractEffectFromMsg(entry.msg || '');
      if (effect) {
        currentAction.effects.push(effect);
      }
    }

    // Handle heals
    if (type === 'heal' && !currentAction) {
      actions.push({
        action_type: 'heal',
        actor_side: actor,
        actor_slot: 0,
        heal_amount: damage
      });
    }
  }

  // Finalize last action
  if (currentAction) {
    actions.push(currentAction);
  }

  return actions;
}

/**
 * Extract effect name from log message
 * @param {string} msg - Log message
 * @returns {string|null} - Effect name or null
 */
function extractEffectFromMsg(msg) {
  var lowerMsg = msg.toLowerCase();
  if (lowerMsg.indexOf('stun') >= 0) return 'stun';
  if (lowerMsg.indexOf('freeze') >= 0 || lowerMsg.indexOf('frozen') >= 0) return 'freeze';
  if (lowerMsg.indexOf('petrif') >= 0) return 'petrify';
  if (lowerMsg.indexOf('poison') >= 0) return 'poison';
  if (lowerMsg.indexOf('burn') >= 0) return 'burn';
  return null;
}

/**
 * Parse backend combat result and prepare for playback
 * Handles various backend response formats
 * @param {Object} combatResult - Raw backend response
 * @returns {Array} - Normalized action_results array
 */
function parseCombatResult(combatResult) {
  // Handle action_results format (direct)
  if (combatResult.action_results && Array.isArray(combatResult.action_results)) {
    return combatResult.action_results;
  }
  
  if (combatResult.actions && Array.isArray(combatResult.actions)) {
    return combatResult.actions;
  }
  
  if (combatResult.turn_results && Array.isArray(combatResult.turn_results)) {
    return combatResult.turn_results;
  }

  // Handle Mind Wars log format (state.log)
  if (combatResult.state && combatResult.state.log && Array.isArray(combatResult.state.log)) {
    console.log('Converting Mind Wars log format to action_results');
    return convertLogToActionResults(combatResult.state.log);
  }

  // Direct log array
  if (combatResult.log && Array.isArray(combatResult.log)) {
    console.log('Converting direct log array to action_results');
    return convertLogToActionResults(combatResult.log);
  }

  // If combatResult is already an array
  if (Array.isArray(combatResult)) {
    return combatResult;
  }

  console.warn('Unable to parse combat result structure:', combatResult);
  return [];
}

/**
 * Main entry point: Execute full turn with backend results
 * @param {Object} combatResult - Backend combat API response
 * @param {Object} arenaContext - Animation context from squad-arena
 * @returns {Promise}
 */
function executeTurnPlayback(combatResult, arenaContext) {
  var actions = parseCombatResult(combatResult);
  
  if (actions.length === 0) {
    console.warn('No actions found in combat result');
    return Promise.resolve();
  }

  console.log('Starting turn playback with ' + actions.length + ' actions');
  
  return playbackCombatSequence(actions, arenaContext)
    .then(function() {
      console.log('Turn playback complete');
      
      // Check for battle end conditions
      if (combatResult.battle_end || combatResult.winner) {
        handleBattleEnd(combatResult, arenaContext);
      }
    });
}

/**
 * Handle battle end state
 * @param {Object} combatResult - Combat result with winner info
 * @param {Object} context - Arena context
 */
function handleBattleEnd(combatResult, context) {
  var winner = combatResult.winner;
  var message = winner === 'player' 
    ? 'VICTORY - All enemies defeated!' 
    : winner === 'enemy'
    ? 'DEFEAT - Squad eliminated'
    : 'DRAW - Battle concluded';

  context.addLog(message, 'system-log');
  
  // Could trigger victory/defeat UI here
  console.log('Battle ended:', winner);
}

/**
 * Create a mock combat result for testing
 * @returns {Object} - Mock backend response
 */
function createMockCombatResult() {
  return {
    action_results: [
      {
        action_type: 'attack',
        actor_side: 'player',
        actor_slot: 0,
        target_slot: 1,
        damage: 145
      },
      {
        action_type: 'ability',
        actor_side: 'player',
        actor_slot: 1,
        target_slot: 1,
        skill_code: 'petrifying_gaze',
        damage: 98,
        effects: ['stun']
      },
      {
        action_type: 'attack',
        actor_side: 'enemy',
        actor_slot: 2,
        target_slot: 0,
        damage: 112
      },
      {
        action_type: 'special',
        actor_side: 'player',
        actor_slot: 2,
        target_slot: 0,
        skill_code: 'ragnarok_strike',
        damage: 280,
        is_aoe: true,
        aoe_targets: [
          {slot: 0, damage: 0},
          {slot: 1, damage: 280},
          {slot: 2, damage: 195}
        ]
      },
      {
        action_type: 'death',
        side: 'enemy',
        slot: 1
      }
    ],
    battle_end: false,
    winner: null
  };
}
