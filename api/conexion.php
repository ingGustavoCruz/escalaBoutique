<?php
// api/conexion.php - EscalaBoutique
$host = 'localhost';
$user = 'root';
$pass = ''; // Tu pass local
$db   = 'kaiexper_escalaboutique'; // <--- NUEVA BD

$conn = new mysqli($host, $user, $pass, $db);
$conn->set_charset("utf8mb4");

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
?>