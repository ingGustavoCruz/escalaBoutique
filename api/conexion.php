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

// ... al final de api/conexion.php ...

// Generar Token CSRF si no existe en la sesión
if (session_status() === PHP_SESSION_ACTIVE && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Función para validar el token en cada petición POST
 */
function validar_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Error de seguridad: Token CSRF inválido.']);
            exit;
        }
    }
}
?>