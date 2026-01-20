<?php
// Configuración de la base de datos - cambia estos datos según tu entorno
$host = "localhost:3306";
$usuario = "root";
$contrasena = "";
$basededatos = "kaiexper_perpetualife";


// Crear conexión
$conn = new mysqli($host, $usuario, $contrasena, $basededatos);

// Verificar conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Establecer conjunto de caracteres a utf8mb4 para soporte completo de caracteres
$conn->set_charset("utf8mb4");
