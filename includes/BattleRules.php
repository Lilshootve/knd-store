<?php
declare(strict_types=1);
/**
 * BattleRules.php - Reglas configurables para el sistema de efectos.
 * Sin dependencias. Solo constantes.
 *
 * Objetivos de balance: Evitar control infinito, limitar snowball sin matar daño,
 * mantener utilidad de controllers, permitir counterplay real, preparar PvP sin romper PvE.
 */

// Límites de efectos
const BATTLE_MAX_DEBUFFS = 3;
const BATTLE_MAX_BUFFS = 3;
const BATTLE_MAX_EFFECT_STACKS = 3;
const BATTLE_MAX_DAMAGE_BONUS = 0.5;
const BATTLE_MAX_CC_RESISTANCE = 0.5;
const BATTLE_MAX_SLEEP_DURATION = 1;

// CC Resistance: incremento por CC aplicado, cap máximo
const BATTLE_CC_RESISTANCE_STEP = 0.15;

// Soft resistance: factor que suaviza el impacto de cc_resistance (0.7 = menos agresivo)
const BATTLE_SOFT_RESISTANCE_FACTOR = 0.7;

// Clasificación de CC - SOLO CC_HARD activa onApplyCC() y applySoftResistance()
const BATTLE_CC_HARD = ['stun', 'freeze', 'petrify', 'sleep'];
const BATTLE_CC_SOFT = ['slow', 'weaken', 'focus_down'];

// Estados negativos para cleanseFull (debuffs + CC asociados)
const BATTLE_NEGATIVE_STATES = ['stun', 'freeze', 'petrify', 'chill', 'focus_down', 'shock', 'cursed_presence'];

// Efectos DoT (no cuentan como debuff para límite; no se eliminan en cleanseBasic)
const BATTLE_DOT_EFFECTS = ['shock', 'bleed', 'burn', 'poison'];

// Penalización extra cuando el target recibió CC en el turno anterior (chain CC)
const BATTLE_CHAIN_CC_PENALTY = 0.15;

// Categorías de efectos: buff | debuff | dot | cc
// Usar $effect['category'] para clasificación; si no existe, se infiere de type/name
