<?php
/**
 * api/validar_cupon.php
 */
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

// Recibir JSON
$input = json_decode(file_get_contents('php://input'), true);
$codigo = $conn->real_escape_string(strtoupper(trim($input['codigo'] ?? '')));

if (empty($codigo)) {
    echo json_encode(['status' => 'error', 'message' => 'Escribe un código.']);
    exit;
}

// Buscar cupón activo
$sql = "SELECT * FROM cupones WHERE codigo = '$codigo' AND estado = 'activo' LIMIT 1";
$res = $conn->query($sql);

if ($res->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Cupón no válido o inactivo.']);
    exit;
}

$cupon = $res->fetch_assoc();

// Validaciones Extra
$hoy = date('Y-m-d');

// 1. Fecha Vencimiento
if (!empty($cupon['fecha_vencimiento']) && $cupon['fecha_vencimiento'] < $hoy) {
    echo json_encode(['status' => 'error', 'message' => 'Este cupón ya venció.']);
    exit;
}

// 2. Límite de Usos
if ($cupon['limite_usos'] > 0 && $cupon['usos_actuales'] >= $cupon['limite_usos']) {
    echo json_encode(['status' => 'error', 'message' => 'Este cupón se ha agotado.']);
    exit;
}

// ¡CUPÓN VÁLIDO! Lo guardamos en sesión
$_SESSION['cupon_activo'] = [
    'id' => $cupon['id'],
    'codigo' => $cupon['codigo'],
    'tipo' => $cupon['tipo_descuento'], // 'porcentaje' o 'fijo'
    'valor' => (float)$cupon['valor']
];

echo json_encode([
    'status' => 'success', 
    'message' => '¡Cupón aplicado! Precios actualizados.',
    'descuento' => $cupon['valor'] . ($cupon['tipo_descuento'] == 'porcentaje' ? '%' : ' MXN')
]);
?>