<?php
require_once __DIR__ . '/../../config.php';
requireLogin();
requirePermission('facturacion', 'view_detail');

$pageTitle = "Detalle de Factura";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    setGlobalMessage("ID de factura no válido o no proporcionado.", "danger");
    header('Location: ' . APP_URL . "/modulos/Facturacion/index.php");
    exit;
}
$factura_id = (int)$_GET['id'];

// Obtener datos de la factura
$stmt_factura = $pdo->prepare(
    "SELECT f.*, ped.numero_pedido, 
            c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.ruc_ci as cliente_ruc_ci, 
            c.email as cliente_email, c.telefono as cliente_telefono, c.direccion as cliente_direccion,
            u_crea.username as usuario_creador,
            u_act.username as usuario_actualizador
     FROM facturas f
     JOIN clientes c ON f.cliente_id = c.id
     LEFT JOIN pedidos ped ON f.pedido_id = ped.id
     LEFT JOIN usuarios u_crea ON f.usuario_id_crea = u_crea.id
     LEFT JOIN usuarios u_act ON f.usuario_id_actualiza = u_act.id
     WHERE f.id = ?"
);
$stmt_factura->execute([$factura_id]);
$factura = $stmt_factura->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    setGlobalMessage("Factura no encontrada.", "danger");
    header('Location: ' . APP_URL . "/modulos/Facturacion/index.php");
    exit;
}

// Obtener items de la factura
$stmt_items = $pdo->prepare(
    "SELECT fi.* 
     FROM factura_items fi
     WHERE fi.factura_id = ? ORDER BY fi.id ASC"
);
$stmt_items->execute([$factura_id]);
$factura_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Detalle Factura: " . htmlspecialchars($factura['numero_factura']);

// Estados de factura y sus badges (igual que en index.php de facturación)
$estadosFacturaText = [
    'pendiente_pago' => 'Pendiente de Pago',
    'pagada' => 'Pagada',
    'anulada' => 'Anulada',
    'borrador' => 'Borrador'
];
$estadoBadges = [
    'pendiente_pago' => 'bg-warning text-dark',
    'pagada' => 'bg-success',
    'anulada' => 'bg-danger',
    'borrador' => 'bg-secondary'
];
$estadoActualTexto = $estadosFacturaText[$factura['estado']] ?? ucfirst($factura['estado']);
$estadoActualBadge = $estadoBadges[$factura['estado']] ?? 'bg-light text-dark';

// Datos de la empresa (ejemplo, considera ponerlos en config.php o en BD)
$empresa_nombre = defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : "Nombre de Tu Empresa S.A.C.";
$empresa_ruc = defined('EMPRESA_RUC') ? EMPRESA_RUC : "20xxxxxxxxxx1";
$empresa_direccion = defined('EMPRESA_DIRECCION') ? EMPRESA_DIRECCION : "Av. Principal 123, Ciudad, País";
$empresa_telefono = defined('EMPRESA_TELEFONO') ? EMPRESA_TELEFONO : "+51 1 1234567";
$empresa_email = defined('EMPRESA_EMAIL') ? EMPRESA_EMAIL : "contacto@tuempresa.com";
$empresa_logo_url = APP_URL . '/assets/img/logo.png'; // Asegúrate que el logo exista

?>
<style>
    .invoice-box {
        max-width: 800px;
        margin: auto;
        padding: 30px;
        border: 1px solid #eee;
        box-shadow: 0 0 10px rgba(0, 0, 0, .15);
        font-size: 16px;
        line-height: 24px;
        font-family: 'Helvetica Neue', 'Helvetica', Helvetica, Arial, sans-serif;
        color: #555;
    }
    .invoice-box table {
        width: 100%;
        line-height: inherit;
        text-align: left;
        border-collapse: collapse;
    }
    .invoice-box table td {
        padding: 5px;
        vertical-align: top;
    }
    .invoice-box table tr td:nth-child(2) {
        /*text-align: right;*/
    }
    .invoice-box table tr.top table td {
        padding-bottom: 20px;
    }
    .invoice-box table tr.top table td.title {
        font-size: 45px;
        line-height: 45px;
        color: #333;
    }
    .invoice-box table tr.information table td {
        padding-bottom: 40px;
    }
    .invoice-box table tr.heading td {
        background: #eee;
        border-bottom: 1px solid #ddd;
        font-weight: bold;
    }
    .invoice-box table tr.details td {
        padding-bottom: 20px;
    }
    .invoice-box table tr.item td{
        border-bottom: 1px solid #eee;
    }
    .invoice-box table tr.item.last td {
        border-bottom: none;
    }
    .invoice-box table tr.total td:nth-child(2) {
        border-top: 2px solid #eee;
        font-weight: bold;
    }
    .text-end { text-align: right !important; }
    .text-center { text-align: center !important; }
    .fw-bold { font-weight: bold !important; }

    @media print {
        body * {
            visibility: hidden;
        }
        .invoice-box, .invoice-box * {
            visibility: visible;
        }
        .invoice-box {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 100%;
            border: none;
            box-shadow: none;
            margin: 0;
            padding: 0;
        }
        .no-print {
            display: none !important;
        }
    }
</style>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Listado
                </a>
                <?php if (hasPermission('facturacion', 'print')): // O simplemente si tiene view_detail, permitir imprimir desde el navegador ?>
                <button onclick="window.print();" class="btn btn-info me-2">
                    <i class="bi bi-printer me-1"></i> Imprimir (Navegador)
                </button>
                <a href="imprimir.php?id=<?= $factura['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" target="_blank" class="btn btn-primary me-2" title="Generar PDF Factura">
                    <i class="bi bi-file-earmark-pdf-fill me-1"></i> Generar PDF
                </a>
                <?php endif; ?>
                <?php if ($factura['estado'] !== 'anulada' && hasPermission('facturacion', 'void')): ?>
                    <form action="factura_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea ANULAR esta factura?');">
                        <input type="hidden" name="accion" value="anular">
                        <input type="hidden" name="factura_id" value="<?= htmlspecialchars($factura['id']) ?>">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        <button type="submit" class="btn btn-danger" title="Anular Factura"><i class="bi bi-x-octagon-fill"></i> Anular</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php displayGlobalMessages(); // Muestra mensajes globales ?> 

        <div class="invoice-box bg-white mb-4">
            <table>
                <tr class="top">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td class="title">
                                    <?php if (file_exists(__DIR__ . '/../../assets/img/logo.png')): ?>
                                        <img src="<?= $empresa_logo_url ?>" style="width:100%; max-width:150px;" alt="Logo Empresa">
                                    <?php else: ?>
                                        <h2 class="fw-bold"><?= htmlspecialchars($empresa_nombre) ?></h2>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <h3 class="fw-bold">FACTURA</h3>
                                    <strong>Nº: <?= htmlspecialchars($factura['numero_factura']) ?></strong><br>
                                    Fecha Emisión: <?= htmlspecialchars(date("d/m/Y", strtotime($factura['fecha_emision']))) ?><br>
                                    <?php if ($factura['fecha_vencimiento']): ?>
                                        Fecha Vencimiento: <?= htmlspecialchars(date("d/m/Y", strtotime($factura['fecha_vencimiento']))) ?><br>
                                    <?php endif; ?>
                                    Estado: <span class="badge <?= $estadoActualBadge ?>"><?= htmlspecialchars($estadoActualTexto) ?></span><br>
                                    <?php if($factura['pedido_id'] && $factura['numero_pedido']): ?>
                                    Pedido Original: <a href="<?= APP_URL ?>/modulos/Pedidos/pedido_detalle.php?id=<?= htmlspecialchars($factura['pedido_id']) ?>" target="_blank"><?= htmlspecialchars($factura['numero_pedido']) ?></a><br>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr class="information">
                    <td colspan="2">
                        <table>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($empresa_nombre) ?></strong><br>
                                    RUC: <?= htmlspecialchars($empresa_ruc) ?><br>
                                    <?= nl2br(htmlspecialchars($empresa_direccion)) ?><br>
                                    Tel: <?= htmlspecialchars($empresa_telefono) ?><br>
                                    Email: <?= htmlspecialchars($empresa_email) ?>
                                </td>
                                <td class="text-end">
                                    <strong>Facturar a:</strong><br>
                                    <?= htmlspecialchars(($factura['cliente_apellido'] ?? '') . ', ' . ($factura['cliente_nombre'] ?? 'N/A')) ?><br>
                                    RUC/CI: <?= htmlspecialchars($factura['cliente_ruc_ci'] ?? 'N/A') ?><br>
                                    <?php if(!empty($factura['cliente_direccion'])): ?>
                                    <?= nl2br(htmlspecialchars($factura['cliente_direccion'])) ?><br>
                                    <?php endif; ?>
                                    <?php if(!empty($factura['cliente_telefono'])): ?>
                                    Tel: <?= htmlspecialchars($factura['cliente_telefono']) ?><br>
                                    <?php endif; ?>
                                    <?php if(!empty($factura['cliente_email'])): ?>
                                    Email: <?= htmlspecialchars($factura['cliente_email']) ?><br>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr class="heading">
                    <td>Descripción</td>
                    <td class="text-center" style="width: 15%;">Cantidad</td>
                    <td class="text-end" style="width: 20%;">Precio Unit.</td>
                    <td class="text-end" style="width: 20%;">Subtotal</td>
                </tr>

                <?php foreach ($factura_items as $item): ?>
                <tr class="item">
                    <td><?= htmlspecialchars($item['descripcion_producto']) ?></td>
                    <td class="text-center"><?= htmlspecialchars(number_format($item['cantidad'], 2)) ?></td>
                    <td class="text-end"><?= htmlspecialchars(formatCurrency($item['precio_unitario'])) ?></td>
                    <td class="text-end"><?= htmlspecialchars(formatCurrency($item['subtotal_item'])) ?></td>
                </tr>
                <?php endforeach; ?>

                <tr class="total">
                    <td colspan="2"></td>
                    <td class="text-end fw-bold">Subtotal:</td>
                    <td class="text-end"><?= htmlspecialchars(formatCurrency($factura['subtotal'])) ?></td>
                </tr>
                <tr class="total">
                    <td colspan="2"></td>
                    <td class="text-end fw-bold">Impuestos:</td>
                    <td class="text-end"><?= htmlspecialchars(formatCurrency($factura['impuestos'])) ?></td>
                </tr>
                <tr class="total">
                    <td colspan="2"></td>
                    <td class="text-end fw-bold h5">TOTAL:</td>
                    <td class="text-end fw-bold h5"><?= htmlspecialchars(formatCurrency($factura['total'])) ?></td>
                </tr>
                 <?php if (!empty($factura['observaciones'])): ?>
                <tr>
                    <td colspan="4" style="padding-top: 20px;">
                        <strong>Observaciones:</strong><br>
                        <?= nl2br(htmlspecialchars($factura['observaciones'])) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <!-- Sección de Auditoría -->
            <div style="margin-top: 30px; padding-top: 10px; border-top: 1px solid #eee; font-size: 0.9em; color: #777;">
                Creada por: <?= htmlspecialchars($factura['usuario_creador'] ?? 'N/A') ?> el <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($factura['fecha_creacion']))) ?><br>
                <?php if (!empty($factura['usuario_actualizador']) && !empty($factura['fecha_actualizacion'])): ?>
                    Última actualización por: <?= htmlspecialchars($factura['usuario_actualizador']) ?> el <?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($factura['fecha_actualizacion']))) ?><br>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 