<?php
/**
 * api/obtener_stock.php
 * Devuelve un JSON ligero solo con ID y STOCK actualizados.
 */
require_once 'conexion.php';
header('Content-Type: application/json');

// Consultamos solo ID y Stock para ser ultra rápidos
$res = $conn->query("SELECT id, stock FROM productos");
$data = [];

while ($row = $res->fetch_assoc()) {
    $data[] = [
        'id' => (int)$row['id'],
        'stock' => (int)$row['stock']
    ];
}

echo json_encode($data);
$conn->close();
?>