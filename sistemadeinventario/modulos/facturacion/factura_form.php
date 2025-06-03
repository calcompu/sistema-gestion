<?php
require_once __DIR__ . '/../../config.php';
requireLogin();
requirePermission('facturacion', 'create_from_order');

$pageTitle = "Generar Nueva Factura";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_GET['pedido_id']) || !filter_var($_GET['pedido_id'], FILTER_VALIDATE_INT)) {
    setGlobalMessage("ID de pedido no válido o no proporcionado para generar la factura.", "danger");
    header('Location: ' . APP_URL . '/modulos/Pedidos/index.php');
    exit;
}
$pedido_id = (int)$_GET['pedido_id'];

// Obtener el ID del estado de pedido 'Completado'
$stmt_estado_completado = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = ?");
$stmt_estado_completado->execute(['completado']);
$estado_completado_id = $stmt_estado_completado->fetchColumn();

if (!$estado_completado_id) {
    setGlobalMessage("Configuración crítica faltante: No se pudo encontrar el estado 'Completado' en la base de datos.", "danger");
    // Considerar loggear este error también
    logSystemEvent($pdo, 'ERROR', 'DB_CONFIG', 'Estado de pedido completado no encontrado.', 'Facturacion');
    header('Location: ' . APP_URL . '/modulos/Pedidos/index.php');
    exit;
}

// Obtener el ID del estado de pedido 'A Facturar'
$stmt_estado_a_facturar = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = ?");
$stmt_estado_a_facturar->execute(['a facturar']);
$estado_a_facturar_id = $stmt_estado_a_facturar->fetchColumn();

if (!$estado_a_facturar_id) {
    setGlobalMessage("Configuración crítica faltante: No se pudo encontrar el estado 'A Facturar' en la base de datos.", "danger");
    logSystemEvent($pdo, 'ERROR', 'DB_CONFIG', 'Estado de pedido \'A Facturar\' no encontrado.', 'Facturacion');
    header('Location: ' . APP_URL . '/modulos/Pedidos/index.php');
    exit;
}

// 1. Verificar si el pedido ya tiene una factura asociada
$stmt_check_factura = $pdo->prepare("SELECT id FROM facturas WHERE pedido_id = ?");
$stmt_check_factura->execute([$pedido_id]);
if ($stmt_check_factura->fetch()) {
    setGlobalMessage("Este pedido ya ha sido facturado.", "warning");
    header('Location: ' . APP_URL . '/modulos/Facturacion/index.php?pedido_id=' . $pedido_id);
    exit;
}

// 2. Cargar datos del Pedido, incluyendo su estado_id y nombre_estado
$stmt_pedido = $pdo->prepare(
    "SELECT p.*, c.id as cliente_id, c.nombre as cliente_nombre, c.apellido as cliente_apellido, c.documento as cliente_ruc_ci, c.direccion as cliente_direccion, c.email as cliente_email, ep.nombre as nombre_estado
     FROM pedidos p
     JOIN clientes c ON p.cliente_id = c.id
     JOIN estados_pedido ep ON p.estado_id = ep.id
     WHERE p.id = ?"
);
$stmt_pedido->execute([$pedido_id]);
$pedido = $stmt_pedido->fetch(PDO::FETCH_ASSOC);

if (!$pedido) {
    setGlobalMessage("Pedido no encontrado.", "danger");
    header('Location: ' . APP_URL . '/modulos/Pedidos/index.php');
    exit;
}

// 3. Validar estado del pedido (solo 'A Facturar' debería ser facturable)
if ((int)$pedido['estado_id'] !== (int)$estado_a_facturar_id) {
    setGlobalMessage("Solo los pedidos en estado 'A Facturar' pueden ser facturados. Estado actual del pedido: " . htmlspecialchars($pedido['nombre_estado']) . ".", "warning");
    header('Location: ' . APP_URL . '/modulos/Pedidos/pedido_detalle.php?id=' . $pedido_id);
    exit;
}

// 4. Cargar items del pedido con cantidad_a_facturar > 0
$stmt_items = $pdo->prepare(
    "SELECT pi.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo
     FROM pedido_items pi
     JOIN productos pr ON pi.producto_id = pr.id
     WHERE pi.pedido_id = ? AND pi.cantidad > 0" // Asegura que solo se carguen items con cantidad a facturar > 0
);
$stmt_items->execute([$pedido_id]);
$pedido_items = $stmt_items->fetchAll();

if (empty($pedido_items)) {
    setGlobalMessage("El pedido no tiene items y no puede ser facturado.", "warning");
    header('Location: ' . APP_URL . '/modulos/Pedidos/pedido_detalle.php?id=' . $pedido_id);
    exit;
}

// Sugerir un número de factura (ejemplo muy básico, necesitarás una lógica más robusta)
$stmtMaxFactura = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(numero_factura, '-', -1), 'F', -1) AS UNSIGNED)) as max_num FROM facturas WHERE numero_factura LIKE 'F%'");
$maxNumFactura = $stmtMaxFactura->fetchColumn();
$siguienteNumeroFactura = 'F' . str_pad(($maxNumFactura + 1), 7, '0', STR_PAD_LEFT); // Ejemplo F0000001

$factura_data_repop = $_SESSION['form_data']['factura'] ?? [];
unset($_SESSION['form_data']['factura']);

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?> para Pedido: <a href="<?= APP_URL ?>/modulos/Pedidos/pedido_detalle.php?id=<?= $pedido_id ?>"><?= htmlspecialchars($pedido['numero_pedido']) ?></a></h1>
            <a href="<?= APP_URL ?>/modulos/Pedidos/index.php?estado_id=<?= $estado_a_facturar_id ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver a Pedidos 'A Facturar'
            </a>
        </div>

        <?php displayGlobalMessages(); ?>

        <form action="factura_acciones.php" method="POST" class="needs-validation" novalidate>
            <input type="hidden" name="accion" value="crear_desde_pedido">
            <input type="hidden" name="pedido_id" value="<?= $pedido_id ?>">
            <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($pedido['cliente_id']) ?>">
            <input type="hidden" name="factura[subtotal]" value="<?= htmlspecialchars($pedido['subtotal'] ?? 0) ?>">
            <input type="hidden" name="factura[impuestos]" value="<?= htmlspecialchars($pedido['impuestos'] ?? 0) ?>">
            <input type="hidden" name="factura[total]" value="<?= htmlspecialchars($pedido['total'] ?? 0) ?>">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Detalles del Cliente</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>Cliente:</strong> <?= htmlspecialchars(($pedido['cliente_apellido'] ?? '') . ', ' . ($pedido['cliente_nombre'] ?? 'N/A')) ?></p>
                            <p><strong>RUC/CI:</strong> <?= htmlspecialchars($pedido['cliente_ruc_ci'] ?? 'N/A') ?></p>
                            <p><strong>Dirección:</strong> <?= htmlspecialchars($pedido['cliente_direccion'] ?? 'No especificada') ?></p>
                            <p><strong>Email:</strong> <?= htmlspecialchars($pedido['cliente_email'] ?? 'No especificado') ?></p>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Items a Facturar (provenientes del Pedido)</h6>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Código</th>
                                            <th>Producto</th>
                                            <th class="text-center">Cantidad</th>
                                            <th class="text-end">Precio Unit.</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedido_items as $index => $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['producto_codigo']) ?></td>
                                                <td><?= htmlspecialchars($item['producto_nombre']) ?></td>
                                                <td class="text-center"><?= htmlspecialchars(number_format($item['cantidad'], 2)) ?></td>
                                                <td class="text-end"><?= formatCurrency($item['precio_unitario']) ?></td>
                                                <td class="text-end"><?= formatCurrency($item['subtotal']) ?></td>
                                                <!-- Campos ocultos para cada item que se enviarán -->
                                                <input type="hidden" name="items[<?= $index ?>][producto_id]" value="<?= htmlspecialchars($item['producto_id']) ?>">
                                                <input type="hidden" name="items[<?= $index ?>][descripcion_producto]" value="<?= htmlspecialchars($item['producto_nombre'] . ' (' . $item['producto_codigo'] . ')') ?>">
                                                <input type="hidden" name="items[<?= $index ?>][cantidad]" value="<?= htmlspecialchars($item['cantidad']) ?>">
                                                <input type="hidden" name="items[<?= $index ?>][precio_unitario]" value="<?= htmlspecialchars($item['precio_unitario']) ?>">
                                                <input type="hidden" name="items[<?= $index ?>][subtotal_item]" value="<?= htmlspecialchars($item['subtotal']) ?>">
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Datos de la Factura</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="numero_factura" class="form-label">Número de Factura <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="numero_factura" name="factura[numero_factura]" value="<?= htmlspecialchars($factura_data_repop['numero_factura'] ?? $siguienteNumeroFactura) ?>" required>
                                <div class="invalid-feedback">Ingrese el número de factura.</div>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_emision" class="form-label">Fecha de Emisión <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="fecha_emision" name="factura[fecha_emision]" value="<?= htmlspecialchars($factura_data_repop['fecha_emision'] ?? date('Y-m-d')) ?>" required>
                                <div class="invalid-feedback">Seleccione la fecha de emisión.</div>
                            </div>
                            <div class="mb-3">
                                <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento (Opcional)</label>
                                <input type="date" class="form-control" id="fecha_vencimiento" name="factura[fecha_vencimiento]" value="<?= htmlspecialchars($factura_data_repop['fecha_vencimiento'] ?? '') ?>">
                            </div>
                             <div class="mb-3">
                                <label for="estado_factura" class="form-label">Estado Inicial <span class="text-danger">*</span></label>
                                <select class="form-select" id="estado_factura" name="factura[estado]" required>
                                    <option value="pendiente_pago" <?= (($factura_data_repop['estado'] ?? 'pendiente_pago') == 'pendiente_pago') ? 'selected' : '' ?>>Pendiente de Pago</option>
                                    <option value="pagada" <?= (($factura_data_repop['estado'] ?? '') == 'pagada') ? 'selected' : '' ?>>Pagada</option>
                                    <option value="borrador" <?= (($factura_data_repop['estado'] ?? '') == 'borrador') ? 'selected' : '' ?>>Borrador</option>
                                </select>
                                <div class="invalid-feedback">Seleccione un estado inicial para la factura.</div>
                            </div>
                            <div class="mb-3">
                                <label for="observaciones_factura" class="form-label">Observaciones (Opcional)</label>
                                <textarea class="form-control" id="observaciones_factura" name="factura[observaciones]" rows="3"><?= htmlspecialchars($factura_data_repop['observaciones'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Totales del Pedido</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-2 row">
                                <strong class="col-sm-5">Subtotal:</strong>
                                <div class="col-sm-7 text-end"><?= formatCurrency($pedido['subtotal'] ?? 0) ?></div>
                            </div>
                            <div class="mb-2 row">
                                <strong class="col-sm-5">Impuestos:</strong>
                                <div class="col-sm-7 text-end"><?= formatCurrency($pedido['impuestos'] ?? 0) ?></div>
                            </div>
                            <hr>
                            <div class="mb-2 row h5">
                                <strong class="col-sm-5">TOTAL:</strong>
                                <div class="col-sm-7 text-end"><?= formatCurrency($pedido['total'] ?? 0) ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="bi bi-receipt-cutoff me-1"></i> Generar Factura
                        </button>
                        <a href="<?= APP_URL ?>/modulos/Pedidos/pedido_detalle.php?id=<?= $pedido_id ?>" class="btn btn-outline-danger">Cancelar y Volver al Pedido</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Script para validación Bootstrap (ya debería estar en main.js o footer)
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})()
</script> 