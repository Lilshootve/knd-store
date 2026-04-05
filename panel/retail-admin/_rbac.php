<?php
/**
 * KND Retail Admin — RBAC
 * Permisos por rol (admin / cashier).
 */

const RETAIL_ROLE_PERMS = [
    'admin'   => ['*'],
    'cashier' => [
        'sales.create',
        'sales.view',
        'products.view',
        'customers.view',
    ],
];

function retail_has_perm(string $perm): bool
{
    $role  = retail_role();
    $perms = RETAIL_ROLE_PERMS[$role] ?? [];
    return in_array('*', $perms, true) || in_array($perm, $perms, true);
}

function retail_require_perm(string $perm): void
{
    if (!retail_has_perm($perm)) {
        http_response_code(403);
        die('<p style="font-family:sans-serif;color:red">Permiso denegado: ' . htmlspecialchars($perm) . '</p>');
    }
}
