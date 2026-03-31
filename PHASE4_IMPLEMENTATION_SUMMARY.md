# Phase 4 Implementation Summary: API Exposure

## Overview
Exposed the new avatar drop, fragment, and badge data through REST API endpoints. All endpoints follow existing project conventions for authentication, error handling, and response formats.

## API Endpoints

### 1. Drop Play API (ALREADY COMPLETE)
**Endpoint:** `POST /api/drop/play.php`

**Authentication:** Required (verified email)

**What it returns:**
```json
{
  "ok": true,
  "season": {
    "name": "Season Name",
    "ends_at": "2026-04-01 00:00:00"
  },
  "entry": 100,
  "rarity": "rare",
  "item": {
    "id": 42,
    "code": "hair_legendary_01",
    "name": "Legendary Hair",
    "slot": "hair",
    "asset_path": "/assets/avatars/hair/legendary_01.svg"
  },
  "was_duplicate": false,
  "fragments_awarded": 0,
  "fragments_total": 150,
  "pity_boost": 4,
  "xp_awarded": 4,
  "xp_delta": 4,
  "xp_total": 1250,
  "level": 11,
  "level_up": false,
  "balance": 450,
  "badges_unlocked": ["DROP_10", "COLLECTOR_10"]
}
```

**Key Fields:**
- `item` - Full avatar item details (id, code, name, slot, asset_path)
- `rarity` - Item rarity (common, special, rare, epic, legendary)
- `was_duplicate` - Boolean indicating if item was already owned
- `fragments_awarded` - Fragments given for duplicate (0 if new item)
- `fragments_total` - User's total fragment balance after drop
- `badges_unlocked` - Array of newly unlocked badge codes (only present if badges were unlocked)
- `pity_boost` - Current pity boost percentage
- `level_up` - Boolean indicating if user leveled up
- `old_level`, `new_level` - Present if level_up is true

**Notes:**
- This endpoint was already implemented and returns all required data
- Badge unlocking happens automatically after successful drop
- Response includes both the reward and user's updated state

---

### 2. Fragment Balance API (NEW)
**Endpoint:** `GET /api/avatar/fragments.php`

**Authentication:** Required

**What it returns:**
```json
{
  "ok": true,
  "fragments": 150
}
```

**Key Fields:**
- `fragments` - User's current fragment balance (integer)

**Use Cases:**
- Display fragment balance in UI
- Check if user has enough fragments for crafting/exchange
- Update fragment counter after drops

---

### 3. User Badges API (CREATED IN PHASE 3)
**Endpoint:** `GET /api/badges/user_badges.php`

**Authentication:** Required

**What it returns:**
```json
{
  "ok": true,
  "unlocked_badges": [
    {
      "id": 1,
      "code": "DROP_10",
      "name": "Drop Explorer",
      "description": "Opened 10 capsules",
      "icon_path": "/assets/badges/drop_10.svg",
      "unlock_type": "drop",
      "unlock_threshold": 10,
      "unlocked_at": "2026-03-06 10:15:30"
    }
  ],
  "progress": [
    {
      "code": "DROP_10",
      "name": "Drop Explorer",
      "description": "Opened 10 capsules",
      "icon_path": "/assets/badges/drop_10.svg",
      "unlock_type": "drop",
      "threshold": 10,
      "current": 15,
      "unlocked": true,
      "progress_percent": 100
    },
    {
      "code": "DROP_50",
      "name": "Drop Veteran",
      "description": "Opened 50 capsules",
      "icon_path": "/assets/badges/drop_50.svg",
      "unlock_type": "drop",
      "threshold": 50,
      "current": 15,
      "unlocked": false,
      "progress_percent": 30
    }
  ],
  "milestones": {
    "generator": 25,
    "drop": 15,
    "collector": 12,
    "legendary_pull": 1,
    "level": 11
  }
}
```

**Key Fields:**
- `unlocked_badges` - Array of badges user has unlocked (with unlock timestamp)
- `progress` - Array of all badges with progress tracking
  - `current` - User's current count for this milestone type
  - `threshold` - Required count to unlock
  - `unlocked` - Boolean indicating if badge is unlocked
  - `progress_percent` - Progress percentage (0-100)
- `milestones` - User's current counts for all milestone types

**Use Cases:**
- Display badge showcase on profile
- Show badge progress bars
- Display newly unlocked badge notifications
- Track user achievements

---

## Response Format Consistency

All endpoints follow the project's standard response format:

**Success Response:**
```json
{
  "ok": true,
  ...data fields...
}
```

**Error Response:**
```json
{
  "ok": false,
  "error": "ERROR_CODE",
  "message": "Human-readable error message"
}
```

## Authentication & Security

All endpoints use existing project patterns:
- Session-based authentication via `api_require_login()`
- Drop endpoint requires verified email via `api_require_verified_email()`
- CSRF protection on POST endpoints
- Rate limiting on drop endpoint
- Standard error logging

## Data Flow

### Drop Flow:
1. User calls `POST /api/drop/play.php`
2. Backend processes drop (deducts KP, selects item, awards XP)
3. Backend checks and grants eligible badges
4. Response includes:
   - Item details (with rarity, duplicate status)
   - Fragments (awarded + total balance)
   - Badges (newly unlocked codes)
   - XP/level changes

### Fragment Check:
1. User calls `GET /api/avatar/fragments.php`
2. Backend queries user's fragment balance
3. Response includes current fragment count

### Badge Check:
1. User calls `GET /api/badges/user_badges.php`
2. Backend queries:
   - User's unlocked badges
   - All badge definitions
   - User's milestone counts
3. Response includes:
   - Unlocked badges with timestamps
   - Progress for all badges
   - Current milestone counts

## Frontend Integration Notes

### Drop Response Handling:
```javascript
// After successful drop
if (response.badges_unlocked && response.badges_unlocked.length > 0) {
  // Show badge unlock notification
  showBadgeNotification(response.badges_unlocked);
}

if (response.was_duplicate) {
  // Show "Duplicate! +X fragments" message
  showFragmentReward(response.fragments_awarded);
} else {
  // Show "New item unlocked!" message
  showNewItemReward(response.item);
}

// Update fragment counter
updateFragmentDisplay(response.fragments_total);
```

### Badge Display:
```javascript
// Fetch and display badges
const badgeData = await fetch('/api/badges/user_badges.php');
const { unlocked_badges, progress, milestones } = badgeData;

// Show unlocked badges
displayBadgeShowcase(unlocked_badges);

// Show progress bars
displayBadgeProgress(progress);

// Show milestone stats
displayMilestones(milestones);
```

### Fragment Display:
```javascript
// Fetch current fragment balance
const fragmentData = await fetch('/api/avatar/fragments.php');
updateFragmentCounter(fragmentData.fragments);
```

## Testing Recommendations

1. **Drop API:**
   - Test successful drop returns all fields
   - Test duplicate drop awards fragments
   - Test badge unlocking on milestone thresholds
   - Test level-up includes badge checks
   - Test legendary drop unlocks LEGENDARY_PULL badge

2. **Fragment API:**
   - Test returns correct balance
   - Test balance updates after drops
   - Test with user who has no fragments (should return 0)

3. **Badge API:**
   - Test returns all unlocked badges
   - Test progress percentages are correct
   - Test milestone counts match database
   - Test with user who has no badges

## Files Modified/Created

### Created:
- `api/avatar/fragments.php` - Fragment balance endpoint

### Already Existed (Phase 3):
- `api/badges/user_badges.php` - Badge data endpoint

### Already Complete:
- `api/drop/play.php` - Drop play endpoint (already returns all required data)

## Notes

- All API endpoints are production-ready
- No frontend changes required for Phase 4
- All endpoints follow existing project conventions
- Error handling is consistent across all endpoints
- Badge unlocking is automatic and transparent to the API caller
- Fragment balance is always included in drop response
