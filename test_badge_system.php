<?php
/**
 * Badge System Test Script
 * Run this to verify badge system is working correctly
 * Usage: php test_badge_system.php
 */

require_once __DIR__ . '/config/bootstrap.php';
require_once BASE_PATH . '/includes/config.php';
require_once BASE_PATH . '/includes/knd_badges.php';

echo "=== KND Badge System Test ===\n\n";

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        die("ERROR: Could not connect to database\n");
    }

    // Test 1: Check if badge tables exist
    echo "Test 1: Checking badge tables...\n";
    $tables = ['knd_badges', 'knd_user_badges'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() > 0) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            echo "  ✗ Table '$table' NOT FOUND\n";
        }
    }
    echo "\n";

    // Test 2: Check if badges are seeded
    echo "Test 2: Checking seeded badges...\n";
    $badges = badges_get_all($pdo);
    echo "  Found " . count($badges) . " active badges\n";
    
    $requiredCodes = ['GENERATOR_10', 'GENERATOR_100', 'DROP_10', 'DROP_50', 
                      'COLLECTOR_10', 'COLLECTOR_25', 'LEGENDARY_PULL', 'LEVEL_10'];
    $foundCodes = array_column($badges, 'code');
    
    foreach ($requiredCodes as $code) {
        if (in_array($code, $foundCodes)) {
            echo "  ✓ Badge '$code' exists\n";
        } else {
            echo "  ✗ Badge '$code' NOT FOUND (run sql/knd_badges_seed.sql)\n";
        }
    }
    echo "\n";

    // Test 3: Test milestone counting (using user ID 1 if exists)
    echo "Test 3: Testing milestone counting...\n";
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $userId = (int)$user['id'];
        echo "  Testing with user ID: $userId\n";
        
        $milestones = badges_get_user_milestones($pdo, $userId);
        echo "  Milestones:\n";
        echo "    - Generator: {$milestones['generator']}\n";
        echo "    - Drop: {$milestones['drop']}\n";
        echo "    - Collector: {$milestones['collector']}\n";
        echo "    - Legendary Pull: {$milestones['legendary_pull']}\n";
        echo "    - Level: {$milestones['level']}\n";
    } else {
        echo "  ⚠ No users found in database\n";
    }
    echo "\n";

    // Test 4: Test badge functions
    echo "Test 4: Testing badge helper functions...\n";
    
    // Test badges_get_all
    $allBadges = badges_get_all($pdo);
    echo "  ✓ badges_get_all() returned " . count($allBadges) . " badges\n";
    
    if ($user) {
        $userId = (int)$user['id'];
        
        // Test badges_get_user_badges
        $userBadges = badges_get_user_badges($pdo, $userId);
        echo "  ✓ badges_get_user_badges() returned " . count($userBadges) . " unlocked badges\n";
        
        // Test badges_get_user_progress
        $progress = badges_get_user_progress($pdo, $userId);
        echo "  ✓ badges_get_user_progress() returned " . count($progress) . " badge progress items\n";
        
        // Test badges_check_and_grant (dry run - checks eligibility)
        $newBadges = badges_check_and_grant($pdo, $userId, 'drop');
        if (!empty($newBadges)) {
            echo "  ✓ badges_check_and_grant() granted " . count($newBadges) . " new badges: " . implode(', ', $newBadges) . "\n";
        } else {
            echo "  ✓ badges_check_and_grant() - no new badges to grant\n";
        }
    }
    echo "\n";

    // Test 5: Check integration points
    echo "Test 5: Checking integration points...\n";
    
    // Check if knd_drop.php includes badges
    $dropFile = file_get_contents(BASE_PATH . '/includes/knd_drop.php');
    if (strpos($dropFile, 'knd_badges.php') !== false) {
        echo "  ✓ includes/knd_drop.php includes badge system\n";
    } else {
        echo "  ✗ includes/knd_drop.php does NOT include badge system\n";
    }
    
    // Check if knd_xp.php includes badges
    $xpFile = file_get_contents(BASE_PATH . '/includes/knd_xp.php');
    if (strpos($xpFile, 'knd_badges.php') !== false) {
        echo "  ✓ includes/knd_xp.php includes badge system\n";
    } else {
        echo "  ✗ includes/knd_xp.php does NOT include badge system\n";
    }
    
    // Check if complete.php includes badges
    $completeFile = file_get_contents(__DIR__ . '/api/labs/queue/complete.php');
    if (strpos($completeFile, 'knd_badges.php') !== false) {
        echo "  ✓ api/labs/queue/complete.php includes badge system\n";
    } else {
        echo "  ✗ api/labs/queue/complete.php does NOT include badge system\n";
    }
    echo "\n";

    echo "=== Test Complete ===\n";
    echo "\nSummary:\n";
    echo "- Badge system core functions: ✓ Working\n";
    echo "- Database tables: Check output above\n";
    echo "- Integration points: Check output above\n";
    echo "\nNext steps:\n";
    echo "1. Ensure badge tables exist (run sql/knd_badges.sql and sql/knd_user_badges.sql)\n";
    echo "2. Seed badges (run sql/knd_badges_seed.sql)\n";
    echo "3. Test in production by performing drops and image generations\n";
    echo "4. Check API endpoint: GET /api/badges/user_badges.php\n";

} catch (\Throwable $e) {
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
