<?php
require_once __DIR__ . '/../../config.php';

$editMode = false;
if (isset($_GET['id'])) {
    $editMode = true;
    requirePermission('pedidos', 'edit');
    $pedido_id = (int)$_GET['id'];
} else {
    requirePermission('pedidos', 'create');
}

$pageTitle = $editMode ? "Editar Pedido" : "Crear Nuevo Pedido";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
// require_once __DIR__ . '/pedidos_functions.php'; // Descomentar si se usa generarCodigoPedido()

$pedido = [
    'id' => '',
    'cliente_id' => '',
    'numero_pedido' => $editMode ? '' : '(Se asignará al guardar)', // Se llenará desde la BD si $editMode
    'fecha_pedido' => date('Y-m-d'),
    'estado_id' => '', // Usaremos estado_id numérico
    'subtotal' => 0.00,
    'impuestos' => 0.00,
    'total' => 0.00,
    'observaciones' => ''
];
$pedido_items_data = []; // Renombrado para claridad, contendrá datos crudos de BD

// Obtener todos los estados de pedido disponibles
$stmt_all_estados = $pdo->query("SELECT id, nombre FROM estados_pedido ORDER BY id");
$todos_los_estados = $stmt_all_estados->fetchAll(PDO::FETCH_ASSOC);

$estado_en_preparacion_id = '';
$estado_a_facturar_id = '';

foreach ($todos_los_estados as $est) {
    if (strtolower($est['nombre']) === 'en preparación') {
        $estado_en_preparacion_id = $est['id'];
    }
    if (strtolower($est['nombre']) === 'a facturar') {
        $estado_a_facturar_id = $est['id'];
    }
}

if (!$editMode) {
    $pedido['estado_id'] = $estado_en_preparacion_id; // Default para nuevos pedidos
} else {
    // En modo edición, el estado se carga desde la BD
}


if ($editMode) {
    $stmt = $pdo->prepare("SELECT * FROM pedidos WHERE id = ?");
    $stmt->execute([$pedido_id]);
    $pedido_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($pedido_data) {
        // Obtener el nombre del estado actual para la validación
        $stmt_current_estado_nombre = $pdo->prepare("SELECT nombre FROM estados_pedido WHERE id = ?");
        $stmt_current_estado_nombre->execute([$pedido_data['estado_id']]);
        $current_estado_nombre_data = $stmt_current_estado_nombre->fetch(PDO::FETCH_ASSOC);
        $current_estado_nombre = $current_estado_nombre_data ? strtolower($current_estado_nombre_data['nombre']) : '';

        if ($current_estado_nombre === 'facturado' || $current_estado_nombre === 'cancelado') {
            setGlobalMessage("Este pedido no puede ser editado porque ya ha sido " . htmlspecialchars($current_estado_nombre) . ".", "warning");
            echo '<div class="container-fluid mt-3">';
            displayGlobalMessages(); 
            echo '<a href="index.php" class="btn btn-primary"><i class="bi bi-arrow-left"></i> Volver al Listado</a></div>';
            require_once __DIR__ . '/../../includes/footer.php';
            exit;
        }
        $pedido = $pedido_data; // $pedido_data ya tiene estado_id
        $stmt_items = $pdo->prepare(
            "SELECT pi.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo 
             FROM pedido_items pi 
             JOIN productos pr ON pi.producto_id = pr.id 
             WHERE pi.pedido_id = ?"
        );
        $stmt_items->execute([$pedido_id]);
        $pedido_items_data = $stmt_items->fetchAll(PDO::FETCH_ASSOC); // Datos crudos
        $pageTitle = "Editar Pedido: " . htmlspecialchars($pedido['numero_pedido']);
    } else {
        setGlobalMessage("Pedido no encontrado.", "danger");
        // Redirigir si el pedido no se encuentra en modo edición
        header('Location: ' . APP_URL . '/Modulos/Pedidos/index.php');
        exit;
    }
} else {
    // $pageTitle ya está seteado a "Crear Nuevo Pedido"
}

$clientes = $pdo->query("SELECT id, nombre, apellido, ruc_ci FROM clientes WHERE activo = 1 ORDER BY apellido, nombre ASC")->fetchAll();
$productos_disponibles = $pdo->query("SELECT id, nombre, codigo, precio_venta, stock FROM productos WHERE activo = 1 ORDER BY nombre ASC")->fetchAll();

// Determinar el estado actual del pedido para la lógica del formulario
$current_pedido_estado_nombre_lower = '';
if ($editMode && isset($pedido['estado_id'])) {
    foreach ($todos_los_estados as $est_actual) {
        if ($est_actual['id'] == $pedido['estado_id']) {
            $current_pedido_estado_nombre_lower = strtolower($est_actual['nombre']);
            break;
        }
    }
}


// Preparar los estados para el select del formulario
$estados_para_select = [];
if ($editMode) {
    // Si está 'En Preparación', puede pasar a 'A Facturar' o mantenerse.
    // Si está 'A Facturar', puede mantenerse o ser 'Cancelado' (la cancelación se maneja en acciones).
    // No debería poder volver a 'En Preparación' fácilmente desde 'A Facturar' sin una lógica específica.
    // Por ahora, si está 'En Preparación' o 'A Facturar', se muestran ambos.
    if ($current_pedido_estado_nombre_lower === 'en preparación' || $current_pedido_estado_nombre_lower === 'a facturar') {
        if ($estado_en_preparacion_id) $estados_para_select[] = ['id' => $estado_en_preparacion_id, 'nombre' => 'En Preparación']; // Nombre exacto de BD
        if ($estado_a_facturar_id) $estados_para_select[] = ['id' => $estado_a_facturar_id, 'nombre' => 'A Facturar']; // Nombre exacto de BD
        
        // Asegurar que el estado actual esté en la lista y seleccionado
        $estado_actual_encontrado_en_select = false;
        foreach ($estados_para_select as $estado_sel) {
            if ($estado_sel['id'] == $pedido['estado_id']) {
                $estado_actual_encontrado_en_select = true;
                break;
            }
        }
        // Si el estado actual no es ni 'en preparación' ni 'a facturar' (raro, pero por si acaso), añadirlo
        if (!$estado_actual_encontrado_en_select) {
             foreach ($todos_los_estados as $est_todos) {
                if ($est_todos['id'] == $pedido['estado_id']) {
                    array_unshift($estados_para_select, $est_todos);
                    break;
                }
            }
        }
    } else { // Si es otro estado (ej. Pendiente, si aún existe y no fue migrado)
        foreach ($todos_los_estados as $est_todos) { // Mostrar todos los no finales por si acaso
             if (strtolower($est_todos['nombre']) !== 'cancelado' && strtolower($est_todos['nombre']) !== 'facturado') {
                 $estados_para_select[] = $est_todos;
             }
        }
    }
} else { // Creando nuevo pedido
    if ($estado_en_preparacion_id) {
        $estados_para_select[] = ['id' => $estado_en_preparacion_id, 'nombre' => 'En Preparación']; // Nombre exacto de BD
    }
}
// Filtrar duplicados por si acaso (manteniendo el primero encontrado, que podría ser el estado actual)
if (!empty($estados_para_select)) {
    $estados_para_select = array_values(array_unique($estados_para_select, SORT_REGULAR));
}

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php displayGlobalMessages(); // Para mensajes de error de sesión ?>
        
        <form action="pedido_acciones.php" method="POST" id="pedidoForm" class="needs-validation" novalidate>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="accion" value="<?= $editMode ? 'actualizar' : 'crear' ?>">
            <?php if ($editMode): ?>
                <input type="hidden" name="pedido_id" value="<?= htmlspecialchars($pedido['id']) ?>">
            <?php endif; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Detalles del Pedido</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="cliente_id" class="form-label">Cliente <span class="text-danger">*</span></label>
                                    <select class="form-select" id="cliente_id" name="pedido[cliente_id]" required>
                                        <option value="">Seleccione un cliente...</option>
                                        <?php foreach ($clientes as $cli): ?>
                                            <option value="<?= htmlspecialchars($cli['id']) ?>" <?= ($pedido['cliente_id'] == $cli['id']) ? 'selected' : '' ?> data-ruc="<?= htmlspecialchars($cli['ruc_ci']) ?>">
                                                <?= htmlspecialchars($cli['apellido'] . ', ' . $cli['nombre']) ?> (<?= htmlspecialchars($cli['ruc_ci']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Por favor, seleccione un cliente.</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="numero_pedido" class="form-label">Nº Pedido <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="numero_pedido" name="pedido[numero_pedido]" value="<?= htmlspecialchars($pedido['numero_pedido']) ?>" readonly required>
                                    <div class="invalid-feedback">Ingrese un número de pedido.</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label for="fecha_pedido" class="form-label">Fecha del Pedido <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="fecha_pedido" name="pedido[fecha_pedido]" value="<?= htmlspecialchars($pedido['fecha_pedido']) ?>" required>
                                    <div class="invalid-feedback">Seleccione la fecha del pedido.</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="estado_id" class="form-label">Estado del Pedido <span class="text-danger">*</span></label>
                                <select class="form-select" id="estado_id" name="pedido[estado_id]" required 
                                    <?= ($editMode && ($current_pedido_estado_nombre_lower === 'facturado' || $current_pedido_estado_nombre_lower === 'cancelado')) ? 'disabled' : '' ?> 
                                    <?= (!$editMode && count($estados_para_select) === 1) ? 'disabled' : '' ?>>
                                    <?php foreach ($estados_para_select as $estado_opt): ?>
                                        <option value="<?= htmlspecialchars($estado_opt['id']) ?>" <?= ($pedido['estado_id'] == $estado_opt['id']) ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($estado_opt['nombre'])) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (count($estados_para_select) <= 1 && !$editMode): // Si es nuevo y solo hay un estado (pendiente), enviar oculto ?>
                                    <input type="hidden" name="pedido[estado_id]" value="<?= htmlspecialchars($pedido['estado_id']) ?>">
                                <?php endif; ?>
                                <div class="invalid-feedback">Seleccione un estado para el pedido.</div>
                            </div>

                            <div class="mb-3">
                                <label for="observaciones" class="form-label">Observaciones</label>
                                <textarea class="form-control" id="observaciones" name="pedido[observaciones]" rows="2"><?= htmlspecialchars($pedido['observaciones']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                            <h6 class="m-0 font-weight-bold text-primary">Productos del Pedido</h6>
                            <?php if (!($editMode && $current_pedido_estado_nombre_lower === 'a facturar')): // No añadir nuevos items si está 'A Facturar' ?>
                            <button type="button" class="btn btn-sm btn-success" id="addProductBtn">
                                <i class="bi bi-plus-circle-fill me-1"></i> Añadir Producto
                            </button>
                            <?php else: ?>
                                <span class="text-muted small">Para añadir nuevos productos, el pedido debe estar 'En Preparación'.</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm" id="pedidoItemsTable">
                                    <thead>
                                        <tr>
                                            <th style="width: 35%;">Producto</th>
                                            <th style="width: 15%;" class="text-center">Cant. Solicitada</th>
                                            <th style="width: 15%;" class="text-center">Cant. a Facturar</th>
                                            <th style="width: 15%;" class="text-end">Precio Unit.</th>
                                            <th style="width: 15%;" class="text-end">Subtotal</th>
                                            <th style="width: 5%;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="pedidoItemsBody">
                                        <?php if (!empty($pedido_items_data)):
                                            foreach ($pedido_items_data as $index => $item_data):
                                                // Asegurar que cantidad_solicitada existe, si no, usar cantidad como fallback (para datos antiguos antes de migración)
                                                $cantidad_solicitada_item = $item_data['cantidad_solicitada'] ?? $item_data['cantidad']; 
                                                $cantidad_a_facturar_item = $item_data['cantidad'];
                                        ?>
                                        <tr class="pedido-item-row <?= (float)$cantidad_a_facturar_item == 0 ? 'item-anulado-visual' : '' ?>" data-index="<?= $index ?>" data-db-id="<?= htmlspecialchars($item_data['id'] ?? '') ?>">
                                            <td>
                                                <input type="hidden" name="items[<?= $index ?>][id]" value="<?= htmlspecialchars($item_data['id'] ?? '') ?>"> <!-- Para identificar items existentes al actualizar -->
                                                <input type="hidden" name="items[<?= $index ?>][producto_id]" value="<?= htmlspecialchars($item_data['producto_id']) ?>">
                                                <input type="hidden" name="items[<?= $index ?>][cantidad_solicitada]" class="cantidad-solicitada-hidden" value="<?= htmlspecialchars($cantidad_solicitada_item) ?>">
                                                <input type="text" class="form-control form-control-sm producto-nombre-display" value="<?= htmlspecialchars(($item_data['producto_nombre'] ?? 'Error al cargar nombre') . ' ('.($item_data['producto_codigo'] ?? 'N/A').')') ?>" readonly tabindex="-1">
                                            </td>
                                            <td><input type="number" class="form-control form-control-sm cantidad-solicitada-display text-center" value="<?= htmlspecialchars($cantidad_solicitada_item) ?>" readonly tabindex="-1"></td>
                                            <td><input type="number" name="items[<?= $index ?>][cantidad]" class="form-control form-control-sm cantidad" value="<?= htmlspecialchars($cantidad_a_facturar_item) ?>" min="0" step="any" required></td>
                                            <td><input type="text" name="items[<?= $index ?>][precio_unitario]" class="form-control form-control-sm precio-unitario text-end" value="<?= htmlspecialchars(number_format((float)($item_data['precio_unitario'] ?? 0), 2, '.', '')) ?>" required></td>
                                            <td><input type="text" class="form-control form-control-sm subtotal-display text-end" value="<?= htmlspecialchars(number_format((float)($item_data['subtotal'] ?? 0), 2, '.', '')) ?>" readonly tabindex="-1"></td>
                                            <td>
                                                <?php if (!($editMode && $current_pedido_estado_nombre_lower === 'a facturar')): // No eliminar físicamente si está 'A Facturar', solo poner a 0 ?>
                                                <button type="button" class="btn btn-sm btn-danger removeItemBtn" title="Eliminar ítem"><i class="bi bi-trash-fill"></i></button>
                                                <?php else: ?>
                                                <button type="button" class="btn btn-sm btn-warning setToZeroBtn" title="Poner cantidad a cero"><i class="bi bi-arrow-down-circle"></i></button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php 
                                            endforeach;
                                        endif; 
                                        ?>
                                    </tbody>
                                </table>
                            </div>
                            <div class="text-center mt-2 <?= !empty($pedido_items_data) ? 'd-none' : '' ?>" id="noItemsMessage">
                                <p class="text-muted">Aún no se han añadido productos al pedido.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Resumen y Totales</h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3 row">
                                <label for="pedido_subtotal_display" class="col-sm-5 col-form-label">Subtotal:</label>
                                <div class="col-sm-7">
                                    <input type="text" readonly class="form-control-plaintext text-end fw-bold" id="pedido_subtotal_display" value="<?= htmlspecialchars(formatCurrency($pedido['subtotal'])) ?>">
                                    <input type="hidden" name="pedido[subtotal]" id="pedido_subtotal" value="<?= htmlspecialchars($pedido['subtotal']) ?>">
                                </div>
                            </div>
                            <div class="mb-3 row">
                                <label for="pedido_impuestos_input" class="col-sm-5 col-form-label">Impuestos:</label>
                                <div class="col-sm-7">
                                     <input type="number" step="0.01" class="form-control text-end" id="pedido_impuestos_input" name="pedido[impuestos]" value="<?= htmlspecialchars(number_format((float)($pedido['impuestos'] ?? 0), 2, '.', '')) ?>">
                                </div>
                            </div>
                            <hr>
                            <div class="mb-3 row">
                                <label for="pedido_total_display" class="col-sm-5 col-form-label h5">TOTAL:</label>
                                <div class="col-sm-7">
                                    <input type="text" readonly class="form-control-plaintext text-end fw-bold h5" id="pedido_total_display" value="<?= htmlspecialchars(formatCurrency($pedido['total'])) ?>">
                                    <input type="hidden" name="pedido[total]" id="pedido_total" value="<?= htmlspecialchars($pedido['total']) ?>">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer text-end">
                            <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-save-fill me-1"></i> <?= $editMode ? 'Actualizar Pedido' : 'Guardar Pedido' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>

<!-- Modal para buscar productos -->
<div class="modal fade" id="buscarProductoModal" tabindex="-1" aria-labelledby="buscarProductoModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="buscarProductoModalLabel">Buscar y Añadir Productos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="productSearchInput" class="form-control" placeholder="Buscar por nombre, código...">
                </div>
                <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                    <table class="table table-sm table-hover" id="availableProductsTable">
                        <thead>
                            <tr><th>Producto</th><th>Código</th><th class="text-end">Precio</th><th class="text-center">Stock</th><th>Acción</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($productos_disponibles as $prod): ?>
                            <tr data-product-id="<?= htmlspecialchars($prod['id']) ?>" 
                                data-nombre="<?= htmlspecialchars($prod['nombre']) ?>" 
                                data-codigo="<?= htmlspecialchars($prod['codigo']) ?>" 
                                data-precio="<?= htmlspecialchars($prod['precio_venta']) ?>" 
                                data-stock="<?= htmlspecialchars($prod['stock']) ?>">
                                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                <td><?= htmlspecialchars($prod['codigo']) ?></td>
                                <td class="text-end"><?= htmlspecialchars(formatCurrency($prod['precio_venta'])) ?></td>
                                <td class="text-center"><?= htmlspecialchars($prod['stock']) ?></td>
                                <td><button type="button" class="btn btn-xs btn-success addProductFromModalBtn" <?= ($prod['stock'] <= 0) ? 'disabled' : '' ?>>Añadir</button></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Lógica de JS para añadir/eliminar items, calcular totales, etc.
// Esta lógica es compleja y se recomienda mantenerla en un archivo .js separado e incluirlo.
// Por brevedad, se omite aquí pero es crucial para la funcionalidad del formulario.
// Asegúrate de que el JS maneje:
// - Añadir filas de items desde el modal de búsqueda.
// - Eliminar filas de items.
// - Recalcular subtotal, impuestos (si es automático), y total cuando cambian cantidades o precios, o se añaden/eliminan items.
// - Validar que no se añadan más items de los que hay en stock (opcionalmente, validación en tiempo real).
// - Actualizar el mensaje "Aún no se han añadido productos" cuando corresponda.
// - Filtrado de productos en el modal.

function addProductRow(productoId = '', productoNombre = '', cantidad = 1, precioUnitario = 0, cantidadSolicitada = null) {
    const tableBody = document.getElementById('pedidoItemsBody');
    const newIndex = tableBody.rows.length > 0 ? (parseInt(tableBody.rows[tableBody.rows.length - 1].dataset.index) + 1) : 0;
    const isCreating = !<?= $editMode ? 'true' : 'false' ?>; // true if creating new order

    const row = document.createElement('tr');
    row.classList.add('pedido-item-row');
    row.dataset.index = newIndex;

    if (cantidadSolicitada === null) cantidadSolicitada = cantidad; // Si es nuevo item, solicitada = a facturar

    row.innerHTML = `
        <td>
            <input type="hidden" name="items[${newIndex}][id]" value=""> <!-- Nuevo item no tiene ID de BD aun -->
            <input type="hidden" name="items[${newIndex}][producto_id]" class="producto-id-hidden" value="${productoId}">
            <input type="hidden" name="items[${newIndex}][cantidad_solicitada]" class="cantidad-solicitada-hidden" value="${cantidadSolicitada}">
            <select name="items[${newIndex}][producto_select]" class="form-select form-select-sm producto-select" required>
                <option value="">Seleccione producto...</option>
                <?php foreach ($productos_disponibles as $p): ?>
                    <option value="<?= htmlspecialchars($p['id']) ?>" data-precio="<?= htmlspecialchars($p['precio_venta']) ?>" data-nombre="<?= htmlspecialchars($p['nombre'] . ' (' . $p['codigo'] . ')') ?>" data-stock="<?= htmlspecialchars($p['stock']) ?>">
                        <?= htmlspecialchars($p['nombre'] . ' (' . $p['codigo'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Seleccione un producto.</div>
        </td>
        <td><input type="number" class="form-control form-control-sm cantidad-solicitada-display text-center" value="${cantidadSolicitada}" readonly tabindex="-1"></td>
        <td><input type="number" name="items[${newIndex}][cantidad]" class="form-control form-control-sm cantidad" value="${cantidad}" min="0" step="any" required></td>
        <td><input type="text" name="items[${newIndex}][precio_unitario]" class="form-control form-control-sm precio-unitario text-end" value="${parseFloat(precioUnitario).toFixed(2)}" required></td>
        <td><input type="text" class="form-control form-control-sm subtotal-display text-end" value="0.00" readonly tabindex="-1"></td>
        <td><button type="button" class="btn btn-sm btn-danger removeItemBtn" title="Eliminar ítem"><i class="bi bi-trash-fill"></i></button></td>
    `;

    tableBody.appendChild(row);
    
    const productoSelect = row.querySelector('.producto-select');
    if (productoId) {
        productoSelect.value = productoId;
        // Simular el cambio para que se actualice el precio y el nombre si es necesario (aunque ya los pasamos)
        // Esto es más para si se cargan items existentes con select en lugar de texto readonly
    } else {
        // Si es un item nuevo y no se seleccionó producto, no podemos llenar la cantidad solicitada display aún
        row.querySelector('.cantidad-solicitada-display').value = '-';
    }


    updateRemoveItemButtonListeners();
    updateFieldListeners(row);
    calculateTotals();
    toggleNoItemsMessage();
    updateDynamicSelects(); // Para inicializar TomSelect si se usa
}

// Botón Añadir Producto
const addProductBtn = document.getElementById('addProductBtn');
if (addProductBtn) {
    addProductBtn.addEventListener('click', function() {
        addProductRow('', '', 1, 0, 1); // cantidadSolicitada = 1 para nuevos items por defecto
    });
}

function updateRemoveItemButtonListeners() {
    document.querySelectorAll('.removeItemBtn').forEach(btn => {
        btn.removeEventListener('click', handleRemoveItem); // Evitar duplicados
        btn.addEventListener('click', handleRemoveItem);
    });
    document.querySelectorAll('.setToZeroBtn').forEach(btn => {
        btn.removeEventListener('click', handleSetToZero);
        btn.addEventListener('click', handleSetToZero);
    });
}

function handleSetToZero(event) {
    const row = event.target.closest('tr.pedido-item-row');
    if (row) {
        const cantidadInput = row.querySelector('.cantidad');
        if (cantidadInput) {
            cantidadInput.value = 0;
            cantidadInput.dispatchEvent(new Event('input')); // Para que se recalcule el total
            row.classList.add('item-anulado-visual');
        }
    }
}

function handleRemoveItem(event) {
    const row = event.target.closest('tr.pedido-item-row');
    const isCreating = !<?= $editMode ? 'true' : 'false' ?>;
    const itemDbId = row.dataset.dbId; // ID del item en la BD

    // Si es un item nuevo (no tiene dbId) O estamos creando un nuevo pedido, se puede borrar la fila.
    // O si el estado es "En Preparación" (permitiendo borrar items antes del primer guardado como "A Facturar")
    const estadoActualPedidoJS = document.getElementById('estado_id') ? document.getElementById('estado_id').options[document.getElementById('estado_id').selectedIndex].text.toLowerCase() : '';
    
    if ((!itemDbId || itemDbId === '') || isCreating || estadoActualPedidoJS.includes('en preparación')) {
         if (confirm('¿Está seguro de que desea eliminar este ítem del pedido?')) {
            row.remove();
            calculateTotals();
            toggleNoItemsMessage();
        }
    } else {
        // Si el item ya existe en la BD y el pedido no está "En Preparación" (ej. "A Facturar"), solo poner cantidad a 0.
        const cantidadInput = row.querySelector('.cantidad');
        if (cantidadInput) {
            if (parseFloat(cantidadInput.value) !== 0) {
                 if (confirm('Este ítem ya existe. ¿Desea anularlo poniendo la cantidad a facturar en 0?')) {
                    cantidadInput.value = 0;
                    cantidadInput.dispatchEvent(new Event('input')); // Recalcular
                    row.classList.add('item-anulado-visual');
                }
            } else {
                alert('Este ítem ya tiene cantidad 0.');
            }
        } else {
            // Fallback si no se encuentra el input de cantidad, aunque no debería pasar.
             if (confirm('¿Está seguro de que desea eliminar este ítem del pedido?')) {
                row.remove();
                calculateTotals();
                toggleNoItemsMessage();
            }
        }
    }
}

// Inicializar listeners para items existentes (si los hay)
updateRemoveItemButtonListeners();
</script>

<style>
    .item-anulado-visual {
        background-color: #f8d7da !important; /* Un rojo claro */
        text-decoration: line-through;
    }
    .item-anulado-visual input,
    .item-anulado-visual select {
        text-decoration: line-through;
    }
    .cantidad-solicitada-display {
        background-color: #e9ecef; /* Un gris claro para indicar no editable */
        cursor: default;
    }
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 