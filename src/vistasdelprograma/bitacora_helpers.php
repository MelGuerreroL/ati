<?php
/**
 * Registra un evento en la bitácora del sistema.
 *
 * @param mysqli $conexion La conexión a la base de datos.
 * @param int|null $id_usuario El ID del usuario que realiza la acción. Puede ser null si la acción no está asociada a un usuario logueado (ej. intento de login fallido).
 * @param int $id_objetos El ID del objeto/módulo afectado (según tbl_objetos).
 * @param string $accion La acción realizada (LOGIN, INSERT, UPDATE, DELETE, LOGOUT, etc.).
 * @param string $descripcion Una descripción detallada del evento.
 */
function registrar_bitacora($conexion, $id_usuario, $id_objetos, $accion, $descripcion) {
    // Asegurarse de que el ID de usuario sea un entero o nulo
    $usuario_id_log = !empty($id_usuario) ? intval($id_usuario) : null;

    $sql = "INSERT INTO tbl_ms_bitacora (fecha, id_usuario, id_objetos, accion, descripcion) 
            VALUES (NOW(), ?, ?, ?, ?)";
    
    $stmt = $conexion->prepare($sql);
    if ($stmt) {
        // Para id_usuario, usamos 'i' si es un entero, 's' si es null (enviando como string) no es ideal.
        // Lo mejor es manejarlo directamente. Aquí se asume que la columna id_usuario en la bitácora acepta NULL.
        $stmt->bind_param('iiss', $usuario_id_log, $id_objetos, $accion, $descripcion);
        $stmt->execute();
        $stmt->close();
    } else {
        // En un sistema de producción, esto debería registrarse en un archivo de log de errores.
        error_log("Error al preparar la consulta de bitácora: " . $conexion->error);
    }
}

/**
 * Traduce un código de acción de la bitácora al español para mostrar en las vistas.
 *
 * @param string $accion Código de la acción (por ejemplo: 'INSERT', 'UPDATE', 'DELETE', 'LOGIN_SUCCESS').
 * @return string Etiqueta en español. Si no existe traducción se devuelve el código original.
 */
function traducir_accion_bitacora($accion) {
    $map = [
        'INSERT' => 'Insertar',
        'UPDATE' => 'Actualizar',
        'DELETE' => 'Eliminar',
        'LOGIN_SUCCESS' => 'Inicio de sesión exitoso',
        'LOGIN_FAIL' => 'Error de inicio de sesión',
        'LOGIN_BLOCKED' => 'Inicio de sesión bloqueado',
        'LOGOUT' => 'Cierre de sesión',
        'USER_LOCKED' => 'Usuario bloqueado',
        'RESET_PASSWORD' => 'Restablecer contraseña',
        'CHANGE_PASSWORD' => 'Cambio de contraseña',
        'ENABLE_2FA' => 'Activar 2FA',
        'DISABLE_2FA' => 'Desactivar 2FA',
        'VERIFY' => 'Verificar',
        'REGISTER' => 'Registro',
        'CONFIRM' => 'Confirmación',
        // Añadir más mapeos según sea necesario
    ];

    $accion_up = strtoupper(trim($accion));
    return isset($map[$accion_up]) ? $map[$accion_up] : $accion;
}

/**
 * Asegura que la columna fecha_creacion exista en tbl_ms_usuario.
 *
 * @param mysqli $conexion Conexión activa a la base de datos.
 * @return bool True si la columna existe o se crea correctamente, false en caso de error.
 */
function ensure_fecha_creacion_column($conexion) {
    if (!$conexion) {
        return false;
    }

    $check = $conexion->query("SHOW COLUMNS FROM tbl_ms_usuario LIKE 'fecha_creacion'");
    if ($check) {
        if ($check->num_rows > 0) {
            $check->free();
            return true;
        }
        $check->free();
    }

    $sql = "ALTER TABLE tbl_ms_usuario ADD COLUMN fecha_creacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP";
    return $conexion->query($sql);
}
?>