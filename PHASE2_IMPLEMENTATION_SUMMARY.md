# Phase 2 Implementation Summary

## Overview
Updated the KND drop system to reward avatar items instead of KP, with a pity system and fragment conversion for duplicates.

## Files Modified

### 1. `includes/knd_drop.php` (Main Implementation)
**Changes:**
- **Removed KP rewards**: Drops no longer grant KP as the main reward
- **Added weighted rarity selection**: Implemented base weights (common=55, special=25, rare=12, epic=6, legendary=2)
- **Added pity system**: 
  - Tracks drops since last rare+ item in `knd_user_pity` table
  - Adds 2% boost to rare+ chances per drop without rare/epic/legendary
  - Caps at 30% maximum boost
  - Resets when rare/epic/legendary is obtained
- **Added avatar item rewards**:
  - Selects random avatar item based on rarity
  - Checks if user owns the item
  - Grants new items to `knd_user_avatar_inventory`
  - Converts duplicates to fragments
- **Added fragment system**:
  - Fragment values: common=5, special=15, rare=30, epic=75, legendary=200
  - Stores fragments in `knd_user_fragments` table
- **Added reward tracking**: Records all drop rewards in `knd_user_drop_rewards` table

**New Functions:**
- `get_user_pity()` - Get user's pity counter
- `update_user_pity()` - Update pity counter based on rarity
- `select_rarity_with_pity()` - Weighted rarity selection with pity boost
- `select_avatar_item_by_rarity()` - Random item selection by rarity
- `user_owns_avatar_item()` - Check item ownership
- `grant_avatar_item()` - Add item to user inventory
- `award_fragments()` - Add fragments for duplicates
- `get_user_fragments()` - Get user's fragment balance
- `record_drop_reward()` - Record reward details

**Updated Functions:**
- `drop_play()` - Complete rewrite to use avatar items instead of KP

**New Constants:**
- `$FRAGMENT_VALUES` - Fragment amounts per rarity
- `$RARITY_WEIGHTS` - Base probability weights
- `PITY_BOOST_PER_DROP` - 2% boost per drop
- `PITY_MAX_BOOST` - 30% maximum boost

## Database Tables Used

### Existing Tables (Already Created)
1. **`knd_user_drop_rewards`** - Stores drop reward details
   - Tracks item received, rarity, duplicate status, fragments, pity boost
   
2. **`knd_user_fragments`** - User fragment balances
   - Single currency for duplicate conversions
   
3. **`knd_user_pity`** - Pity counter per user
   - Tracks drops since last rare+ item
   
4. **`knd_avatar_items`** - Avatar item catalog
   - Must have 'special' rarity added (migration already exists)
   
5. **`knd_user_avatar_inventory`** - User's owned items
   - Existing avatar system infrastructure

## API Response Changes

### New Response Format
```json
{
  "ok": true,
  "season": {"name": "...", "ends_at": "..."},
  "entry": 100,
  "rarity": "rare",
  "item": {
    "id": 42,
    "code": "hair_legendary_01",
    "name": "Cosmic Crown",
    "slot": "hair",
    "asset_path": "/assets/avatars/hair/..."
  },
  "was_duplicate": false,
  "fragments_awarded": 0,
  "fragments_total": 150,
  "pity_boost": 6,
  "xp_awarded": 4,
  "balance": 2500,
  "xp_delta": 4,
  "xp_total": 1234,
  "level": 15,
  "level_up": false
}
```

### Removed Fields
- `reward_kp` - No longer rewards KP

### Added Fields
- `item` - Avatar item details
- `was_duplicate` - Whether user already owned the item
- `fragments_awarded` - Fragments given for duplicate (0 if new)
- `fragments_total` - User's total fragment balance
- `pity_boost` - Current pity boost percentage

## How It Works

### Drop Flow
1. User spends 100 KP to enter
2. System checks pity counter
3. Rarity selected with weighted random + pity boost
4. Random avatar item selected for that rarity
5. Check if user owns item:
   - **New item**: Add to inventory
   - **Duplicate**: Convert to fragments
6. Update pity counter (reset if rare+, increment otherwise)
7. Award XP based on rarity
8. Record reward in `knd_user_drop_rewards`
9. Return item details and fragment info

### Pity System
- Starts at 0 for new users
- Increments by 1 for each common/special drop
- Adds 2% to rare+ probability per counter value
- Caps at 30% maximum boost (after 15 drops)
- Resets to 0 when rare/epic/legendary obtained

### Fragment Conversion
When duplicate item is received:
- Common: 5 fragments
- Special: 15 fragments
- Rare: 30 fragments
- Epic: 75 fragments
- Legendary: 200 fragments

## Testing Checklist

- [ ] Verify database tables exist (run SQL migrations)
- [ ] Ensure avatar items exist with all rarities including 'special'
- [ ] Test drop with new item (should add to inventory)
- [ ] Test drop with duplicate (should award fragments)
- [ ] Verify pity counter increments on common/special
- [ ] Verify pity counter resets on rare/epic/legendary
- [ ] Check fragment balance updates correctly
- [ ] Verify XP awards for all rarities
- [ ] Confirm KP is no longer rewarded
- [ ] Check `knd_user_drop_rewards` records are created

## Migration Requirements

Before deploying, ensure these SQL files are executed:
1. `sql/knd_avatar_items_add_special_rarity.sql` - Adds 'special' rarity
2. `sql/knd_user_fragments.sql` - Creates fragment table
3. `sql/knd_user_pity.sql` - Creates pity table
4. `sql/knd_user_drop_rewards.sql` - Creates reward tracking table

## Notes

- Implementation is simple and focused on the drop flow only
- Reuses existing avatar infrastructure (`knd_avatar.php`)
- No changes to unrelated systems
- Frontend will need updates to display new response format
- Fragment shop/redemption system not included (future phase)
