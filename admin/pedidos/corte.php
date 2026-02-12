<?php
/**
 * admin/pedidos/corte.php - Panel de Corte de Quincena
 */
session_start();
require_once '../../api/conexion.php';

if (!isset($_SESSION['admin_id'])) { header("Location: ../index.php"); exit; }

// Obtenemos pedidos aprobados que NO han sido enviados a nómina
$query = "
    SELECT p.*, e.nombre as empleado, e.numero_empleado, e.area
    FROM pedidos p
    JOIN empleados e ON p.empleado_id = e.id
    WHERE p.estado = 'aprobado' AND p.enviado_nomina = 0
    ORDER BY p.fecha_pedido ASC
";
$resultado = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Nómina | Escala Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script>
        tailwind.config = { theme: { extend: { colors: { 'escala-green': '#00524A', 'escala-dark': '#003d36' } } } }
    </script>
</head>
<body class="bg-slate-50 font-sans flex h-screen overflow-hidden">
    <?php include '../includes/sidebar.php'; ?>

    <main class="flex-1 overflow-y-auto p-8">
        <header class="flex justify-between items-center mb-8">
            <div>
                <h1 class="text-2xl font-black text-escala-green uppercase">Corte de Quincena</h1>
                <p class="text-gray-500 text-sm">Pedidos aprobados pendientes de procesar en nómina.</p>
            </div>
            
            <?php if($resultado->num_rows > 0): ?>
            <a href="exportar_csv.php" class="bg-escala-green hover:bg-escala-dark text-white px-6 py-3 rounded-xl font-bold uppercase text-xs shadow-lg flex items-center gap-2 transition-all">
                <i data-lucide="download" class="w-4 h-4"></i> Descargar Layout y Cerrar Corte
            </a>
            <?php endif; ?>
        </header>

        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead class="bg-gray-50 text-[10px] uppercase font-black text-gray-400 border-b border-gray-100">
                    <tr>
                        <th class="px-6 py-4">Empleado</th>
                        <th class="px-6 py-4 text-center">Plazos</th>
                        <th class="px-6 py-4 text-right">Total Pedido</th>
                        <th class="px-6 py-4 text-right text-escala-green">Descuento Qna.</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-sm">
                    <?php 
                    $total_corte = 0;
                    while($row = $resultado->fetch_assoc()): 
                        $descuento_qna = $row['monto_total'] / $row['plazos'];
                        $total_corte += $descuento_qna;
                    ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="font-bold text-gray-800"><?php echo $row['empleado']; ?></div>
                            <div class="text-[10px] text-gray-400">ID #<?php echo $row['numero_empleado']; ?></div>
                        </td>
                        <td class="px-6 py-4 text-center font-bold"><?php echo $row['plazos']; ?></td>
                        <td class="px-6 py-4 text-right font-medium text-gray-500">$<?php echo number_format($row['monto_total'], 2); ?></td>
                        <td class="px-6 py-4 text-right font-black text-escala-green">$<?php echo number_format($descuento_qna, 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
                <?php if($resultado->num_rows > 0): ?>
                <tfoot class="bg-escala-green/5 border-t-2 border-escala-green">
                    <tr>
                        <td colspan="3" class="px-6 py-4 text-right font-black text-escala-green uppercase">Total a descontar esta quincena:</td>
                        <td class="px-6 py-4 text-right font-black text-xl text-escala-green">$<?php echo number_format($total_corte, 2); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
            
            <?php if($resultado->num_rows === 0): ?>
                <div class="p-20 text-center text-gray-300">
                    <i data-lucide="check-circle" class="w-16 h-16 mx-auto mb-4 opacity-20"></i>
                    <p class="font-bold uppercase tracking-widest">No hay pedidos pendientes de corte</p>
                </div>
            <?php endif; ?>
        </div>
    </main>
    <script>lucide.createIcons();</script>
</body>
</html>