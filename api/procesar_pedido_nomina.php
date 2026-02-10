<?php
/**
 * api/procesar_pedido_nomina.php
 * Versión Final: Transacción BD + Notificación a RH + Confirmación al Empleado
 */

session_start();
header('Content-Type: application/json');

// Incluir dependencias
require_once 'conexion.php';
require_once 'mailer.php'; // Tu archivo configurado con PHPMailer y Outlook

// --- 1. VALIDACIÓN DE SESIÓN ---
if (!isset($_SESSION['empleado_id_db'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Por favor recarga la página.']);
    exit;
}

$empleado_id = $_SESSION['empleado_id_db'];

// DATOS DEL EMPLEADO (Recuperados de la sesión de login)
// Es vital que $_SESSION['usuario_empleado']['email'] tenga el correo real del usuario.
$empleado_nombre = $_SESSION['usuario_empleado']['nombre'] ?? 'Empleado';
$empleado_num    = $_SESSION['usuario_empleado']['numero'] ?? 'S/N';
$empleado_area   = $_SESSION['usuario_empleado']['area'] ?? 'General';
$empleado_email  = $_SESSION['usuario_empleado']['email'] ?? ''; 

// --- 2. RECIBIR DATOS DEL FRONTEND ---
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || empty($input['cart'])) {
    echo json_encode(['status' => 'error', 'message' => 'El carrito está vacío.']);
    exit;
}

$carrito = $input['cart'];
$plazos  = isset($input['plazos']) ? (int)$input['plazos'] : 1;
$total_frontend = $input['total'];

if (!in_array($plazos, [1, 2, 3])) { $plazos = 1; }

// --- 3. INICIO DE TRANSACCIÓN SQL ---
$conn->begin_transaction();

try {
    $total_calculado = 0;
    $htmlTablaProductos = ""; 

    // A. Crear Cabecera del Pedido
    $stmtPedido = $conn->prepare("INSERT INTO pedidos (empleado_id, monto_total, plazos, estado, fecha_pedido) VALUES (?, ?, ?, 'pendiente', NOW())");
    $stmtPedido->bind_param("idi", $empleado_id, $total_frontend, $plazos);
    
    if (!$stmtPedido->execute()) {
        throw new Exception("Error al crear el pedido en BD.");
    }
    $pedido_id = $conn->insert_id;
    $stmtPedido->close();

    // B. Procesar Productos
    foreach ($carrito as $item) {
        $producto_id = (int)$item['id'];
        $cantidad    = (int)$item['qty'];
        $talla       = isset($item['talla']) ? $conn->real_escape_string($item['talla']) : null;
        
        // Bloqueo de fila (FOR UPDATE)
        $stmtCheck = $conn->prepare("SELECT precio, stock, nombre FROM productos WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmtCheck->bind_param("i", $producto_id);
        $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        
        if ($resCheck->num_rows === 0) { throw new Exception("Producto ID $producto_id no encontrado."); }
        
        $prodDB = $resCheck->fetch_assoc();
        $stmtCheck->close();

        if ($prodDB['stock'] < $cantidad) {
            throw new Exception("Stock insuficiente para: " . $prodDB['nombre']);
        }

        $precio_real = $prodDB['precio'];
        $subtotal_item = $precio_real * $cantidad;
        $total_calculado += $subtotal_item;

        // Insertar Detalle
        $stmtDetalle = $conn->prepare("INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario, talla) VALUES (?, ?, ?, ?, ?)");
        $stmtDetalle->bind_param("iiids", $pedido_id, $producto_id, $cantidad, $precio_real, $talla);
        $stmtDetalle->execute();
        $stmtDetalle->close();

        // C) Descontar Stock
        if ($talla) {
            // 1. Si es producto con talla, restamos de la tabla de variantes
            $stmtUpdateTalla = $conn->prepare("UPDATE inventario_tallas SET stock = stock - ? WHERE producto_id = ? AND talla = ?");
            $stmtUpdateTalla->bind_param("iis", $cantidad, $producto_id, $talla);
            if (!$stmtUpdateTalla->execute()) {
                throw new Exception("Error al descontar stock de talla $talla.");
            }
            $stmtUpdateTalla->close();
            
            // IMPORTANTE: Si usaste el TRIGGER que te di en el Paso 1, 
            // NO necesitas actualizar la tabla 'productos' manualmente, el trigger lo hará solo.
            // Si NO usaste triggers, descomenta esto:
            /*
            $stmtUpdatePadre = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtUpdatePadre->bind_param("ii", $cantidad, $producto_id);
            $stmtUpdatePadre->execute();
            */
        } else {
            // 2. Si NO tiene talla (es accesorio), restamos directo al producto padre
            $stmtUpdate = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtUpdate->bind_param("ii", $cantidad, $producto_id);
            if (!$stmtUpdate->execute()) {
                throw new Exception("Error al actualizar inventario general.");
            }
            $stmtUpdate->close();
        }

        // Construir fila para el HTML del correo
        $tallaStr = $talla ? " (Talla: $talla)" : "";
        $htmlTablaProductos .= "
            <tr>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; font-size: 13px;'>{$prodDB['nombre']}{$tallaStr}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: center;'>{$cantidad}</td>
                <td style='padding: 8px; border-bottom: 1px solid #ddd; text-align: right;'>$" . number_format($subtotal_item, 2) . "</td>
            </tr>";
    }

    // Actualizar total real si es necesario
    if (abs($total_calculado - $total_frontend) > 0.1) {
         $conn->query("UPDATE pedidos SET monto_total = $total_calculado WHERE id = $pedido_id");
    }

    // --- 4. CONFIRMAR TRANSACCIÓN (COMMIT) ---
    $conn->commit();

    // --- 5. ENVIAR CORREOS ---
    
    $asunto = "Confirmación Pedido #$pedido_id - Escala Boutique";
    
    // Plantilla HTML (Idéntica para todos)
    $mensajeHTML = "
    <div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden;'>
        <div style='background-color: #00524A; padding: 20px; text-align: center;'>
            <h1 style='color: white; margin: 0; font-size: 24px; letter-spacing: 1px;'>ESCALA BOUTIQUE</h1>
            <p style='color: #a8d5d0; margin: 5px 0 0; font-size: 12px; text-transform: uppercase;'>Comprobante de Pedido</p>
        </div>
        
        <div style='padding: 20px; background-color: #ffffff;'>
            <p style='margin-bottom: 20px;'>Hola <strong>$empleado_nombre</strong>, hemos recibido tu solicitud de compra.</p>
            
            <table style='width: 100%; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 6px; font-size: 14px;'>
                <tr><td style='padding: 4px 0;'><strong>Empleado:</strong></td><td>$empleado_nombre ($empleado_num)</td></tr>
                <tr><td style='padding: 4px 0;'><strong>Área:</strong></td><td>$empleado_area</td></tr>
                <tr><td style='padding: 4px 0;'><strong>Pedido ID:</strong></td><td>#$pedido_id</td></tr>
                <tr><td style='padding: 4px 0;'><strong>Plazos:</strong></td><td><strong style='color: #00524A;'>$plazos Quincenas</strong></td></tr>
                <tr><td style='padding: 4px 0;'><strong>Fecha:</strong></td><td>" . date('d/m/Y H:i') . "</td></tr>
            </table>

            <h3 style='font-size: 16px; border-bottom: 2px solid #00524A; padding-bottom: 5px; margin-bottom: 10px;'>Detalle de la Compra</h3>
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'>
                <thead style='background-color: #eee;'>
                    <tr>
                        <th style='padding: 8px; text-align: left; font-size: 12px;'>PRODUCTO</th>
                        <th style='padding: 8px; font-size: 12px;'>CANT.</th>
                        <th style='padding: 8px; text-align: right; font-size: 12px;'>SUBTOTAL</th>
                    </tr>
                </thead>
                <tbody>
                    $htmlTablaProductos
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan='2' style='text-align: right; padding: 15px 10px; font-weight: bold;'>TOTAL:</td>
                        <td style='text-align: right; padding: 15px 10px; font-weight: bold; color: #00524A; font-size: 18px;'>$" . number_format($total_calculado, 2) . "</td>
                    </tr>
                    " . ($plazos > 1 ? "
                    <tr>
                        <td colspan='3' style='text-align: right; padding: 0 10px; font-size: 12px; color: #666;'>
                            Descuento quincenal aprox: <strong>$" . number_format($total_calculado / $plazos, 2) . "</strong>
                        </td>
                    </tr>" : "") . "
                </tfoot>
            </table>
            
            <div style='background-color: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; font-size: 12px; color: #0d47a1;'>
                <strong>Información:</strong><br>
                Tu pedido está siendo procesado. El descuento se verá reflejado en tu nómina según los plazos seleccionados.
            </div>
        </div>
    </div>";

    // --- LISTA DE DESTINATARIOS ---
    // Aquí definimos quién recibe el correo.
    $destinatarios = [
        // 1. EL USUARIO COMPRADOR (Obtenido de la sesión)
        ['email' => $empleado_email, 'nombre' => $empleado_nombre],
        
        // 2. DIRECTORA DE NÓMINA (Para procesar el pago)
        ['email' => 'mundo_cube@hotmail.com', 'tavo' => 'Dirección de Nóminas'], 
        
        // 3. ENCARGADO DE ALMACÉN (Para preparar el paquete)
        ['email' => 'munecodealambre@gmail.com', 'gusgus' => 'Encargado Almacén'] 
    ];

    try {
        if (function_exists('enviarCorreo')) {
            foreach ($destinatarios as $dest) {
                if (filter_var($dest['email'], FILTER_VALIDATE_EMAIL)) {
                    enviarCorreo($dest['email'], $asunto, $mensajeHTML);
                }
            }
        }
    } catch (Exception $mailError) {
        error_log("Error email pedido #$pedido_id: " . $mailError->getMessage());
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Pedido procesado con éxito',
        'pedido_id' => $pedido_id
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

$conn->close();
?>