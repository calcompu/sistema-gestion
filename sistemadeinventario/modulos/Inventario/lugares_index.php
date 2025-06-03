<?php
require_once __DIR__ . '/../../config.php';
// requireLogin();
requirePermission('inventario_lugares', 'view_list');

$pageTitle = "Gestión de Lugares/Ubicaciones";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Configuración de paginación
$elementosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Filtros y búsqueda
$busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$filtroEstado = isset($_GET['estado']) ? (int)$_GET['estado'] : ''; // 1 para activos, 0 para inactivos, '' para todos

$whereClauses = [];
$params = [];

if (!empty($busqueda)) {
    $whereClauses[] = "(l.nombre LIKE ? OR l.descripcion LIKE ?)";
    $params[] = "%{$busqueda}%";
    $params[] = "%{$busqueda}%";
}

if ($filtroEstado !== '') {
    $whereClauses[] = "l.activo = ?";
    $params[] = $filtroEstado;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de lugares para paginación
$stmtTotal = $pdo->prepare("SELECT COUNT(id) FROM lugares $whereSql");
$stmtTotal->execute($params);
$totalLugares = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalLugares / $elementosPorPagina);

// Obtener lugares para la página actual
$sql = "SELECT l.id, l.nombre, l.descripcion, l.activo, l.fecha_creacion, l.fecha_actualizacion, COUNT(p.id) as productos_ubicados
        FROM lugares l 
        LEFT JOIN productos p ON l.id = p.lugar_id AND p.activo = 1
        $whereSql 
        GROUP BY l.id, l.nombre, l.descripcion, l.activo, l.fecha_creacion, l.fecha_actualizacion
        ORDER BY l.nombre ASC 
        LIMIT ? OFFSET ?";
$paramsPaged = array_merge($params, [$elementosPorPagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsPaged);
$lugares = $stmt->fetchAll();

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Lugares/Ubicaciones de Almacenamiento</h1>
            <?php if (hasPermission('inventario_lugares', 'create')): ?>
            <a href="lugar_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Lugar
            </a>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros y Búsqueda</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="lugares_index.php" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="busqueda" class="form-label">Buscar Lugar</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre o descripción...">
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="1" <?= ($filtroEstado === 1) ? 'selected' : '' ?>>Activos</option>
                            <option value="0" <?= ($filtroEstado === 0 && $filtroEstado !== '') ? 'selected' : '' ?>>Inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2 w-100"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="lugares_index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Lugares</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableLugares" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Productos Ubicados</th>
                                <?php if (hasPermission('inventario_lugares', 'edit') || hasPermission('inventario_lugares', 'toggle_status') || hasPermission('inventario_lugares', 'delete')): ?>
                                <th class="text-center">Acciones</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($lugares)): ?>
                                <tr>
                                    <td colspan="<?php echo (hasPermission('inventario_lugares', 'edit') || hasPermission('inventario_lugares', 'toggle_status') || hasPermission('inventario_lugares', 'delete')) ? '5' : '4'; ?>" class="text-center">No se encontraron lugares con los filtros aplicados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($lugares as $lugar): ?>
                                    <?php
                                    // La cuenta de productos ubicados ahora viene de la consulta principal
                                    $productosUbicados = $lugar['productos_ubicados'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($lugar['nombre']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($lugar['descripcion'] ?? '')) ?></td>
                                        <td class="text-center">
                                            <?php if ($lugar['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="../Inventario/index.php?lugar_id=<?= $lugar['id'] ?>" class="badge bg-info text-decoration-none">
                                                <?= $productosUbicados ?>
                                            </a>
                                        </td>
                                        <?php if (hasPermission('inventario_lugares', 'edit') || hasPermission('inventario_lugares', 'toggle_status') || hasPermission('inventario_lugares', 'delete')): ?>
                                        <td class="text-center actions-btn-group">
                                            <?php if (hasPermission('inventario_lugares', 'edit')): ?>
                                            <a href="lugar_form.php?id=<?= $lugar['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('inventario_lugares', 'toggle_status')): ?>
                                            <?php if ($lugar['activo']): ?>
                                                <form action="lugar_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea desactivar este lugar? Los productos ubicados aquí no serán eliminados pero podrían quedar sin ubicación visible.');">
                                                    <input type="hidden" name="accion" value="desactivar">
                                                    <input type="hidden" name="lugar_id" value="<?= $lugar['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" title="Desactivar"><i class="bi bi-toggle-off"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <form action="lugar_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea activar este lugar?');">
                                                    <input type="hidden" name="accion" value="activar">
                                                    <input type="hidden" name="lugar_id" value="<?= $lugar['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Activar"><i class="bi bi-toggle-on"></i></button>
                                                </form>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (hasPermission('inventario_lugares', 'delete')): ?>
                                            <form action="lugar_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('ADVERTENCIA: ¿Está seguro de que desea eliminar permanentemente este lugar? Esta acción no se puede deshacer y podría afectar a los productos ubicados aquí. Se recomienda desactivar en su lugar.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="lugar_id" value="<?= $lugar['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Permanentemente" <?= ($productosUbicados > 0) ? 'disabled' : '' ?>><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                            <?php endif; ?>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginación de lugares">
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