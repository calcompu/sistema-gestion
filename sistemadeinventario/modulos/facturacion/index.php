<?php
require_once __DIR__ . '/../../config.php';
requireLogin();
requirePermission('facturacion', 'view_list');

$pageTitle = "Gestión de Facturas";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Obtener el ID del estado de pedido 'Completado' para el enlace de "Nueva Factura"
$stmt_estado_completado = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = ?");
$stmt_estado_completado->execute(['completado']);
$estado_completado_pedido_id = $stmt_estado_completado->fetchColumn();
// Si no se encuentra, el enlace podría no funcionar como se espera, pero no es crítico para el listado en sí.

// Obtener el ID del estado de pedido 'A Facturar' para el enlace de "Nueva Factura"
$stmt_estado_a_facturar = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = ?");
$stmt_estado_a_facturar->execute(['a facturar']);
$estado_a_facturar_pedido_id = $stmt_estado_a_facturar->fetchColumn();

// Configuración de paginación
$elementosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Filtros y búsqueda
$busquedaNumero = isset($_GET['numero_factura']) ? sanitizeInput($_GET['numero_factura']) : '';
$filtroCliente = isset($_GET['cliente_id']) ? (int)$_GET['cliente_id'] : '';
$filtroEstado = isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : ''; // ej: pendiente_pago, pagada, anulada
$filtroFechaDesde = isset($_GET['fecha_desde']) ? sanitizeInput($_GET['fecha_desde']) : '';
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? sanitizeInput($_GET['fecha_hasta']) : '';

$whereClauses = [];
$params = [];

if (!empty($busquedaNumero)) {
    $whereClauses[] = "f.numero_factura LIKE ?";
    $params[] = "%{$busquedaNumero}%";
}
if (!empty($filtroCliente)) {
    $whereClauses[] = "f.cliente_id = ?";
    $params[] = $filtroCliente;
}
if (!empty($filtroEstado)) {
    $whereClauses[] = "f.estado = ?";
    $params[] = $filtroEstado;
}
if (!empty($filtroFechaDesde)) {
    $whereClauses[] = "f.fecha_emision >= ?";
    $params[] = $filtroFechaDesde; // Asume formato YYYY-MM-DD
}
if (!empty($filtroFechaHasta)) {
    $whereClauses[] = "f.fecha_emision <= ?";
    $params[] = $filtroFechaHasta; // Asume formato YYYY-MM-DD
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de facturas para paginación
$stmtTotal = $pdo->prepare("SELECT COUNT(f.id) FROM facturas f $whereSql");
$stmtTotal->execute($params);
$totalFacturas = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalFacturas / $elementosPorPagina);

// Obtener facturas para la página actual
$sql = "SELECT f.*, c.nombre as cliente_nombre, c.apellido as cliente_apellido 
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id
        $whereSql 
        ORDER BY f.fecha_emision DESC, f.id DESC
        LIMIT ? OFFSET ?";
$paramsPaged = array_merge($params, [$elementosPorPagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsPaged);
$facturas = $stmt->fetchAll();

// Obtener clientes para el filtro
$clientes = $pdo->query("SELECT id, nombre, apellido FROM clientes WHERE activo = 1 ORDER BY apellido ASC, nombre ASC")->fetchAll();

// Estados de factura (ejemplo)
$estadosFactura = [
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

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (hasPermission('facturacion', 'create_from_order')): ?>
            <a href="<?= APP_URL ?>/modulos/Pedidos/index.php?estado_id=<?= $estado_a_facturar_pedido_id ?? '' ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Nueva Factura (desde Pedido)
            </a>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros y Búsqueda de Facturas</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <div class="col-md-2">
                        <label for="numero_factura" class="form-label">Nº Factura</label>
                        <input type="text" class="form-control" id="numero_factura" name="numero_factura" value="<?= htmlspecialchars($busquedaNumero) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="cliente_id" class="form-label">Cliente</label>
                        <select class="form-select" id="cliente_id" name="cliente_id">
                            <option value="">Todos</option>
                            <?php foreach ($clientes as $cli): ?>
                                <option value="<?= $cli['id'] ?>" <?= $filtroCliente == $cli['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cli['apellido'] . ', ' . $cli['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <?php foreach ($estadosFactura as $key => $value): ?>
                                <option value="<?= $key ?>" <?= $filtroEstado == $key ? 'selected' : '' ?>><?= htmlspecialchars($value) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_desde" class="form-label">Fecha Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($filtroFechaDesde) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_hasta" class="form-label">Fecha Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($filtroFechaHasta) ?>">
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2 w-100"><i class="bi bi-search"></i></button>
                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Facturas (<?= $totalFacturas ?>)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableFacturas" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nº Factura</th>
                                <th>Cliente</th>
                                <th>Fecha Emisión</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($facturas)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No se encontraron facturas con los filtros aplicados.
                                    <?php if (hasPermission('facturacion', 'create_from_order')): ?>
                                        Puede <a href="<?= APP_URL ?>/modulos/Pedidos/index.php?estado_id=<?= $estado_a_facturar_pedido_id ?? '' ?>">crear una nueva factura</a> desde un pedido en estado 'A Facturar'.
                                    <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($facturas as $factura): ?>
                                    <tr>
                                        <td>
                                            <?php if (hasPermission('facturacion', 'view_detail')): ?>
                                            <a href="factura_detalle.php?id=<?= $factura['id'] ?>" title="Ver Detalle Factura">
                                                <?= htmlspecialchars($factura['numero_factura']) ?>
                                            </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($factura['numero_factura']) ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(($factura['cliente_apellido'] ?? '') . ', ' . ($factura['cliente_nombre'] ?? 'N/A')) ?></td>
                                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></td>
                                        <td class="text-end"><?= formatCurrency($factura['total']) ?></td>
                                        <td class="text-center">
                                            <?php 
                                            $estadoTexto = $estadosFactura[$factura['estado']] ?? ucfirst($factura['estado']);
                                            $badgeClass = $estadoBadges[$factura['estado']] ?? 'bg-light text-dark';
                                            echo "<span class=\"badge {$badgeClass}\">" . htmlspecialchars($estadoTexto) . "</span>";
                                            ?>
                                        </td>
                                        <td class="text-center actions-btn-group">
                                            <?php if (hasPermission('facturacion', 'view_detail')): ?>
                                            <a href="factura_detalle.php?id=<?= $factura['id'] ?>" class="btn btn-sm btn-info me-1" title="Ver Detalle"><i class="bi bi-eye-fill"></i></a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('facturacion', 'print')): // Asumiendo un permiso 'print' para la factura ?>
                                                <a href="imprimir.php?id=<?= $factura['id'] ?>&csrf_token=<?= htmlspecialchars($_SESSION['csrf_token']) ?>" target="_blank" class="btn btn-sm btn-secondary me-1" title="Imprimir Factura"><i class="bi bi-printer-fill"></i></a>
                                            <?php endif; ?>
                                            <?php if ($factura['estado'] !== 'anulada' && hasPermission('facturacion', 'void')): ?>
                                                <form action="factura_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea ANULAR esta factura? Esta acción no se puede deshacer fácilmente.');">
                                                    <input type="hidden" name="accion" value="anular">
                                                    <input type="hidden" name="factura_id" value="<?= $factura['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Anular Factura"><i class="bi bi-x-octagon-fill"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <!-- Podrían ir más acciones, como registrar pago, enviar por email, etc. -->
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación de facturas">
                    <ul class="pagination justify-content-center mt-4">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['pagina']);
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