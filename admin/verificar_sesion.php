<?php
/**
 * admin/verificar_sesion.php - Blindaje de Sesión Estricto
 * Acción: Cierre automático por inactividad (20 min)
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Configuración de tiempo: 20 minutos en segundos (20 * 60)
$tiempo_max_inactividad = 1200; 

// 2. Si no hay sesión, rebote inmediato al login
if (!isset($_SESSION['admin_id'])) {
    header("Location: /admin/index.php");
    exit;
}

// 3. Lógica de Inactividad
if (isset($_SESSION['ultima_actividad'])) {
    $segundos_inactivo = time() - $_SESSION['ultima_actividad'];
    
    if ($segundos_inactivo > $tiempo_max_inactividad) {
        // Opcional: Registrar el evento en bitácora antes de destruir
        // require_once __DIR__ . '/../api/logger.php';
        // registrarBitacora('SEGURIDAD', 'SESIÓN EXPIRADA', "Admin ID " . $_SESSION['admin_id'] . " desconectado por inactividad.", $conn);
        
        session_unset();
        session_destroy();
        header("Location: /admin/index.php?error=timeout");
        exit;
    }
}

// 4. Actualizamos la marca de tiempo en cada interacción con el servidor
$_SESSION['ultima_actividad'] = time();
?>