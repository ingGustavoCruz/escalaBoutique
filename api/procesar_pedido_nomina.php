<?php
/**
 * api/procesar_pedido_nomina.php
 * Versión Final: Transacción BD + Notificación + Auditoría + Envíos Foráneos
 */
session_start();
header('Content-Type: application/json');

require_once 'conexion.php';
require_once 'mailer.php';

if (!isset($_SESSION['empleado_id_db'])) { echo json_encode(['status' => 'error', 'message' => 'Sesión expirada. Por favor recarga.']); exit; }

$empleado_id = $_SESSION['empleado_id_db'];
$empleado_nombre = $_SESSION['usuario_empleado']['nombre'] ?? 'Empleado';
$empleado_num = $_SESSION['usuario_empleado']['numero'] ?? 'S/N';
$empleado_area = $_SESSION['usuario_empleado']['area'] ?? 'General';
$empleado_email = $_SESSION['usuario_empleado']['email'] ?? ''; 

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['cart'])) { echo json_encode(['status' => 'error', 'message' => 'Carrito vacío.']); exit; }

$carrito = $input['cart'];
$plazos = isset($input['plazos']) ? (int)$input['plazos'] : 1;
if (!in_array($plazos, [1, 2, 3])) { $plazos = 1; }
$total_frontend = $input['total'];

// --- NUEVO: DATOS DE ENVÍO ---
$requiere_envio = !empty($input['requiereEnvio']) ? 1 : 0;
$form_envio = $input['formEnvio'] ?? [];

$conn->begin_transaction();
try {
    $total_calculado = 0; $htmlTablaProductos = ""; 

    // A. Crear Cabecera del Pedido (Ahora con requiere_envio)
    $stmtPedido = $conn->prepare("INSERT INTO pedidos (empleado_id, monto_total, plazos, estado, requiere_envio, fecha_pedido) VALUES (?, ?, ?, 'pendiente', ?, NOW())");
    $stmtPedido->bind_param("idii", $empleado_id, $total_frontend, $plazos, $requiere_envio);
    if (!$stmtPedido->execute()) throw new Exception("Error al crear pedido en BD.");
    $pedido_id = $conn->insert_id;
    $stmtPedido->close();

    // B. Insertar Dirección si aplica
    if ($requiere_envio) {
        $stmtDir = $conn->prepare("INSERT INTO direcciones_envio (pedido_id, estado, calle, colonia, cp, nombre_contacto, telefono_contacto) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtDir->bind_param("issssss", $pedido_id, $form_envio['estado'], $form_envio['calle'], $form_envio['colonia'], $form_envio['cp'], $form_envio['nombre_contacto'], $form_envio['telefono_contacto']);
        if (!$stmtDir->execute()) throw new Exception("Error al guardar dirección.");
        $stmtDir->close();
    }

    // C. Procesar Productos
    foreach ($carrito as $item) {
        $producto_id = (int)$item['id']; $cantidad = (int)$item['qty'];
        $talla = !empty($item['talla']) ? $conn->real_escape_string($item['talla']) : null;
        $incorporada_id = !empty($item['incorporada_id']) ? (int)$item['incorporada_id'] : null;
        
        $stmtCheck = $conn->prepare("SELECT precio, stock, nombre FROM productos WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmtCheck->bind_param("i", $producto_id); $stmtCheck->execute();
        $resCheck = $stmtCheck->get_result();
        if ($resCheck->num_rows === 0) throw new Exception("Producto $producto_id no encontrado.");
        $prodDB = $resCheck->fetch_assoc(); $stmtCheck->close();

        if ($prodDB['stock'] < $cantidad) throw new Exception("Stock insuficiente: " . $prodDB['nombre']);
        $precio_real = $prodDB['precio']; $subtotal_item = $precio_real * $cantidad; $total_calculado += $subtotal_item;

        $stmtDetalle = $conn->prepare("INSERT INTO detalles_pedido (pedido_id, producto_id, cantidad, precio_unitario, talla, incorporada_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtDetalle->bind_param("iiidsi", $pedido_id, $producto_id, $cantidad, $precio_real, $talla, $incorporada_id);
        $stmtDetalle->execute(); $stmtDetalle->close();

        // Descontar Stock y Bitácora
        $motivo_audit = "VENTA PEDIDO #$pedido_id"; $cambio_stock = $cantidad * -1;
        if ($talla) {
            $stmtUpd = $conn->prepare("UPDATE inventario_tallas SET stock = stock - ? WHERE producto_id = ? AND talla = ?");
            $stmtUpd->bind_param("iis", $cantidad, $producto_id, $talla);
            if (!$stmtUpd->execute()) throw new Exception("Error stock talla."); $stmtUpd->close();
            $stmtLog = $conn->prepare("INSERT INTO bitacora_inventario (producto_id, talla, cantidad_cambio, motivo) VALUES (?, ?, ?, ?)");
            $stmtLog->bind_param("isis", $producto_id, $talla, $cambio_stock, $motivo_audit);
        } else {
            $stmtUpd = $conn->prepare("UPDATE productos SET stock = stock - ? WHERE id = ?");
            $stmtUpd->bind_param("ii", $cantidad, $producto_id);
            if (!$stmtUpd->execute()) throw new Exception("Error stock general."); $stmtUpd->close();
            $null_talla = null;
            $stmtLog = $conn->prepare("INSERT INTO bitacora_inventario (producto_id, talla, cantidad_cambio, motivo) VALUES (?, ?, ?, ?)");
            $stmtLog->bind_param("isis", $producto_id, $null_talla, $cambio_stock, $motivo_audit);
        }
        $stmtLog->execute(); $stmtLog->close();

        // Nombre empresa para correo
        $inc_nombre = "";
        if ($incorporada_id) {
            $stmtInc = $conn->prepare("SELECT nombre FROM incorporadas WHERE id = ?");
            $stmtInc->bind_param("i", $incorporada_id); $stmtInc->execute(); $stmtInc->bind_result($nombre_empresa);
            if ($stmtInc->fetch()) $inc_nombre = $nombre_empresa; $stmtInc->close();
        }

        $tallaStr = $talla ? " (Talla: $talla)" : "";
        $incStr = $inc_nombre ? "<br><span style='color: #00524A; font-size: 11px; font-weight: bold;'>[$inc_nombre]</span>" : "";
        $htmlTablaProductos .= "<tr><td style='padding:8px; border-bottom:1px solid #ddd; font-size:13px;'>{$prodDB['nombre']}{$tallaStr}{$incStr}</td><td style='padding:8px; border-bottom:1px solid #ddd; text-align:center;'>{$cantidad}</td><td style='padding:8px; border-bottom:1px solid #ddd; text-align:right;'>$" . number_format($subtotal_item, 2) . "</td></tr>";
    }

    if (abs($total_calculado - $total_frontend) > 0.1) $conn->query("UPDATE pedidos SET monto_total = $total_calculado WHERE id = $pedido_id");

    // D. Cuotas Nómina
    $monto_cuota = round($total_calculado / $plazos, 2); $suma_cuotas = $monto_cuota * $plazos; $ajuste = round($total_calculado - $suma_cuotas, 2);
    for ($i = 1; $i <= $plazos; $i++) {
        $monto_final = ($i === $plazos) ? ($monto_cuota + $ajuste) : $monto_cuota;
        $stmtPagos = $conn->prepare("INSERT INTO pagos_nomina (pedido_id, empleado_id, numero_cuota, total_cuotas, monto_cuota) VALUES (?, ?, ?, ?, ?)");
        $stmtPagos->bind_param("iiiid", $pedido_id, $empleado_id, $i, $plazos, $monto_final); $stmtPagos->execute(); $stmtPagos->close();
    }

    $conn->commit();

    // E. Generar Bloque de Correo para Envío
    $htmlEnvio = $requiere_envio ? "
        <h3 style='font-size: 14px; border-bottom: 2px solid #00524A; padding-bottom: 5px; margin-top: 20px;'>Dirección de Envío</h3>
        <table style='width: 100%; font-size: 13px; background: #eef2f3; padding: 10px; border-radius: 5px;'>
            <tr><td><strong>Recibe:</strong> {$form_envio['nombre_contacto']} ({$form_envio['telefono_contacto']})</td></tr>
            <tr><td><strong>Dirección:</strong> {$form_envio['calle']}, Col. {$form_envio['colonia']}, C.P. {$form_envio['cp']}, {$form_envio['estado']}</td></tr>
        </table>" : "
        <h3 style='font-size: 14px; border-bottom: 2px solid #00524A; padding-bottom: 5px; margin-top: 20px;'>Método de Entrega</h3>
        <p style='font-size: 13px; color: #555;'>Recoger en Oficina CDMX</p>";

    // F. Enviar Correos
    $asunto = "Confirmación Pedido #$pedido_id - Escala Boutique";
    $mensajeHTML = "<div style='font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; border: 1px solid #e0e0e0; border-radius: 8px;'><div style='background-color: #00524A; padding: 20px; text-align: center;'><h1 style='color: white; margin: 0; font-size: 24px;'>ESCALA BOUTIQUE</h1><p style='color: #a8d5d0; margin: 5px 0 0; font-size: 12px; text-transform: uppercase;'>Comprobante de Pedido</p></div><div style='padding: 20px; background-color: #ffffff;'><p>Hola <strong>$empleado_nombre</strong>, hemos recibido tu solicitud.</p><table style='width: 100%; margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 6px; font-size: 14px;'><tr><td><strong>Empleado:</strong></td><td>$empleado_nombre ($empleado_num)</td></tr><tr><td><strong>Área:</strong></td><td>$empleado_area</td></tr><tr><td><strong>Pedido ID:</strong></td><td>#$pedido_id</td></tr><tr><td><strong>Plazos:</strong></td><td><strong style='color: #00524A;'>$plazos Quincenas</strong></td></tr></table> $htmlEnvio <h3 style='font-size: 16px; border-bottom: 2px solid #00524A; padding-bottom: 5px; margin-bottom: 10px; margin-top: 20px;'>Detalle de la Compra</h3><table style='width: 100%; border-collapse: collapse; margin-bottom: 20px;'><thead style='background-color: #eee;'><tr><th style='padding: 8px; text-align: left; font-size: 12px;'>PRODUCTO</th><th style='padding: 8px; font-size: 12px;'>CANT.</th><th style='padding: 8px; text-align: right; font-size: 12px;'>SUBTOTAL</th></tr></thead><tbody>$htmlTablaProductos</tbody><tfoot><tr><td colspan='2' style='text-align: right; padding: 15px 10px; font-weight: bold;'>TOTAL:</td><td style='text-align: right; padding: 15px 10px; font-weight: bold; color: #00524A; font-size: 18px;'>$" . number_format($total_calculado, 2) . "</td></tr>" . ($plazos > 1 ? "<tr><td colspan='3' style='text-align: right; padding: 0 10px; font-size: 12px; color: #666;'>Descuento quincenal aprox: <strong>$" . number_format($total_calculado / $plazos, 2) . "</strong></td></tr>" : "") . "</tfoot></table><div style='background-color: #e3f2fd; padding: 10px; border-left: 4px solid #2196f3; font-size: 12px; color: #0d47a1;'><strong>Información:</strong> El descuento se verá reflejado en tu nómina.</div></div></div>";

    $email_nomina = get_config('email_nomina', $conn); $email_almacen = get_config('email_almacen', $conn);
    $destinatarios = [ ['email' => $empleado_email], ['email' => $email_nomina], ['email' => $email_almacen] ];

    try {
        if (function_exists('enviarCorreo')) {
            foreach ($destinatarios as $dest) { if (filter_var($dest['email'], FILTER_VALIDATE_EMAIL)) enviarCorreo($dest['email'], $asunto, $mensajeHTML); }
        }
    } catch (Exception $e) { error_log("Error email pedido #$pedido_id"); }

    echo json_encode(['status' => 'success', 'message' => 'Pedido procesado', 'pedido_id' => $pedido_id]);

} catch (Exception $e) {
    $conn->rollback(); echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
$conn->close();
?>