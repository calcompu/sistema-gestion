<?php
require_once __DIR__ . '/../../config.php'; // Contiene inicio de sesión, $pdo, etc.
// requireLogin(); // Es llamado por requirePermission
requirePermission('facturacion', 'print');
require_once __DIR__ . '/../../includes/functions.php'; // Para formatCurrency u otras funciones

// Validar CSRF token para la acción de imprimir (GET)
if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
    setGlobalMessage("Error de validación CSRF (imprimir). Inténtelo desde el detalle de la factura.", "danger");
    logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED_PRINT', 
        'Intento de imprimir factura con token CSRF inválido o ausente.', 'Facturacion', 
        ['request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => 'GET', 'factura_id' => ($_GET['id'] ?? 'N/A')]
    );
    header('Location: ' . APP_URL . '/modulos/Facturacion/index.php?status=csrf_error_print');
    exit;
}

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    setGlobalMessage("ID de factura no válido para imprimir.", "danger");
    header("Location: " . APP_URL . "/modulos/Facturacion/index.php");
    exit();
}
$id = (int)$_GET['id'];

// Obtener datos de la factura, incluyendo el teléfono del cliente directamente
$stmt = $pdo->prepare("
    SELECT f.*, ped.numero_pedido, 
           c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.ruc_ci as cliente_ruc_ci, c.documento as cliente_tipo_documento, 
           c.direccion as cliente_direccion, c.email as cliente_email, c.telefono as cliente_telefono, 
           u_crea.username as usuario_creador
    FROM facturas f
    LEFT JOIN pedidos ped ON f.pedido_id = ped.id
    LEFT JOIN clientes c ON f.cliente_id = c.id
    LEFT JOIN usuarios u_crea ON f.usuario_id_crea = u_crea.id
    WHERE f.id = ?
");
$stmt->execute([$id]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    setGlobalMessage("Factura no encontrada para imprimir.", "danger");
    header("Location: " . APP_URL . "/modulos/Facturacion/index.php");
    exit();
}

// Obtener items de la factura, incluyendo el código del producto desde la tabla productos
$stmt_items = $pdo->prepare("
    SELECT fi.*, p.codigo as producto_codigo 
    FROM factura_items fi
    LEFT JOIN productos p ON fi.producto_id = p.id
    WHERE fi.factura_id = ? ORDER BY fi.id ASC
");
$stmt_items->execute([$id]);
$detalles = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

// Datos de la empresa (deberían venir de config o BD)
$empresa_nombre = defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : "Nombre de Tu Empresa S.A.C.";
$empresa_ruc = defined('EMPRESA_RUC') ? EMPRESA_RUC : "20xxxxxxxxxx1";
$empresa_direccion = defined('EMPRESA_DIRECCION') ? EMPRESA_DIRECCION : "Av. Principal 123, Ciudad, País";
$empresa_telefono = defined('EMPRESA_TELEFONO') ? EMPRESA_TELEFONO : "+51 1 1234567";
$empresa_email = defined('EMPRESA_EMAIL') ? EMPRESA_EMAIL : "contacto@tuempresa.com";

// Estados de factura (igual que en factura_detalle.php)
$estadosFacturaText = [
    'pendiente_pago' => 'Pendiente de Pago',
    'pagada' => 'Pagada',
    'anulada' => 'Anulada',
    'borrador' => 'Borrador'
];
$estadoActualTexto = $estadosFacturaText[$factura['estado']] ?? ucfirst($factura['estado']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?= htmlspecialchars($factura['numero_factura']) ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            font-size: 12px;
        }
        .container {
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
        }
        .header, .company-info, .invoice-info, .client-info, .footer {
            margin-bottom: 20px;
        }
        .header h1 {
            text-align: center;
            margin: 0;
            color: #333;
        }
        .company-info p, .invoice-info p, .client-info p {
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .totals-table {
            width: 40%;
            margin-left: auto;
        }
        .totals-table td {
             border: none;
        }
        .totals-table td:first-child {
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 0.9em;
            color: #777;
        }
        @media print {
            body {
                padding: 0;
                font-size: 10pt; /* Ajustar tamaño para impresión si es necesario */
            }
            .container {
                border: none;
                box-shadow: none;
                margin: 0;
                max-width: 100%;
                padding: 5mm;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.close();" style="margin-right:10px;">Cerrar</button>
        <button onclick="window.print();">Imprimir</button>
    </div>

    <div class="container">
        <div class="header">
            <h1>FACTURA</h1>
        </div>

        <table style="border:none; margin-bottom: 30px;">
            <tr>
                <td style="border:none; width:60%;">
                    <div class="company-info">
                        <h3><?= htmlspecialchars($empresa_nombre) ?></h3>
                        <p>RUC: <?= htmlspecialchars($empresa_ruc) ?></p>
                        <p><?= htmlspecialchars($empresa_direccion) ?></p>
                        <p>Teléfono: <?= htmlspecialchars($empresa_telefono) ?></p>
                        <p>Email: <?= htmlspecialchars($empresa_email) ?></p>
                    </div>
                </td>
                <td style="border:none; width:40%; vertical-align:top; text-align:right;">
                     <div class="invoice-info">
                        <p><strong>Nº Factura:</strong> <?= htmlspecialchars($factura['numero_factura']) ?></p>
                        <p><strong>Fecha Emisión:</strong> <?= htmlspecialchars(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></p>
                        <?php if ($factura['fecha_vencimiento']): ?>
                            <p><strong>Fecha Vencimiento:</strong> <?= htmlspecialchars(date("d/m/Y", strtotime($factura['fecha_vencimiento']))) ?></p>
                        <?php endif; ?>
                        <p><strong>Estado:</strong> <?= htmlspecialchars($estadoActualTexto) ?></p>
                        <?php if($factura['numero_pedido']): ?>
                            <p><strong>Pedido Original:</strong> <?= htmlspecialchars($factura['numero_pedido']) ?></p>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        </table>

        <div class="client-info">
            <h4>Cliente:</h4>
            <p><?= htmlspecialchars(($factura['cliente_apellido'] ?? '') . ', ' . ($factura['cliente_nombre'] ?? 'N/A')) ?></p>
            <p><?= htmlspecialchars($factura['cliente_tipo_documento'] ?? 'RUC/CI') ?>: <?= htmlspecialchars($factura['cliente_ruc_ci'] ?? 'N/A') ?></p>
            <p>Dirección: <?= htmlspecialchars($factura['cliente_direccion'] ?? 'No especificada') ?></p>
            <p>Teléfono: <?= htmlspecialchars($factura['cliente_telefono'] ?? 'No especificado') ?></p>
            <p>Email: <?= htmlspecialchars($factura['cliente_email'] ?? 'No especificado') ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:15%;">Código</th>
                    <th style="width:40%;">Descripción</th>
                    <th class="text-center" style="width:15%;">Cantidad</th>
                    <th class="text-right" style="width:15%;">P. Unit.</th>
                    <th class="text-right" style="width:15%;">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($detalles)): ?>
                    <tr><td colspan="5" class="text-center">No hay items en esta factura.</td></tr>
                <?php else: ?>
                    <?php foreach ($detalles as $detalle): ?>
                        <tr>
                            <td><?= htmlspecialchars($detalle['producto_codigo'] ?? 'N/A') ?></td> 
                            <td><?= htmlspecialchars($detalle['descripcion_producto']) ?></td>
                            <td class="text-center"><?= htmlspecialchars(number_format($detalle['cantidad'], 2)) ?></td>
                            <td class="text-right"><?= htmlspecialchars(formatCurrency($detalle['precio_unitario'])) ?></td>
                            <td class="text-right"><?= htmlspecialchars(formatCurrency($detalle['subtotal_item'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right"><?= htmlspecialchars(formatCurrency($factura['subtotal'])) ?></td>
            </tr>
            <tr>
                <td>Impuestos:</td>
                <td class="text-right"><?= htmlspecialchars(formatCurrency($factura['impuestos'])) ?></td>
            </tr>
            <tr style="font-size: 1.1em;">
                <td><strong>Total:</strong></td>
                <td class="text-right"><strong><?= htmlspecialchars(formatCurrency($factura['total'])) ?></strong></td>
            </tr>
        </table>

        <?php if (!empty($factura['observaciones'])): ?>
        <div style="margin-top: 20px;">
            <strong>Observaciones:</strong>
            <p><?= nl2br(htmlspecialchars($factura['observaciones'])) ?></p>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Gracias por su preferencia.</p>
            <p>Factura generada por: <?= htmlspecialchars($factura['usuario_creador'] ?? 'Sistema') ?> el <?= htmlspecialchars(date('d/m/Y H:i', strtotime($factura['fecha_creacion']))) ?></p>
             <?php if(defined('EMPRESA_CONDICIONES') && EMPRESA_CONDICIONES): ?>
                <p style="font-size:0.8em; margin-top:15px;"><?= nl2br(htmlspecialchars(EMPRESA_CONDICIONES)) ?></p>
            <?php endif; ?>
        </div>
    </div> <!-- Fin .container -->
</body>
</html> 