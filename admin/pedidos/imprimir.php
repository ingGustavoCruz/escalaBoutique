<?php
/**
 * admin/pedidos/imprimir.php - Formato de Firma para Nómina
 */
session_start();
require_once '../../api/conexion.php';

// Seguridad
if (!isset($_SESSION['admin_id']) || !isset($_GET['id'])) {
    die("Acceso denegado");
}

$pedido_id = (int)$_GET['id'];

// 1. Obtener Datos del Pedido y Empleado
$sql = "SELECT p.*, e.nombre, e.email, e.numero_empleado, e.area 
        FROM pedidos p 
        JOIN empleados e ON p.empleado_id = e.id 
        WHERE p.id = $pedido_id";
$res = $conn->query($sql);

if ($res->num_rows === 0) die("Pedido no encontrado");
$pedido = $res->fetch_assoc();

// 2. Obtener Productos
$sqlDetalles = "SELECT dp.*, p.nombre 
                FROM detalles_pedido dp 
                JOIN productos p ON dp.producto_id = p.id 
                WHERE dp.pedido_id = $pedido_id";
$detalles = $conn->query($sqlDetalles);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Descuento #<?php echo str_pad($pedido['id'], 5, "0", STR_PAD_LEFT); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            @page { margin: 20px; }
            body { -webkit-print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        body { font-family: 'Times New Roman', serif; color: #000; }
    </style>
</head>
<body class="bg-gray-100 p-8 print:bg-white print:p-0">

    <div class="max-w-3xl mx-auto mb-6 flex justify-end no-print">
        <button onclick="window.print()" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-bold shadow-lg hover:bg-blue-700 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            IMPRIMIR DOCUMENTO
        </button>
    </div>

    <div class="max-w-3xl mx-auto bg-white p-12 shadow-xl print:shadow-none print:w-full">
        
        <div class="flex justify-between items-center border-b-2 border-black pb-4 mb-6">
            <div class="flex items-center gap-4">
                <img src="../../imagenes/EscalaBoutique.png" alt="Logo" class="h-16 w-auto" 
     style="filter: brightness(0) saturate(100%) invert(19%) sepia(13%) saturate(3620%) hue-rotate(130deg) brightness(94%) contrast(102%);">    
                <div>
                    <h1 class="text-2xl font-bold uppercase tracking-widest">Autorización de Descuento</h1>
                    <p class="text-sm">Escala Intranet Corporativa</p>
                </div>
            </div>
            <div class="text-right">
                <p class="font-bold text-lg">FOLIO: #<?php echo str_pad($pedido['id'], 5, "0", STR_PAD_LEFT); ?></p>
                <p class="text-sm"><?php echo date('d/m/Y h:i A', strtotime($pedido['fecha_pedido'])); ?></p>
            </div>
        </div>

        <div class="bg-gray-50 border border-gray-200 p-4 mb-6 print:border-black print:bg-transparent">
            <h3 class="font-bold border-b border-gray-300 mb-2 uppercase text-sm">Datos del Colaborador</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
                <div>
                    <span class="font-bold block text-xs uppercase text-gray-500">Nombre:</span>
                    <?php echo $pedido['nombre']; ?>
                </div>
                <div>
                    <span class="font-bold block text-xs uppercase text-gray-500">No. Empleado:</span>
                    <?php echo $pedido['numero_empleado']; ?>
                </div>
                <div>
                    <span class="font-bold block text-xs uppercase text-gray-500">Departamento/Área:</span>
                    <?php echo $pedido['area']; ?>
                </div>
                <div>
                    <span class="font-bold block text-xs uppercase text-gray-500">Plazo Solicitado:</span>
                    <?php echo $pedido['plazos']; ?> Quincena(s)
                </div>
            </div>
        </div>

        <table class="w-full mb-6 text-sm border-collapse border border-black">
            <thead>
                <tr class="bg-gray-100 print:bg-gray-200">
                    <th class="border border-black px-3 py-2 text-left w-12">Cant.</th>
                    <th class="border border-black px-3 py-2 text-left">Concepto / Producto</th>
                    <th class="border border-black px-3 py-2 text-center w-24">Talla</th>
                    <th class="border border-black px-3 py-2 text-right w-24">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $detalles->fetch_assoc()): ?>
                <tr>
                    <td class="border border-black px-3 py-2 text-center"><?php echo $item['cantidad']; ?></td>
                    <td class="border border-black px-3 py-2"><?php echo $item['nombre']; ?></td>
                    <td class="border border-black px-3 py-2 text-center uppercase"><?php echo $item['talla']; ?></td>
                    <td class="border border-black px-3 py-2 text-right">$<?php echo number_format($item['precio_unitario'], 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="3" class="border border-black px-3 py-2 text-right font-bold uppercase">Total a Descontar:</td>
                    <td class="border border-black px-3 py-2 text-right font-bold text-lg">$<?php echo number_format($pedido['monto_total'], 2); ?></td>
                </tr>
            </tfoot>
        </table>

        <div class="text-justify text-xs mb-12 leading-relaxed px-4">
            <p class="mb-2">
                Por medio del presente documento, <strong>AUTORIZO</strong> expresamente a la empresa a realizar el descuento vía nómina por la cantidad total de 
                <strong>$<?php echo number_format($pedido['monto_total'], 2); ?> MXN</strong>, diferido en <strong><?php echo $pedido['plazos']; ?> quincena(s)</strong> consecutivas a partir de la próxima fecha de pago.
            </p>
            <p>
                Reconozco haber recibido los productos descritos anteriormente a mi entera satisfacción y entiendo que este documento sirve como pagaré incondicional a la orden de la empresa. En caso de baja laboral antes de cubrir el monto total, autorizo que el saldo restante sea descontado de mi finiquito o liquidación conforme a la ley.
            </p>
        </div>

        <div class="grid grid-cols-2 gap-12 mt-20 text-center text-sm">
            <div>
                <div class="border-t border-black pt-2 mx-8"></div>
                <p class="font-bold uppercase"><?php echo $pedido['nombre']; ?></p>
                <p class="text-xs">Firma del Colaborador (Acepto)</p>
            </div>
            <div>
                <div class="border-t border-black pt-2 mx-8"></div>
                <p class="font-bold uppercase">Recursos Humanos / Admin</p>
                <p class="text-xs">Autorización y Sello</p>
            </div>
        </div>

        <div class="mt-12 text-center text-[10px] text-gray-400 uppercase">
            Sistema Intranet Escala Boutique - Documento generado el <?php echo date('d/m/Y H:i:s'); ?>
        </div>

    </div>
</body>
</html>