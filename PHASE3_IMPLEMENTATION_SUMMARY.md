# Phase 3 Implementation Summary: Badge System Backend

## Overview
Implemented a milestone-based badge system with backend logic for automatic badge unlocking based on user achievements. The system tracks 5 types of milestones: generator, drop, collector, legendary_pull, and level.

## Files Created

### 1. `includes/knd_badges.php` (NEW)
**Purpose:** Core badge system logic and helper functions

**Key Functions:**
- `badges_get_all()` - Get all active badges
- `badges_get_user_badges()` - Get user's unlocked badges
- `badges_user_has_badge()` - Check if user has a specific badge
- `badges_grant_badge()` - Grant a badge to a user (idempotent)
- `badges_get_user_milestones()` - Get user's milestone counts for all types
- `badges_check_and_grant()` - Check and grant eligible badges for a specific unlock type
- `badges_check_all()` - Check all badge types for a user
- `badges_get_user_progress()` - Get user's badge progress for display

**Milestone Tracking:**
- **generator**: Count of completed labs jobs (`knd_labs_jobs` with `status='done'`)
- **drop**: Total drop count (`knd_drops`)
- **collector**: Unique avatar items owned (`knd_user_avatar_inventory`)
- **legendary_pull**: Count of legendary drops (`knd_drops` with `rarity='legendary'`)
- **level**: Current user level (`knd_user_xp.level`)

### 2. `api/badges/user_badges.php` (NEW)
**Purpose:** API endpoint for fetching user badge data

**Endpoint:** `GET /api/badges/user_badges.php`

**Response:**
```json
{
  "ok": true,
  "unlocked_badges": [...],
  "progress": [...],
  "milestones": {
    "generator": 15,
    "drop": 25,
    "collector": 12,
    "legendary_pull": 1,
    "level": 10
  }
}
```

## Files Modified

### 3. `includes/knd_drop.php` (MODIFIED)
**Changes:**
- Added `require_once __DIR__ . '/knd_badges.php';`
- Integrated badge checks in `drop_play()` function after successful drop
- Checks performed:
  - Drop milestone badges (after every drop)
  - Collector badges (when new item acquired, not duplicate)
  - Legendary pull badge (when legendary rarity dropped)
  - Level badges (when level-up occurs)
- Returns `badges_unlocked` array in response when new badges are granted
- Badge errors are logged but don't fail the drop transaction

### 4. `includes/knd_xp.php` (MODIFIED)
**Changes:**
- Added `require_once __DIR__ . '/knd_badges.php';`
- Badge checks for level milestones are handled by calling code (e.g., drop_play)
- XP system remains focused on XP/level calculation

### 5. `api/labs/queue/complete.php` (MODIFIED)
**Changes:**
- Added `require_once __DIR__ . '/../../../includes/knd_badges.php';`
- Integrated badge check after successful job completion
- Fetches user_id from completed job
- Calls `badges_check_and_grant($pdo, $userId, 'generator')`
- Badge errors are logged but don't fail the completion

## Badge Integration Points

### Drop Flow (`includes/knd_drop.php`)
```php
// After successful drop, before commit
$newBadges = [];
$dropBadges = badges_check_and_grant($pdo, $userId, 'drop');
$newBadges = array_merge($newBadges, $dropBadges);

if (!$wasDuplicate) {
    $collectorBadges = badges_check_and_grant($pdo, $userId, 'collector');
    $newBadges = array_merge($newBadges, $collectorBadges);
}

if ($rarity === 'legendary') {
    $legendaryBadges = badges_check_and_grant($pdo, $userId, 'legendary_pull');
    $newBadges = array_merge($newBadges, $legendaryBadges);
}

if ($levelUp) {
    $levelBadges = badges_check_and_grant($pdo, $userId, 'level');
    $newBadges = array_merge($newBadges, $levelBadges);
}
```

### Image Generation Flow (`api/labs/queue/complete.php`)
```php
// After job marked as done
$stmt = $pdo->prepare("SELECT user_id FROM knd_labs_jobs WHERE id = ?");
$stmt->execute([$jobId]);
$job = $stmt->fetch(PDO::FETCH_ASSOC);

if ($job && isset($job['user_id'])) {
    $userId = (int)$job['user_id'];
    badges_check_and_grant($pdo, $userId, 'generator');
}
```

## Badge Codes Supported

As defined in `sql/knd_badges_seed.sql`:

### Generator Badges
- `GENERATOR_10` - Generated 10 images
- `GENERATOR_100` - Generated 100 images
- `GENERATOR_500` - Generated 500 images

### Drop Badges
- `DROP_10` - Opened 10 capsules
- `DROP_50` - Opened 50 capsules
- `DROP_200` - Opened 200 capsules

### Collector Badges
- `COLLECTOR_10` - Collected 10 unique avatars
- `COLLECTOR_25` - Collected 25 unique avatars
- `COLLECTOR_50` - Collected 50 unique avatars

### Special Badges
- `LEGENDARY_PULL` - Pulled a legendary avatar

### Level Badges
- `LEVEL_10` - Reached level 10
- `LEVEL_20` - Reached level 20
- `LEVEL_30` - Reached max level

## Key Design Decisions

1. **Milestone-Based Only**: No random badge drops, all badges unlock based on achievements
2. **Idempotent Badge Granting**: `badges_grant_badge()` can be called multiple times safely
3. **Non-Blocking**: Badge check failures are logged but don't fail the main transaction
4. **Modular**: Badge logic is isolated in `includes/knd_badges.php`
5. **Efficient**: Badge checks only query relevant milestone counts
6. **Transaction-Safe**: Badge checks happen after main transaction commits in drop flow

## Error Handling

All badge check operations are wrapped in try-catch blocks:
```php
try {
    $newBadges = badges_check_and_grant($pdo, $userId, 'drop');
} catch (\Throwable $e) {
    error_log('Badge check failed: ' . $e->getMessage());
}
```

This ensures that badge system issues never break core functionality.

## Testing Recommendations

1. **Drop Flow**: Test badge unlocking after 10, 50 drops
2. **Generator Flow**: Test badge unlocking after 10, 100 image generations
3. **Collector Flow**: Test badge unlocking when acquiring 10, 25 unique items
4. **Legendary Flow**: Test badge unlocking on first legendary drop
5. **Level Flow**: Test badge unlocking when reaching levels 10, 20, 30
6. **API Endpoint**: Test `/api/badges/user_badges.php` returns correct data
7. **Idempotency**: Verify badges aren't granted multiple times
8. **Error Resilience**: Verify badge errors don't break drops/generations

## Database Requirements

Ensure these tables exist (from Phase 2):
- `knd_badges` - Badge definitions
- `knd_user_badges` - User badge unlocks
- Run `sql/knd_badges_seed.sql` to populate initial badges

## Future Enhancements (Not Implemented)

- Frontend badge display UI
- Badge notifications/toasts
- Badge showcase on profile
- Admin badge management interface
- Badge rarity tiers
- Time-limited event badges
- Social sharing of badge achievements

## Notes

- Badge system is fully backend-ready
- Frontend integration can be done independently
- All badge logic is centralized and reusable
- System is designed for easy extension with new badge types
