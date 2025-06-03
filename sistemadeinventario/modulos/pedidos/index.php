<?php
require_once __DIR__ . '/../../config.php';
requirePermission('pedidos', 'view_list'); 

$pageTitle = "Gestión de Pedidos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Configuración de paginación
$elementosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Obtener todos los estados de pedido para filtros y lógica
$stmt_todos_estados = $pdo->query("SELECT id, nombre FROM estados_pedido ORDER BY nombre ASC");
$todos_los_estados = $stmt_todos_estados->fetchAll(PDO::FETCH_ASSOC);
$map_estado_id_a_nombre = array_column($todos_los_estados, 'nombre', 'id');
$map_estado_nombre_a_id = array_column($todos_los_estados, 'id', 'nombre');

// Filtros y búsqueda
$busquedaNumero = isset($_GET['numero_pedido']) ? sanitizeInput($_GET['numero_pedido']) : '';
$filtroCliente = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : '';
$filtroEstadoId = isset($_GET['estado_id']) ? (int)$_GET['estado_id'] : ''; // Ahora es estado_id
$filtroFechaDesde = isset($_GET['fecha_desde']) ? sanitizeInput($_GET['fecha_desde']) : '';
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? sanitizeInput($_GET['fecha_hasta']) : '';

$whereClauses = [];
$params = [];

if (!empty($busquedaNumero)) {
    $whereClauses[] = "p.numero_pedido LIKE ?";
    $params[] = "%{$busquedaNumero}%";
}
if (!empty($filtroCliente)) {
    $whereClauses[] = "p.cliente_id = ?";
    $params[] = $filtroCliente;
}
if (!empty($filtroEstadoId)) { // Usar filtroEstadoId
    $whereClauses[] = "p.estado_id = ?";
    $params[] = $filtroEstadoId;
}
if (!empty($filtroFechaDesde)) {
    $whereClauses[] = "DATE(p.fecha_pedido) >= ?"; // Comparar solo la fecha
    $params[] = $filtroFechaDesde;
}
if (!empty($filtroFechaHasta)) {
    $whereClauses[] = "DATE(p.fecha_pedido) <= ?"; // Comparar solo la fecha
    $params[] = $filtroFechaHasta;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de pedidos para paginación (JOIN con estados_pedido para que el filtro de estado funcione si es necesario antes)
// No es estrictamente necesario el JOIN aquí si el filtro ya es por p.estado_id
$stmtTotal = $pdo->prepare("SELECT COUNT(p.id) FROM pedidos p $whereSql");
$stmtTotal->execute($params);
$totalPedidos = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalPedidos / $elementosPorPagina);

// Obtener pedidos para la página actual, incluyendo nombre del cliente y nombre del estado
$sql = "SELECT p.*, 
               CONCAT(c.nombre, ' ', c.apellido) as cliente_nombre_completo, 
               es.nombre as nombre_estado
        FROM pedidos p
        LEFT JOIN clientes c ON p.cliente_id = c.id
        LEFT JOIN estados_pedido es ON p.estado_id = es.id
        $whereSql 
        ORDER BY p.fecha_pedido DESC, p.id DESC
        LIMIT ? OFFSET ?";
$paramsPaged = array_merge($params, [$elementosPorPagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsPaged);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener clientes para el filtro
$clientes_filtro = $pdo->query("SELECT id, nombre, apellido FROM clientes WHERE activo = 1 ORDER BY apellido ASC, nombre ASC")->fetchAll();

// $estadosPedido ya no es necesario hardcodear, se usa $todos_los_estados para el select de filtros

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (hasPermission('pedidos', 'create')): ?>
            <a href="pedido_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Pedido
            </a>
            <?php endif; ?>
        </div>

        <?php 
        if (isset($_GET['status'])) {
            if ($_GET['status'] == 'success_create') showAlert('Pedido creado exitosamente.', 'success');
            if ($_GET['status'] == 'success_update') showAlert('Pedido actualizado exitosamente.', 'success');
            if ($_GET['status'] == 'success_cancel') showAlert('Pedido cancelado exitosamente.', 'info');
            if ($_GET['status'] == 'error') showAlert('Ocurrió un error al procesar la solicitud.', 'danger');
        }
        ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros y Búsqueda de Pedidos</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="numero_pedido" class="form-label">Nº Pedido</label>
                        <input type="text" class="form-control" id="numero_pedido" name="numero_pedido" value="<?= htmlspecialchars($busquedaNumero) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id">
                            <option value="">Todos</option>
                            <?php foreach ($clientes_filtro as $cli): // Usar $clientes_filtro ?>
                                <option value="<?= $cli['id'] ?>" <?= $filtroCliente == $cli['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cli['apellido'] . ', ' . $cli['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="estado_id" class="form-label">Estado</label> 
                        <select class="form-select" id="estado_id" name="estado_id"> // name="estado_id"
                            <option value="">Todos</option>
                            <?php foreach ($todos_los_estados as $estado_opt): // Usar $todos_los_estados ?>
                                <option value="<?= htmlspecialchars($estado_opt['id']) ?>" <?= $filtroEstadoId == $estado_opt['id'] ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($estado_opt['nombre'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_desde" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($filtroFechaDesde) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($filtroFechaHasta) ?>">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2 w-100"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Pedidos (<?= $totalPedidos ?>)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTablePedidos" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nº Pedido</th>
                                <th>Cliente</th>
                                <th>Fecha</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pedidos)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No se encontraron pedidos. 
                                        <?php if (empty($busquedaNumero) && empty($filtroCliente) && empty($filtroEstadoId) && empty($filtroFechaDesde) && empty($filtroFechaHasta) && hasPermission('pedidos', 'create')): ?>
                                            <a href="pedido_form.php">Crear un nuevo pedido</a>.
                                        <?php elseif (!empty($busquedaNumero) || !empty($filtroCliente) || !empty($filtroEstadoId) || !empty($filtroFechaDesde) || !empty($filtroFechaHasta)) : ?>
                                            Intente modificar los filtros.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pedidos as $pedido): 
                                    $estadoActualNombre = strtolower($pedido['nombre_estado'] ?? '');
                                ?>
                                    <tr>
                                        <td>
                                            <?php if (hasPermission('pedidos', 'view_detail')): ?>
                                                <a href="pedido_detalle.php?id=<?= $pedido['id'] ?>">
                                                    <?= htmlspecialchars($pedido['numero_pedido']) ?>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($pedido['numero_pedido']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($pedido['cliente_nombre_completo'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['fecha_pedido']))) ?></td>
                                        <td class="text-end"><?= htmlspecialchars(formatCurrency($pedido['total'])) ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $estadoTexto = ucfirst(htmlspecialchars($pedido['nombre_estado'] ?? 'Desconocido'));
                                            $badgeClass = 'bg-secondary'; // Default
                                            switch ($estadoActualNombre) { // Usar $estadoActualNombre (nombre en minúscula)
                                                case 'pendiente': $badgeClass = 'bg-warning text-dark'; break;
                                                case 'procesando': $badgeClass = 'bg-info text-dark'; break; // Asumiendo que 'procesando' existe
                                                case 'completado': $badgeClass = 'bg-success'; break;
                                                case 'facturado': $badgeClass = 'bg-primary'; break;
                                                case 'cancelado': $badgeClass = 'bg-danger'; break;
                                            }
                                            echo "<span class=\"badge {$badgeClass}\">" . $estadoTexto . "</span>";
                                            ?>
                                        </td>
                                        <td class="text-center actions-btn-group">
                                            <?php if (hasPermission('pedidos', 'view_detail')): ?>
                                                <a href="pedido_detalle.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-info me-1" title="Ver Detalles"><i class="bi bi-eye-fill"></i></a>
                                            <?php endif; ?>
                                            <?php 
                                            // IDs de estados para comparación (asumiendo que $map_estado_nombre_a_id está disponible)
                                            $id_pendiente = $map_estado_nombre_a_id['pendiente'] ?? null;
                                            $id_procesando = $map_estado_nombre_a_id['procesando'] ?? null; // Asegúrate que 'procesando' exista como nombre en BD
                                            $id_completado = $map_estado_nombre_a_id['completado'] ?? null;
                                            $id_facturado = $map_estado_nombre_a_id['facturado'] ?? null;
                                            $id_cancelado = $map_estado_nombre_a_id['cancelado'] ?? null;

                                            if (($pedido['estado_id'] == $id_pendiente || $pedido['estado_id'] == $id_procesando) && hasPermission('pedidos', 'edit')): ?>
                                                <a href="pedido_form.php?id=<?= $pedido['id'] ?>" class="btn btn-sm btn-warning me-1" title="Editar Pedido"><i class="bi bi-pencil-fill"></i></a>
                                            <?php endif; ?>
                                            <?php 
                                            // Para facturar, asumimos que necesitamos el ID de pedido y que el estado sea 'completado'
                                            // También necesitamos verificar si ya existe una factura para este pedido (no implementado aquí aún)
                                            if ($pedido['estado_id'] == $id_completado && hasPermission('facturacion', 'create_from_order')): ?>
                                                <a href="../Facturacion/factura_form.php?pedido_id=<?= $pedido['id'] ?>" class="btn btn-sm btn-success me-1" title="Generar Factura"><i class="bi bi-receipt"></i></a>
                                            <?php endif; ?>
                                             <?php if ($pedido['estado_id'] != $id_cancelado && $pedido['estado_id'] != $id_facturado && hasPermission('pedidos', 'cancel')):
                                                $csrfToken = htmlspecialchars($_SESSION['csrf_token']);    
                                             ?>
                                                <form action="pedido_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea cancelar este pedido?');">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="accion" value="cancelar">
                                                    <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Cancelar Pedido"><i class="bi bi-x-circle-fill"></i></button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación de pedidos">
                    <ul class="pagination justify-content-center mt-4">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['pagina']); // Eliminar pagina para no duplicar
                        // Asegurar que el filtro de estado_id se mantenga si está presente
                        if (isset($queryParams['estado'])) { // Si el antiguo 'estado' (nombre) aún está en GET, eliminarlo
                            unset($queryParams['estado']);
                        }
                        // if (!empty($filtroEstadoId)) { $queryParams['estado_id'] = $filtroEstadoId; } // Ya debería estar en $_GET

                        $queryString = http_build_query($queryParams);
                        ?>
                        <li class="page-item <?= ($paginaActual <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&<?= $queryString ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                        <li class="page-item <?= ($i == $paginaActual) ? 'active' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $i ?>&<?= $queryString ?>"><?= $i ?></a>
                        </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($paginaActual >= $totalPaginas) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual + 1 ?>&<?= $queryString ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 