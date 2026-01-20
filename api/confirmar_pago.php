<?php
/**
 * API DE ALTA SEGURIDAD PARA CONFIRMACIÓN DE PEDIDOS
 */
header('Content-Type: application/json');
require_once 'conexion.php';

// 1. Recibir los datos del frontend
$json = file_get_contents('php://input');
$datos = json_decode($json, true);

if (!$datos) {
    echo json_encode(['status' => 'error', 'message' => 'Sin datos válidos']);
    exit;
}

$orderID = $datos['orderID'];
$cart = $datos['cart'];
$total = $datos['total'];

// Iniciar transacción SQL para asegurar que si algo falla, no se guarde nada a medias
$conn->begin_transaction();

try {
    // 2. Insertar en la tabla pedidos (Uso de ruta absoluta para seguridad)
    $stmt = $conn->prepare("INSERT INTO kaiexper_perpetualife.pedidos (paypal_order_id, total, moneda, estado) VALUES (?, ?, 'USD', 'COMPLETADO')");
    $stmt->bind_param("sd", $orderID, $total);
    $stmt->execute();
    $pedido_id = $conn->insert_id;

    // 3. Insertar detalles y actualizar stock
    $stmt_detalle = $conn->prepare("INSERT INTO kaiexper_perpetualife.detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $stmt_stock = $conn->prepare("UPDATE kaiexper_perpetualife.productos SET stock = stock - ? WHERE id = ?");

    foreach ($cart as $item) {
        $p_id = $item['id'];
        $qty = $item['qty'];
        $price = $item['precio'];

        // Guardar detalle
        $stmt_detalle->bind_param("iiid", $pedido_id, $p_id, $qty, $price);
        $stmt_detalle->execute();

        // Descontar stock
        $stmt_stock->bind_param("ii", $qty, $p_id);
        $stmt_stock->execute();
    }

    // Si todo salió bien, confirmar cambios
    $conn->commit();
    echo json_encode(['status' => 'success', 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    // Si algo falla, deshacer todo (Rollback)
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}