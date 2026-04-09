<?php
/**
 * ¿Puede este usuario de juego (users.id) usar Nexus World Builder?
 *
 * Orden de comprobación:
 * 1) admin_users.linked_game_user_id = $uid (vínculo explícito panel ↔ juego)
 * 2) admin_users.username = users.username (compatibilidad previa)
 * 3) users.role legado admin/superadmin/mod (si existe la columna)
 */
function nexus_user_can_world_builder(PDO $pdo, ?int $uid): bool
{
    if (!$uid) {
        return false;
    }

    $builderRoles = ['owner', 'manager', 'support'];

    // 1) Vínculo explícito a cuenta de juego
    try {
        $s = $pdo->prepare("
            SELECT role
            FROM admin_users
            WHERE linked_game_user_id = ?
              AND active = 1
            LIMIT 1
        ");
        $s->execute([$uid]);
        $role = $s->fetchColumn();
        if (in_array($role, $builderRoles, true)) {
            return true;
        }
    } catch (PDOException $_) {
        // Tabla/columna aún no migrada: seguir con otros criterios
    }

    // 2) Mismo username en admin_users y users
    try {
        $u = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
        $u->execute([$uid]);
        $username = $u->fetchColumn();
        if ($username) {
            $a = $pdo->prepare("
                SELECT role
                FROM admin_users
                WHERE username = ?
                  AND active = 1
                LIMIT 1
            ");
            $a->execute([$username]);
            $adminRole = $a->fetchColumn();
            if (in_array($adminRole, $builderRoles, true)) {
                return true;
            }
        }
    } catch (PDOException $_) {
        // noop
    }

    // 3) users.role (legado; puede no existir en tu esquema)
    try {
        $legacy = $pdo->prepare('SELECT role FROM users WHERE id = ? LIMIT 1');
        $legacy->execute([$uid]);
        $role = $legacy->fetchColumn();
        return in_array($role, ['admin', 'superadmin', 'mod'], true);
    } catch (PDOException $_) {
        return false;
    }
}
