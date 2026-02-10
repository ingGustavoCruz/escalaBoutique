<?php
/**
 * api/logger.php - Sistema de Registro de Actividad
 */
if (session_status() === PHP_SESSION_NONE) session_start();

function registrarBitacora($modulo, $accion, $detalle, $conn) {
    // 1. Detectar quién es el usuario
    $usuario = "Invitado";
    $rol = "Anonimo";

    if (isset($_SESSION['admin_nombre'])) {
        $usuario = $_SESSION['admin_nombre'];
        $rol = "Super Admin";
    } elseif (isset($_SESSION['usuario_empleado']['nombre'])) {
        $usuario = $_SESSION['usuario_empleado']['nombre'];
        $rol = "Empleado";
    } elseif (isset($_SESSION['sistema_auto'])) {
        $usuario = "SISTEMA";
        $rol = "Bot";
    }

    // 2. Obtener IP
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';

    // 3. Insertar en BD
    try {
        $stmt = $conn->prepare("INSERT INTO bitacora (usuario, rol, modulo, accion, detalle, ip, fecha) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssss", $usuario, $rol, $modulo, $accion, $detalle, $ip);
        $stmt->execute();
        $stmt->close();
    } catch (Exception $e) {
        // Silencioso: Si falla el log, no queremos romper el sistema principal
        error_log("Error Bitacora: " . $e->getMessage());
    }
}
?>