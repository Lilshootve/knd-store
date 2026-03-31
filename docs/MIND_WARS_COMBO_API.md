# Mind Wars - Combo Action API Design

## Overview
Extension of `perform_action.php` to support 3-card ability combos while maintaining backward compatibility with single-action turns.

## Current System (Single Action)
```
POST /api/mind-wars/perform_action.php
{
  battle_token: "abc123...",
  action: "attack" | "defend" | "ability" | "special",
  action_id: "uuid-optional"
}

Response:
{
  ok: true,
  state: { player: {...}, enemy: {...}, log: [...], ... },
  battle_over: false,
  result: null,
  rewards: null
}
```

## New System (3-Card Combo)

### Request Format
```
POST /api/mind-wars/perform_action.php
{
  battle_token: "abc123...",
  combo_actions: [
    { action: "ability", card_id: "card_1" },
    { action: "special", card_id: "card_2" },
    { action: "attack", card_id: "card_3" }
  ],
  action_id: "uuid-optional"
}
```

### Backward Compatibility
If `combo_actions` is not present, fall back to single `action` parameter (current behavior).

### Energy System for Combos
- Each card has an energy cost (1-5)
- Total combo cost must not exceed player's available energy
- Cards execute in sequence specified by array order
- Energy is deducted before first action

### Response Format (Extended)
```json
{
  "ok": true,
  "state": {
    "player": {...},
    "enemy": {...},
    "log": [...],
    "turn": 5
  },
  "combo_executed": true,
  "actions_performed": 3,
  "battle_over": false,
  "result": null,
  "rewards": null
}
```

## Implementation Plan

### 1. Validation Layer
- Validate combo_actions array (1-3 elements)
- Check total energy cost
- Verify each action type is valid
- Ensure abilities aren't on cooldown

### 2. Execution Layer
- Execute actions sequentially
- Aggregate log entries
- Check for knockouts after each action
- Stop execution if battle ends mid-combo

### 3. State Management
- Single state object updated through combo
- Consolidated log with all actions
- Final state reflects all combo effects

### 4. Cooldown Handling
- Abilities used in combo enter cooldown
- Special resets energy to 0
- Track which abilities were used

## Energy Cost Reference
```
attack:  1 energy
defend:  0 energy (passive)
ability: 2 energy
special: 5 energy (max)
heal:    2 energy
```

## Combo Examples

### Example 1: Burst Combo
```json
{
  "combo_actions": [
    { "action": "ability", "energy_cost": 2 },
    { "action": "attack", "energy_cost": 1 },
    { "action": "attack", "energy_cost": 1 }
  ]
}
// Total: 4 energy
```

### Example 2: Ultimate Finisher
```json
{
  "combo_actions": [
    { "action": "special", "energy_cost": 5 }
  ]
}
// Total: 5 energy
```

### Example 3: Ability Chain
```json
{
  "combo_actions": [
    { "action": "ability", "energy_cost": 2 },
    { "action": "ability", "energy_cost": 2 },
    { "action": "attack", "energy_cost": 1 }
  ]
}
// Total: 5 energy
// Note: Requires 2 different abilities (separate cooldowns)
```

## Database Schema Considerations

No immediate DB changes required, but future enhancements could include:

```sql
-- Track combo usage statistics
CREATE TABLE knd_mind_wars_combo_stats (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  battle_id INT NOT NULL,
  combo_actions JSON NOT NULL,
  total_damage INT DEFAULT 0,
  turn_used INT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Frontend Integration

The arena playback system (`arena-playback.js`) already supports sequential action execution via `executeTurnPlayback()`. The log-to-action converter will automatically handle combo logs.

## Testing Strategy

1. **Single Action** (backward compat) - Existing tests pass
2. **2-Card Combo** - Ability + Attack
3. **3-Card Combo** - Full combo execution
4. **Energy Overflow** - Reject combo exceeding max energy
5. **Cooldown Conflict** - Reject same ability twice
6. **Mid-Combo KO** - Stop execution if enemy dies
7. **Invalid Actions** - Handle gracefully

## Migration Path

1. ✅ Phase 1: Arena playback refactor (COMPLETE)
2. 🔄 Phase 2: Extend perform_action.php (IN PROGRESS)
3. Phase 3: Create combo builder UI
4. Phase 4: Add combo validation endpoint
5. Phase 5: Deploy and monitor

## Notes

- PvP mode: Opponent's turn still single action (for now)
- 3v3 mode: Combo applies to active fighter only
- AI opponents: Continue single-action behavior
- Combo validation must be server-side (client cannot be trusted)
