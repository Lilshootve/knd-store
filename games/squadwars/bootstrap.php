<?php
declare(strict_types=1);

/**
 * Carga del módulo SquadWars (motor simultáneo). No incluye legacy mw_squad.
 */
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
}

require_once BASE_PATH . '/shared/combat/domain/SkillRegistry.php';
require_once BASE_PATH . '/shared/combat/rules/CombatCaps.php';
require_once BASE_PATH . '/games/squadwars/contracts/SquadStateV1.php';
require_once BASE_PATH . '/games/squadwars/contracts/SubmitRoundPayloadV1.php';
require_once BASE_PATH . '/games/squadwars/contracts/SubmitRoundResultV1.php';
require_once BASE_PATH . '/games/squadwars/engine/TargetScope.php';
require_once BASE_PATH . '/games/squadwars/engine/InitiativeTimeline.php';
require_once BASE_PATH . '/games/squadwars/engine/SkillExecutor.php';
require_once BASE_PATH . '/games/squadwars/engine/SquadTargetResolver.php';
require_once BASE_PATH . '/games/squadwars/engine/SquadEffectResolver.php';
require_once BASE_PATH . '/games/squadwars/engine/SquadActionResolver.php';
require_once BASE_PATH . '/games/squadwars/engine/SquadSimEngine.php';
require_once BASE_PATH . '/games/squadwars/application/SubmitRoundService.php';

/**
 * Factory por defecto (sin PDO skills todavía).
 */
function knd_squadwars_create_submit_service(?PDO $pdo = null): SubmitRoundService
{
    $registry = new KndSquadSkillRegistry($pdo);
    $skillExec = new SquadSkillExecutor();
    $resolver = new SquadActionResolver($skillExec, $registry);
    $engine = new SquadSimEngine(
        new InitiativeTimeline(),
        new SquadTargetResolver(),
        $resolver,
        new SquadEffectResolver(false),
        $registry,
    );
    return new SubmitRoundService($engine);
}
