<?php
/**
 * api/procesar_pedido_nomina.php
 * Procesa la compra interna: Resta stock, guarda pedido y notifica a áreas clave.
 */

require_once 'conexion.php';
require_once 'mailer.php'; // Asegúrate de tener tu archivo de configuración de correos

header('Content-Type: application/json');
session_start();

// 1. VALIDACIÓN DE SESIÓN
if (!isset($_SESSION['empleado_id_db'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Recarga la página.']);
    exit;
}

$empleado_id = $_SESSION['empleado_id_db'];
$empleado_nombre = $_SESSION['usuario_empleado']['nombre'];
$empleado_num = $_SESSION['usuario_empleado']['numero'];
$empleado_area = $_SESSION['usuario_empleado']['area'];
$empleado_email = $_SESSION['usuario_empleado']['email'];

// 2. RECIBIR DATOS DEL FRONTEND
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || empty($data['cart'])) {
    echo json_encode(['status' => 'error', 'message' => 'El carrito está vacío.']);
    exit;
}

$cart = $data['cart'];
$total = floatval($data['total']);
$plazos = intval($data['plazos']); // 1, 2 o 3 quincenas

// Iniciar transacción (Todo o nada)
$conn->begin_transaction();

try {
    // ---------------------------------------------------------
    // PASO A: VALIDAR Y RESTAR STOCK
    // ---------------------------------------------------------
    $stmtCheck = $conn->prepare("SELECT stock, nombre FROM productos WHERE id = ?");
    $stmtUpdate = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
    
    foreach ($cart as $item) {
        $id = $item['id'];
        $qty = $item['qty'];

        // Verificar stock actual
        $stmtCheck->bind_param("i", $id);
        $stmtCheck->execute();
        $res = $stmtCheck->get_result();
        $producto = $res->fetch_assoc();

        if (!$producto || $producto['stock'] < $qty) {
            throw new Exception("Stock insuficiente para: " . ($producto['nombre'] ?? 'Producto desconocido'));
        }

        // Restar stock
        $stmtUpdate->bind_param("ii", $qty, $id);
        $stmtUpdate->execute();
    }

    // ---------------------------------------------------------
    // PASO B: GUARDAR PEDIDO
    // ---------------------------------------------------------
    // Estado inicial: PENDIENTE (Hasta que tienda lo marque entregado o RH aprobado)
    $sqlPedido = "INSERT INTO pedidos (empleado_id, fecha, total, estado, metodo_pago, plazos) VALUES (?, NOW(), ?, 'PENDIENTE', 'NOMINA', ?)";
    $stmtPed = $conn->prepare($sqlPedido);
    $stmtPed->bind_param("idi", $empleado_id, $total, $plazos);
    
    if (!$stmtPed->execute()) {
        throw new Exception("Error al guardar el pedido.");
    }
    
    $pedido_id = $conn->insert_id;

    // ---------------------------------------------------------
    // PASO C: GUARDAR DETALLES
    // ---------------------------------------------------------
    $stmtDet = $conn->prepare("INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
    $htmlTablaProductos = "";

    foreach ($cart as $item) {
        $stmtDet->bind_param("iiid", $pedido_id, $item['id'], $item['qty'], $item['precio']);
        $stmtDet->execute();

        // Construir fila para el correo
        $subtotalItem = number_format($item['precio'] * $item['qty'], 2);
        $htmlTablaProductos .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd;'>{$item['nombre']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$item['qty']}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$$subtotalItem</td>
            </tr>";
    }

    // Confirmar transacción en BD
    $conn->commit();

    // ---------------------------------------------------------
    // PASO D: ENVIAR CORREOS
    // ---------------------------------------------------------
    
    // Configuración de Destinatarios (AJUSTA ESTOS CORREOS)
    $destinatarios = [
        ['email' => $empleado_email, 'nombre' => $empleado_nombre], // Al usuario
        ['email' => 'nominas@empresa.com', 'nombre' => 'Dirección de Nóminas'],
        ['email' => 'finanzas@empresa.com', 'nombre' => 'Dirección de Finanzas'],
        ['email' => 'tienda@empresa.com', 'nombre' => 'Encargado Escala Boutique']
    ];

    // Asunto del correo
    $asunto = "Nuevo Pedido Nómina #$pedido_id - $empleado_nombre";

    // Plantilla HTML del Correo (Diseño Corporativo Escala)
    $mensajeHTML = "
    <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0;'>
        <div style='background-color: #00524A; padding: 20px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 24px;'>ESCALA BOUTIQUE</h1>
            <p style='color: #a8d5d0; margin: 5px 0 0;'>Solicitud de Descuento por Nómina</p>
        </div>
        
        <div style='padding: 20px;'>
            <p>Se ha registrado una nueva solicitud de compra interna.</p>
            
            <table style='width: 100%; margin-bottom: 20px; background: #f9f9f9; padding: 10px;'>
                <tr><td><strong>Empleado:</strong></td><td>$empleado_nombre ($empleado_num)</td></tr>
                <tr><td><strong>Área:</strong></td><td>$empleado_area</td></tr>
                <tr><td><strong>Pedido:</strong></td><td>#$pedido_id</td></tr>
                <tr><td><strong>Plazos Solicitados:</strong></td><td><strong>$plazos Quincenas</strong></td></tr>
            </table>

            <h3>Detalle de la Compra:</h3>
            <table style='width: 100%; border-collapse: collapse;'>
                <thead style='background-color: #eee;'>
                    <tr>
                        <th style='padding: 8px; text-align: left;'>Producto</th>
                        <th style='padding: 8px;'>Cant.</th>
                        <th style='padding: 8px; text-align: right;'>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    $htmlTablaProductos
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='2' style='text-align: right; padding: 10px; font-weight: bold;'>TOTAL A DESCONTAR:</td>
                        <td style='text-align: right; padding: 10px; font-weight: bold; color: #00524A; font-size: 18px;'>$" . number_format($total, 2) . "</td>
                    </tr>
                </tfoot>
            </table>
            
            <p style='margin-top: 30px; font-size: 12px; color: #666;'>
                * Este correo sirve como autorización para el descuento en nómina según los plazos seleccionados.<br>
                * El pedido será preparado por el encargado de la boutique.
            </p>
        </div>
    </div>";

    // Enviar correos (Usando tu función enviarCorreo de mailer.php)
    // Nota: Si enviarCorreo solo acepta un destinatario, hacemos un loop.
    // Si acepta array, pásalo directo. Asumimos loop para compatibilidad básica.
    
    if (function_exists('enviarCorreo')) {
        foreach ($destinatarios as $dest) {
            // Enviamos copia oculta o individual a cada uno
            enviarCorreo($dest['email'], $asunto, $mensajeHTML); 
        }
    }

    echo json_encode(['status' => 'success', 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>