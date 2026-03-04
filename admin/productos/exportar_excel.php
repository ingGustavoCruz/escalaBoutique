<?php
session_start();
require_once '../../api/conexion.php';

if (!isset($_SESSION['admin_id'])) {
    exit("Acceso denegado");
}

// Nombre del archivo
$filename = "Inventario_Escala_" . date('Ymd') . ".csv";

// Cabeceras para forzar la descarga de un CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '";');

// Abrir el flujo de salida
$f = fopen('php://output', 'w');

// BOM para que Excel detecte correctamente los acentos (UTF-8)
fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));

// Definir los encabezados de las columnas
$columnas = ['ID', 'PRODUCTO', 'ACTIVO', 'DESCRIPCIÓN_C', 'DESCRIPCIÓN_L', 'CATEGORIA', 'PRECIO', 'PRECIO_ANT', 'STOCK', 'TALLAS', 'SEL_INCORPORADA', 'OFERTA', 'TOP', 'CALIFICACIÓN','CREACIÓN'];
fputcsv($f, $columnas);

// Consulta de productos
$query = "SELECT * FROM productos ORDER BY id DESC";
$res = $conn->query($query);

while ($row = $res->fetch_assoc()) {
    // Limpiamos los datos para evitar errores en el CSV
    $linea = [
        $row['id'],
        $row['nombre'],
        $row['activo'] ? 'Sí' : 'No',
        $row['descripcion_corta'] ?? '',
        $row['descripcion_larga'] ?? '',
        $row['categoria'] ?? 'General',
        number_format($row['precio'], 2, '.', ''), // Sin comas de miles para no romper el CSV
        number_format($row['precio_anterior'], 2, '.', ''), // Sin comas de miles para no romper el CSV
        $row['stock'],
        $row['tallas'],
        $row['sel_inc'] ? 'Sí' : 'No',
        $row['en_oferta'] ? 'Sí' : 'No',
        $row['es_top'] ? 'Sí' : 'No',
        $row['calificacion'] ?? 0,
        $row['fecha_creacion'] ?? ''
    ];
    fputcsv($f, $linea);
}

fclose($f);
exit;