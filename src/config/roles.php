<?php
require_once __DIR__ . '/errorlogs.php';

use App\config\errorlogs;
errorlogs::activa_error_logs();

define('ROLE_SUPER_ADMIN', 1);
define('ROLE_USER_BASIC', 2);
define('SUPPORT_USER_ID', 25);

define('PERM_CREATE', 'create');
define('PERM_DELETE', 'delete');
define('PERM_UPDATE', 'update');
define('PERM_READ', 'read');

const MODULE_OBJECT_MAP = [
    'usuarios' => 1,
    'dashboard' => 2,
    'clientes' => 9,
    'mantenimiento_clientes' => 3,
    'suscripciones' => 6,
    'gestion_suscripciones' => 4,
    'notificaciones' => 7,
    'calendario' => 8,
    'pagos' => 10,
    'config' => 5,
    'backup' => 11,
];

const PERMISSION_COLUMN_MAP = [
    PERM_CREATE => 'permiso_insercion',
    PERM_DELETE => 'permiso_eliminacion',
    PERM_UPDATE => 'permiso_actualizacion',
    PERM_READ => 'permiso_consultar',
];

// Permisos mínimos garantizados cuando no existen (o están negados) en la BD para roles específicos.
const ROLE_FALLBACK_PERMISSIONS = [
    ROLE_USER_BASIC => [
        'clientes' => [
            PERM_READ => true,
            PERM_CREATE => true,
            PERM_UPDATE => true,
        ],
        'suscripciones' => [
            PERM_READ => true,
            PERM_CREATE => true,
            PERM_UPDATE => true,
        ],
        'pagos' => [
            PERM_READ => true,
            PERM_CREATE => true,
        ],
    ],
];

function roles_db_connection(): ?mysqli
{
    global $conexion;
    if (isset($conexion) && $conexion instanceof mysqli) {
        if (@$conexion->ping()) {
            return $conexion;
        }
    }

    static $internalConnection = null;
    if ($internalConnection instanceof mysqli) {
        if (@$internalConnection->ping()) {
            return $internalConnection;
        }
    }

    $internalConnection = @new mysqli('localhost', 'root', '', 'gestion_suscripciones');
    if ($internalConnection->connect_errno !== 0) {
        return null;
    }
    $internalConnection->set_charset('utf8');
    return $internalConnection;
}

function role_is_admin(int $roleId): bool
{
    return $roleId === ROLE_SUPER_ADMIN;
}

function user_has_permission(string $moduleKey, string $permission): bool
{
    $roleId = intval($_SESSION['rol_id'] ?? 0);
    if ($roleId === ROLE_SUPER_ADMIN) {
        return true;
    }
    if ($roleId <= 0) {
        return false;
    }

    $objectId = MODULE_OBJECT_MAP[$moduleKey] ?? null;
    if ($objectId === null) {
        return false;
    }

    $column = PERMISSION_COLUMN_MAP[$permission] ?? null;
    if ($column === null) {
        return false;
    }

    static $cache = [];
    $cacheKey = $roleId . ':' . $objectId;
    if (!array_key_exists($cacheKey, $cache)) {
        $conn = roles_db_connection();
        if (!$conn) {
            $cache[$cacheKey] = null;
        } else {
            $stmt = $conn->prepare('SELECT permiso_insercion, permiso_eliminacion, permiso_actualizacion, permiso_consultar FROM tbl_permisos WHERE tbl_ms_roles_id_rol = ? AND tbl_objetos_id_objeto = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ii', $roleId, $objectId);
                $stmt->execute();
                $res = $stmt->get_result();
                $cache[$cacheKey] = $res ? $res->fetch_assoc() : null;
                $stmt->close();
            } else {
                $cache[$cacheKey] = null;
            }
        }
    }

    $fallbackAllowed = !empty(ROLE_FALLBACK_PERMISSIONS[$roleId][$moduleKey][$permission]);

    if (!is_array($cache[$cacheKey])) {
        return $fallbackAllowed;
    }

    if (($cache[$cacheKey][$column] ?? 'N') === 'S') {
        return true;
    }

    return $fallbackAllowed;
}

function require_permission(string $moduleKey, string $permission): void
{
    if (!user_has_permission($moduleKey, $permission)) {
        header('Location: ../../public/index.php?error=No tiene permisos');
        exit;
    }
}
