<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); 
requirePermission('inventario_categorias', 'view_list');

$pageTitle = "Gestión de Categorías";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Configuración de paginación
$elementosPorPagina = 10;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Filtros y búsqueda
$busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$filtroEstado = isset($_GET['estado']) ? (int)$_GET['estado'] : ''; // 1 para activas, 0 para inactivas, '' para todas

$whereClauses = [];
$params = [];

if (!empty($busqueda)) {
    $whereClauses[] = "(c.nombre LIKE ? OR c.descripcion LIKE ?)"; // Alias c.
    $params[] = "%{$busqueda}%";
    $params[] = "%{$busqueda}%";
}

if ($filtroEstado !== '') {
    $whereClauses[] = "c.activa = ?"; // Alias c.
    $params[] = $filtroEstado;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de categorías para paginación
$stmtTotal = $pdo->prepare("SELECT COUNT(id) FROM categorias $whereSql");
$stmtTotal->execute($params);
$totalCategorias = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalCategorias / $elementosPorPagina);

// Obtener categorías para la página actual
$sql = "SELECT c.id, c.nombre, c.descripcion, c.activa, c.fecha_creacion, c.fecha_actualizacion, COUNT(p.id) as productos_asociados
        FROM categorias c
        LEFT JOIN productos p ON c.id = p.categoria_id AND p.activo = 1
        $whereSql 
        GROUP BY c.id, c.nombre, c.descripcion, c.activa, c.fecha_creacion, c.fecha_actualizacion
        ORDER BY c.nombre ASC 
        LIMIT ? OFFSET ?";
$paramsPaged = array_merge($params, [$elementosPorPagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsPaged);
$categorias = $stmt->fetchAll();

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800">Categorías de Productos</h1>
            <?php if (hasPermission('inventario_categorias', 'create')): ?>
            <a href="categoria_form.php" class="btn btn-primary">
                <i class="bi bi-plus-circle-fill me-1"></i> Nueva Categoría
            </a>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros y Búsqueda</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="categorias_index.php" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="busqueda" class="form-label">Buscar Categoría</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre o descripción...">
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todas</option>
                            <option value="1" <?= ($filtroEstado === 1) ? 'selected' : '' ?>>Activas</option>
                            <option value="0" <?= ($filtroEstado === 0 && $filtroEstado !== '') ? 'selected' : '' ?>>Inactivas</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2 w-100"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="categorias_index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Categorías</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableCategorias" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Productos Asociados</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($categorias)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No se encontraron categorías con los filtros aplicados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($categorias as $categoria): ?>
                                    <?php
                                    // La cuenta de productos asociados ahora viene de la consulta principal
                                    $productosAsociados = $categoria['productos_asociados'];
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($categoria['nombre']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($categoria['descripcion'] ?? '')) ?></td>
                                        <td class="text-center">
                                            <?php if ($categoria['activa']): ?>
                                                <span class="badge bg-success">Activa</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactiva</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="../Inventario/index.php?categoria_id=<?= $categoria['id'] ?>" class="badge bg-info text-decoration-none">
                                                <?= $productosAsociados ?>
                                            </a>
                                        </td>
                                        <td class="text-center actions-btn-group">
                                            <?php if (hasPermission('inventario_categorias', 'edit')): ?>
                                            <a href="categoria_form.php?id=<?= $categoria['id'] ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil-fill"></i></a>
                                            <?php endif; ?>

                                            <?php if ($categoria['activa']): ?>
                                                <?php if (hasPermission('inventario_categorias', 'toggle_status')): // Asumiendo permiso 'toggle_status' ?>
                                                <form action="categoria_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea desactivar esta categoría? Los productos asociados no serán eliminados pero podrían quedar sin categoría visible hasta que se les asigne una nueva o se reactive esta.');">
                                                    <input type="hidden" name="accion" value="desactivar">
                                                    <input type="hidden" name="categoria_id" value="<?= $categoria['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" title="Desactivar"><i class="bi bi-toggle-off"></i></button>
                                                </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <?php if (hasPermission('inventario_categorias', 'toggle_status')): // Asumiendo permiso 'toggle_status' ?>
                                                <form action="categoria_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea activar esta categoría?');">
                                                    <input type="hidden" name="accion" value="activar">
                                                    <input type="hidden" name="categoria_id" value="<?= $categoria['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-success" title="Activar"><i class="bi bi-toggle-on"></i></button>
                                                </form>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                            <?php if (hasPermission('inventario_categorias', 'delete')): ?>
                                            <form action="categoria_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('ADVERTENCIA: ¿Está seguro de que desea eliminar permanentemente esta categoría? Esta acción no se puede deshacer y podría afectar a los productos asociados. Se recomienda desactivar en su lugar.');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="categoria_id" value="<?= $categoria['id'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Permanentemente" <?= ($productosAsociados > 0) ? 'disabled' : '' ?>><i class="bi bi-trash-fill"></i></button>
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
                <nav aria-label="Paginación de categorías">
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