<?php
// 1. Configuración de la Base de Datos
$host = '127.0.0.1';
$user = 'root';
$pass = ''; 
$db   = 'kaiexper_escalaboutique'; 

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

/**
 * Función global para obtener ajustes de la tabla 'configuracion'
 */
function get_config($clave, $conn) {
    $stmt = $conn->prepare("SELECT valor FROM configuracion WHERE clave = ? LIMIT 1");
    $stmt->bind_param("s", $clave);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $fila = $resultado->fetch_assoc(); // Usar fetch_assoc es ligeramente más rápido que object
    $stmt->close(); // ¡Importante cerrar el stmt!
    
    return $fila['valor'] ?? null;
}
?>