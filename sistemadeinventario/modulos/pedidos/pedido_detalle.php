<?php
require_once __DIR__ . '/../../config.php';
requireLogin();
requirePermission('pedidos', 'view'); // Permiso para ver detalles de pedidos

$pageTitle = "Detalle del Pedido";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_GET['id'])) {
    setGlobalMessage("No se especificó un ID de pedido.", "danger");
    header("Location: " . APP_URL . "/Modulos/Pedidos/index.php");
    exit;
}

$pedido_id = (int)$_GET['id'];

// Cargar datos del pedido
$stmt_pedido = $pdo->prepare(
    "SELECT p.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.ruc_ci as cliente_ruc, " .
    "e.nombre as estado_nombre, e.clase_css as estado_clase_css, " .
    "u_crea.username as usuario_crea_username, u_act.username as usuario_actualiza_username " .
    "FROM pedidos p " .
    "JOIN clientes c ON p.cliente_id = c.id " .
    "JOIN estados_pedido e ON p.estado_id = e.id " .
    "LEFT JOIN usuarios u_crea ON p.usuario_id_crea = u_crea.id " .
    "LEFT JOIN usuarios u_act ON p.usuario_id_actualiza = u_act.id " .
    "WHERE p.id = ?"
);
$stmt_pedido->execute([$pedido_id]);
$pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    setGlobalMessage("Pedido no encontrado.", "danger");
    header("Location: " . APP_URL . "/Modulos/Pedidos/index.php");
    exit;
}

// Cargar items del pedido
$stmt_items = $pdo->prepare(
    "SELECT pi.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo " .
    "FROM pedido_items pi " .
    "JOIN productos pr ON pi.producto_id = pr.id " .
    "WHERE pi.pedido_id = ? ORDER BY pi.id ASC"
);
$stmt_items->execute([$pedido_id]);
$pedido_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$current_estado_nombre_lower = strtolower($pedido['estado_nombre']);
$can_edit = ($current_estado_nombre_lower === 'en preparación' || $current_estado_nombre_lower === 'a facturar');
$can_cancel = ($current_estado_nombre_lower !== 'facturado' && $current_estado_nombre_lower !== 'cancelado');
$can_proceed_to_invoice = ($current_estado_nombre_lower === 'a facturar');

?>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Detalle del Pedido: #<?= htmlspecialchars($pedido['numero_pedido']) ?></h1>
            <div>
                <a href="index.php" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-left me-1"></i> Volver al Listado</a>
                <button onclick="window.print();" class="btn btn-outline-info me-2"><i class="bi bi-printer me-1"></i> Imprimir</button>
            </div>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Información del Cliente y Pedido</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h5>Cliente:</h5>
                                <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($pedido['cliente_apellido'] . ', ' . $pedido['cliente_nombre']) ?></p>
                                <p class="mb-0"><strong>RUC/CI:</strong> <?= htmlspecialchars($pedido['cliente_ruc']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Pedido:</h5>
                                <p class="mb-1"><strong>Nº Pedido:</strong> <?= htmlspecialchars($pedido['numero_pedido']) ?></p>
                                <p class="mb-1"><strong>Fecha:</strong> <?= htmlspecialchars(date("d/m/Y", strtotime($pedido['fecha_pedido']))) ?></p>
                                <p class="mb-1"><strong>Estado:</strong> <span class="badge bg-<?= htmlspecialchars($pedido['estado_clase_css'] ?? 'secondary') ?>"><?= htmlspecialchars(ucfirst($pedido['estado_nombre'])) ?></span></p>
                            </div>
                        </div>
                        <?php if (!empty($pedido['observaciones'])): ?>
                        <hr>
                        <h5>Observaciones:</h5>
                        <p><?= nl2br(htmlspecialchars($pedido['observaciones'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Productos del Pedido</h6>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pedido_items)): ?>
                            <p class="text-muted">Este pedido no tiene productos.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto (Código)</th>
                                            <th class="text-center">Cant. Solicitada</th>
                                            <th class="text-center">Cant. a Facturar</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php 
                                    $subtotal_calculado_items = 0;
                                    foreach ($pedido_items as $item): 
                                        $cantidad_solicitada = $item['cantidad_solicitada'] ?? $item['cantidad']; // Fallback por si no existe
                                        $cantidad_a_facturar = $item['cantidad'];
                                        $precio_unitario = (float)$item['precio_unitario'];
                                        $subtotal_item = $cantidad_a_facturar * $precio_unitario;
                                        $subtotal_calculado_items += $subtotal_item;

                                        $row_class = '';
                                        $diff_text = '';
                                        if ($cantidad_a_facturar == 0 && $cantidad_solicitada > 0) {
                                            $row_class = 'table-danger item-anulado-detalle';
                                            $diff_text = '<span class="text-danger small fst-italic">(Ítem anulado)</span>';
                                        } elseif ($cantidad_solicitada != $cantidad_a_facturar) {
                                            $row_class = 'table-warning item-ajustado-detalle';
                                            $diff_text = '<span class="text-warning small fst-italic">(Cantidad ajustada)</span>';
                                        }
                                    ?>
                                        <tr class="<?= $row_class ?>">
                                            <td><?= htmlspecialchars($item['producto_nombre']) ?> (<?= htmlspecialchars($item['producto_codigo']) ?>) <?= $diff_text ?></td>
                                            <td class="text-center"><?= htmlspecialchars(formatCurrency($cantidad_solicitada, false)) // No es moneda ?></td>
                                            <td class="text-center fw-bold"><?= htmlspecialchars(formatCurrency($cantidad_a_facturar, false)) // No es moneda ?></td>
                                            <td class="text-end"><?= htmlspecialchars(formatCurrency($precio_unitario)) ?></td>
                                            <td class="text-end"><?= htmlspecialchars(formatCurrency($subtotal_item)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Resumen Financiero</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal Items:</span>
                            <strong><?= htmlspecialchars(formatCurrency($subtotal_calculado_items)) ?></strong>
                        </div>
                         <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal Pedido (Guardado):</span>
                            <strong><?= htmlspecialchars(formatCurrency($pedido['subtotal'])) ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Impuestos:</span>
                            <strong><?= htmlspecialchars(formatCurrency($pedido['impuestos'])) ?></strong>
                        </div>
                        <hr>
                        <div class="d-flex justify-content-between h5 mb-3">
                            <span>TOTAL:</span>
                            <strong><?= htmlspecialchars(formatCurrency($pedido['total'])) ?></strong>
                        </div>
                    </div>
                </div>
                
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Acciones del Pedido</h6>
                    </div>
                    <div class="card-body d-grid gap-2">
                        <?php if ($can_edit): ?>
                            <a href="pedido_form.php?id=<?= $pedido_id ?>" class="btn btn-primary"><i class="bi bi-pencil-square me-1"></i> Editar Pedido</a>
                        <?php endif; ?>

                        <?php if ($can_proceed_to_invoice): ?>
                            <a href="<?= APP_URL ?>/Modulos/Facturacion/factura_form.php?pedido_id=<?= $pedido_id ?>" class="btn btn-success"><i class="bi bi-receipt-cutoff me-1"></i> Proceder a Facturar</a>
                        <?php endif; ?>
                        
                        <?php if ($can_cancel): ?>
                            <form action="pedido_acciones.php" method="POST" onsubmit="return confirm('¿Está seguro de que desea cancelar este pedido? Esta acción no se puede deshacer.');" style="display: contents;">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="accion" value="cancelar">
                                <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
                                <button type="submit" class="btn btn-danger"><i class="bi bi-x-octagon-fill me-1"></i> Cancelar Pedido</button>
                            </form>
                        <?php endif; ?>

                        <?php if (!$can_edit && !$can_proceed_to_invoice && !$can_cancel): ?>
                            <p class="text-muted text-center">No hay acciones disponibles para este pedido en su estado actual.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Auditoría</h6>
                    </div>
                    <div class="card-body">
                        <p class="small mb-1"><strong>Creado por:</strong> <?= htmlspecialchars($pedido['usuario_crea_username'] ?? 'N/A') ?> el <?= htmlspecialchars(date("d/m/Y H:i", strtotime($pedido['fecha_creacion']))) ?></p>
                        <?php if ($pedido['fecha_actualizacion']): ?>
                        <p class="small mb-0"><strong>Última actualización por:</strong> <?= htmlspecialchars($pedido['usuario_actualiza_username'] ?? 'N/A') ?> el <?= htmlspecialchars(date("d/m/Y H:i", strtotime($pedido['fecha_actualizacion']))) ?></p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<style>
    .item-anulado-detalle td {
        background-color: #ffe0e0 !important; /* Un rojo más suave para detalle */
        text-decoration: line-through;
    }
    .item-ajustado-detalle td {
        background-color: #fff3cd !important; /* Amarillo suave para detalle */
    }
    @media print {
        .btn, .main-sidebar, .topbar, .d-flex.justify-content-between.align-items-center.mb-4 > div:last-child {
            display: none !important;
        }
        .main-content {
            margin-left: 0 !important;
        }
        .card {
            box-shadow: none !important;
            border: 1px solid #dee2e6 !important;
        }
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 