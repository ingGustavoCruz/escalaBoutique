<?php
/**
 * admin/pedidos/exportar_csv.php - Generador de Layout Filtrado
 * Versión: Sincronización Total (Quirúrgico)
 */
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

// 1. Seguridad de Acceso
if (!isset($_SESSION['admin_id'])) { exit; }

// 2. Captura de Filtros (Idéntico a corte.php)
$f_inicio = $_GET['f_inicio'] ?? '';
$f_fin    = $_GET['f_fin'] ?? '';
$area_sel = $_GET['area'] ?? '';

// 3. Construcción de Query Dinámica
$where = "WHERE pn.estado = 'pendiente' AND p.estado = 'aprobado'";

if (!empty($f_inicio)) {
    $where .= " AND p.fecha_pedido >= '" . $conn->real_escape_string($f_inicio) . " 00:00:00'";
}
if (!empty($f_fin)) {
    $where .= " AND p.fecha_pedido <= '" . $conn->real_escape_string($f_fin) . " 23:59:59'";
}
if (!empty($area_sel)) {
    $where .= " AND e.area = '" . $conn->real_escape_string($area_sel) . "'";
}

$query = "
    SELECT 
        pn.id as pago_id, pn.numero_cuota, pn.total_cuotas, pn.monto_cuota,
        e.numero_empleado, e.nombre, e.area, p.id as pedido_id, p.monto_total
    FROM pagos_nomina pn
    JOIN pedidos p ON pn.pedido_id = p.id
    JOIN empleados e ON pn.empleado_id = e.id
    $where
    ORDER BY p.fecha_pedido ASC
";

$resultado = $conn->query($query);

if ($resultado && $resultado->num_rows > 0) {
    // Limpiar búfer para evitar basura en el archivo
    if (ob_get_length()) ob_clean();

    $filename = "Corte_Nomina_" . date('Y-m-d_H-i') . ".csv";
    
    // Headers de descarga
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    
    // BOM para compatibilidad con acentos en Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados del Layout solicitado
    fputcsv($output, ['No_Empleado', 'Nombre', 'Area', 'Pedido_ID', 'Total_Compra', 'Cuota_Actual', 'Monto_Quincenal']);

    $pagos_ids = [];
    $conn->begin_transaction();

    try {
        while ($row = $resultado->fetch_assoc()) {
            fputcsv($output, [
                $row['numero_empleado'],
                $row['nombre'],
                strtoupper($row['area']),
                $row['pedido_id'],
                number_format($row['monto_total'], 2, '.', ''),
                $row['numero_cuota'] . " de " . $row['total_cuotas'],
                number_format($row['monto_cuota'], 2, '.', '')
            ]);
            $pagos_ids[] = $row['pago_id'];
        }

        if (!empty($pagos_ids)) {
            $lista = implode(',', $pagos_ids);
            
            // A. Marcar cuotas como enviadas
            $conn->query("UPDATE pagos_nomina SET estado = 'enviado', fecha_corte = NOW() WHERE id IN ($lista)");
            
            // B. Marcado inteligente del pedido global (Solo si ya no tiene cuotas pendientes)
            $conn->query("UPDATE pedidos p SET enviado_nomina = 1 
                          WHERE id IN (SELECT pedido_id FROM pagos_nomina WHERE id IN ($lista))
                          AND NOT EXISTS (SELECT 1 FROM pagos_nomina pn WHERE pn.pedido_id = p.id AND pn.estado = 'pendiente')");
            
            // C. Registro en Bitácora con detalles del filtro
            $detalle_log = "Exportación: " . count($pagos_ids) . " registros. Filtros: Area(" . ($area_sel ?: 'Todas') . ")";
            registrarBitacora('NOMINA', 'CORTE GENERADO', $detalle_log, $conn);
        }

        $conn->commit();
        fclose($output);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        header_remove();
        echo "Error: " . $e->getMessage();
    }
} else {
    echo "No hay datos que coincidan con los filtros seleccionados.";
}