<?php
session_start();
require_once '../../api/conexion.php';
require_once '../../api/logger.php';

if (!isset($_SESSION['admin_id'])) { exit; }

$query = "
    SELECT e.numero_empleado, e.nombre, p.id as pedido_id, p.monto_total, p.plazos
    FROM pedidos p
    JOIN empleados e ON p.empleado_id = e.id
    WHERE p.estado = 'aprobado' AND p.enviado_nomina = 0
";
$resultado = $conn->query($query);

if ($resultado->num_rows > 0) {
    $filename = "Corte_Nomina_" . date('Y-m-d_H-i') . ".csv";
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    // Encabezados del CSV para el software de RH
    fputcsv($output, ['No_Empleado', 'Nombre', 'Pedido_ID', 'Total_Compra', 'Plazos', 'Monto_Quincenal']);

    $ids_procesados = [];
    while ($row = $resultado->fetch_assoc()) {
        $descuento = round($row['monto_total'] / $row['plazos'], 2);
        fputcsv($output, [
            $row['numero_empleado'],
            $row['nombre'],
            $row['pedido_id'],
            $row['monto_total'],
            $row['plazos'],
            $descuento
        ]);
        $ids_procesados[] = $row['pedido_id'];
    }

    // Quirúrgico: Marcamos como enviados para cerrar el corte
    if (!empty($ids_procesados)) {
        $lista_ids = implode(',', $ids_procesados);
        $conn->query("UPDATE pedidos SET enviado_nomina = 1 WHERE id IN ($lista_ids)");
        registrarBitacora('NOMINA', 'CORTE GENERADO', "Se exportaron " . count($ids_procesados) . " pedidos al archivo $filename", $conn);
    }
    
    fclose($output);
    exit;
} else {
    echo "No hay datos para exportar.";
}