<?php
declare(strict_types=1);
/**
 * EffectGuard.php - Capa de control de efectos para el sistema de combate.
 * Métodos estáticos para validar y limitar efectos antes de aplicarlos.
 * Desacoplado: no llama a mw_*; el caller integra donde corresponda.
 */

require_once __DIR__ . '/BattleRules.php';

class EffectGuard
{
    /** @var array Log de debug (últimos mensajes) */
    private static array $logBuffer = [];

    /**
     * Registra mensaje de debug.
     * Escribe en error_log y mantiene buffer interno (últimos 100).
     *
     * @param string $message Mensaje a registrar
     */
    public static function log(string $message): void
    {
        self::$logBuffer[] = $message;
        if (count(self::$logBuffer) > 100) {
            array_shift(self::$logBuffer);
        }
        error_log('[EffectGuard] ' . $message);
    }

    /**
     * Obtiene el buffer de log (para debug).
     *
     * @return array Últimos mensajes registrados
     */
    public static function getLogBuffer(): array
    {
        return self::$logBuffer;
    }

    /**
     * Obtiene la categoría efectiva de un efecto (compatibilidad con type/category).
     *
     * @param array $entry Datos del efecto
     * @param string $effectName Nombre del efecto
     * @return string|null buff|debuff|dot|cc
     */
    private static function getEffectCategory(array $entry, string $effectName): ?string
    {
        $category = (string) ($entry['category'] ?? '');
        if (in_array($category, ['buff', 'debuff', 'dot', 'cc'], true)) {
            return $category;
        }
        $type = (string) ($entry['type'] ?? '');
        if ($type === 'crowd_control' || in_array($effectName, BATTLE_CC_HARD, true)) {
            return 'cc';
        }
        if ($type === 'buff') {
            return 'buff';
        }
        if (in_array($effectName, BATTLE_DOT_EFFECTS, true)) {
            return 'dot';
        }
        if ($type === 'debuff') {
            return 'debuff';
        }
        return null;
    }

    /**
     * Limita stacks de un efecto a MAX_EFFECT_STACKS.
     * No modifica efectos que no usen stacks.
     *
     * Problema de balance: Evita acumulación infinita de stacks.
     * Cuándo se ejecuta: Antes de aplicar/actualizar un efecto con stacks.
     * Afecta: Efectos con clave 'stacks' en el payload.
     *
     * @param array $effect Payload del efecto (por referencia)
     */
    public static function capStacks(array &$effect): void
    {
        if (!isset($effect['stacks']) || !is_numeric($effect['stacks'])) {
            return;
        }
        $effect['stacks'] = min((int) $effect['stacks'], BATTLE_MAX_EFFECT_STACKS);
    }

    /**
     * Prepara el target para recibir un nuevo debuff.
     * Si ya tiene MAX_DEBUFFS, elimina el más antiguo para hacer hueco.
     *
     * Problema de balance: Evitar saturación total; permite rotación de debuffs.
     * Cuándo se ejecuta: Antes de mw_apply_effect cuando el payload es debuff.
     * Afecta: Solo efectos con category === 'debuff' (NO dot, NO cc).
     *
     * @param array $target Fighter array con effects, states
     * @return bool Siempre true; nunca bloquea, solo hace hueco si es necesario
     */
    public static function canApplyDebuff(array &$target): bool
    {
        if (!isset($target['effects']) || !is_array($target['effects'])) {
            $target['effects'] = [];
            return true;
        }

        $debuffs = [];
        foreach ($target['effects'] as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $category = self::getEffectCategory($entry, $name);
            if ($category !== 'debuff') {
                continue;
            }
            $duration = (int) ($entry['duration'] ?? 0);
            if ($duration > 0) {
                $debuffs[$name] = (int) ($entry['applied_turn'] ?? 0);
            }
        }

        if (count($debuffs) < BATTLE_MAX_DEBUFFS) {
            return true;
        }

        $oldest = null;
        $oldestTurn = PHP_INT_MAX;
        foreach ($debuffs as $name => $turn) {
            if ($turn < $oldestTurn) {
                $oldestTurn = $turn;
                $oldest = $name;
            }
        }

        if ($oldest !== null) {
            self::log("canApplyDebuff: removed oldest debuff '{$oldest}' (turn {$oldestTurn}) to make room");
            unset($target['effects'][$oldest]);
            if (isset($target['states'][$oldest])) {
                unset($target['states'][$oldest]);
            }
        }

        return true;
    }

    /**
     * Limita la duración de efectos tipo sleep a 1 turno.
     *
     * Problema de balance: Evitar cadenas de sleep largas.
     * Cuándo se ejecuta: Antes de aplicar efecto sleep.
     * Afecta: Efectos de tipo sleep.
     *
     * @param int $duration Duración solicitada en turnos
     * @return int Duración limitada (máx BATTLE_MAX_SLEEP_DURATION)
     */
    public static function applySleepLimit(int $duration): int
    {
        return min((int) $duration, BATTLE_MAX_SLEEP_DURATION);
    }

    /**
     * Reduce la probabilidad de aplicar CC según resistencia y chain CC.
     * Solo aplica para CC_HARD (stun, freeze, petrify, sleep).
     *
     * Problema de balance: Evitar control infinito; penalizar chain CC.
     * Cuándo se ejecuta: Antes del roll de probabilidad para CC_HARD.
     * Afecta: CC_HARD únicamente; CC_SOFT no se modifica.
     *
     * Fórmula: baseChance * (1 - (cc_resistance * 0.7)); si recibió CC turno anterior: -15%.
     *
     * @param array $target Fighter con cc_resistance, last_cc_turn opcionales
     * @param float $baseChance Probabilidad base 0-100
     * @param string $ccType Tipo de CC (stun, freeze, petrify, sleep, slow, weaken, focus_down)
     * @param int|null $currentTurn Turno actual (para chain CC penalty)
     * @return float Probabilidad ajustada 0-100
     */
    public static function applySoftResistance(array $target, float $baseChance, string $ccType, ?int $currentTurn = null): float
    {
        if (!in_array($ccType, BATTLE_CC_HARD, true)) {
            return max(0.0, min(100.0, (float) $baseChance));
        }

        $res = (float) ($target['cc_resistance'] ?? 0);
        $effectiveRes = $res * BATTLE_SOFT_RESISTANCE_FACTOR;
        $chance = $baseChance * (1.0 - $effectiveRes);

        if ($currentTurn !== null && isset($target['last_cc_turn'])) {
            $lastCc = (int) $target['last_cc_turn'];
            if ($lastCc === $currentTurn - 1) {
                $chance *= (1.0 - BATTLE_CHAIN_CC_PENALTY);
            }
        }

        return max(0.0, min(100.0, $chance));
    }

    /**
     * Limita bonus escalables (daño, futuro: heal, defense).
     *
     * Problema de balance: Evitar snowball excesivo de daño sin matar builds ofensivos.
     * Cuándo se ejecuta: Al acumular bonus de daño antes del cálculo final.
     * Afecta: Tipo 'damage' (cap 0.5); heal/defense futuros.
     *
     * @param float $value Valor a limitar
     * @param string $type Tipo: 'damage' (cap 0.5), 'heal'|'defense' (futuro, por ahora sin cap)
     * @return float Valor limitado
     */
    public static function applyScalingCap(float $value, string $type): float
    {
        if ($type === 'damage') {
            return min($value, BATTLE_MAX_DAMAGE_BONUS);
        }
        // Futuro: 'heal', 'defense' con constantes propias
        return $value;
    }

    /**
     * Incrementa la resistencia a CC cuando el target recibe control duro.
     * Guarda last_cc_turn para penalización de chain CC.
     * Solo aplica para CC_HARD.
     *
     * Problema de balance: Counterplay progresivo; evita control infinito en PvP.
     * Cuándo se ejecuta: Tras aplicar con éxito un CC de tipo CC_HARD.
     * Afecta: CC_HARD únicamente.
     *
     * @param array $target Fighter (por referencia)
     * @param string $ccType Tipo de CC aplicado
     * @param int|null $currentTurn Turno actual (para last_cc_turn)
     */
    public static function onApplyCC(array &$target, string $ccType, ?int $currentTurn = null): void
    {
        if (!in_array($ccType, BATTLE_CC_HARD, true)) {
            return;
        }

        $current = (float) ($target['cc_resistance'] ?? 0);
        $target['cc_resistance'] = min(
            BATTLE_MAX_CC_RESISTANCE,
            $current + BATTLE_CC_RESISTANCE_STEP
        );

        if ($currentTurn !== null) {
            $target['last_cc_turn'] = $currentTurn;
        }

        self::log("onApplyCC: {$ccType} applied, cc_resistance now " . $target['cc_resistance']);
    }

    /**
     * Reduce la resistencia a CC al pasar el turno.
     *
     * Problema de balance: La resistencia no es permanente; permite ventanas de ventaja.
     * Cuándo se ejecuta: En la fase de fin de turno del fighter.
     * Afecta: cc_resistance del target.
     *
     * @param array $target Fighter (por referencia)
     */
    public static function onTurnEnd(array &$target): void
    {
        $current = (float) ($target['cc_resistance'] ?? 0);
        $target['cc_resistance'] = max(0.0, $current - 0.1);
    }

    /**
     * Elimina solo efectos con category === 'debuff'.
     * No toca dot, cc ni buffs.
     *
     * Problema de balance: Counterplay parcial; cleanse básico.
     * Cuándo se ejecuta: Habilidades de cleanse básico.
     * Afecta: Solo category debuff.
     *
     * @param array $target Fighter (por referencia)
     */
    public static function cleanseBasic(array &$target): void
    {
        if (!isset($target['effects']) || !is_array($target['effects'])) {
            return;
        }

        $toRemove = [];
        foreach ($target['effects'] as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if (self::getEffectCategory($entry, $name) === 'debuff') {
                $toRemove[] = $name;
            }
        }

        foreach ($toRemove as $name) {
            unset($target['effects'][$name]);
            if (isset($target['states'][$name])) {
                unset($target['states'][$name]);
            }
        }

        if (!empty($toRemove)) {
            self::log('cleanseBasic: removed ' . implode(', ', $toRemove));
        }
    }

    /**
     * Elimina debuffs + cc. NO elimina dot ni buffs.
     *
     * Problema de balance: Counterplay fuerte; permite recuperación total.
     * Cuándo se ejecuta: Habilidades de cleanse completo / full dispel.
     * Afecta: category debuff y cc; preserva dot y buffs.
     *
     * @param array $target Fighter (por referencia)
     */
    public static function cleanseFull(array &$target): void
    {
        if (!isset($target['effects']) || !is_array($target['effects'])) {
            return;
        }

        $toRemove = [];
        foreach ($target['effects'] as $name => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $category = self::getEffectCategory($entry, $name);
            if ($category === 'debuff' || $category === 'cc') {
                $toRemove[] = $name;
            }
        }

        foreach ($toRemove as $name) {
            unset($target['effects'][$name]);
            if (isset($target['states'][$name])) {
                unset($target['states'][$name]);
            }
        }

        if (isset($target['states']) && is_array($target['states'])) {
            foreach (BATTLE_NEGATIVE_STATES as $state) {
                if (isset($target['states'][$state])) {
                    unset($target['states'][$state]);
                }
            }
        }

        if (!empty($toRemove)) {
            self::log('cleanseFull: removed effects ' . implode(', ', $toRemove) . ' + negative states');
        }
    }
}
