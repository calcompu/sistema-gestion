<?php
require_once __DIR__ . '/../../config.php';
requirePermission('clientes', 'view_list');

$pageTitle = "Gestión de Clientes";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Configuración de paginación
$elementosPorPagina = 15;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Filtros y búsqueda
$busqueda = isset($_GET['q']) ? sanitizeInput($_GET['q']) : '';
$filtroEstado = isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : ''; // '1' para activos, '0' para inactivos, '' para todos

$whereClauses = [];
$params = [];

if (!empty($busqueda)) {
    $whereClauses[] = "(c.nombre LIKE ? OR c.apellido LIKE ? OR c.numero_documento LIKE ? OR c.email LIKE ?)";
    $params = array_merge($params, ["%{$busqueda}%", "%{$busqueda}%", "%{$busqueda}%", "%{$busqueda}%"]);
}

if ($filtroEstado !== '') {
    $whereClauses[] = "c.activo = ?";
    $params[] = (int)$filtroEstado;
}

$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de clientes para paginación
$stmtTotal = $pdo->prepare("SELECT COUNT(c.id) FROM clientes c $whereSql");
$stmtTotal->execute($params);
$totalClientes = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalClientes / $elementosPorPagina);

// Obtener clientes para la página actual
$sql = "SELECT c.*, td.codigo as tipo_documento_codigo, td.nombre as tipo_documento_nombre 
        FROM clientes c
        LEFT JOIN tipos_documento td ON c.tipo_documento_id = td.id
        $whereSql
        ORDER BY c.apellido ASC, c.nombre ASC
        LIMIT ? OFFSET ?";

$paramsPaged = array_merge($params, [$elementosPorPagina, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($paramsPaged);
$clientes = $stmt->fetchAll();

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (hasPermission('clientes', 'create')): ?>
            <a href="cliente_form.php" class="btn btn-primary">
                <i class="bi bi-person-plus-fill me-1"></i> Nuevo Cliente
            </a>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros y Búsqueda de Clientes</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="q" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="q" name="q" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Nombre, apellido, núm. documento, email...">
                    </div>
                    <div class="col-md-3">
                        <label for="estado" class="form-label">Estado</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="1" <?= ($filtroEstado === '1') ? 'selected' : '' ?>>Activos</option>
                            <option value="0" <?= ($filtroEstado === '0') ? 'selected' : '' ?>>Inactivos</option>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-info me-2 w-100"><i class="bi bi-search"></i> Filtrar/Buscar</button>
                        <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Clientes (<?= $totalClientes ?>)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableClientes" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Nombre Completo</th>
                                <th>Tipo Doc.</th>
                                <th>Núm. Documento</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clientes)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No se encontraron clientes.
                                        <?php if(!empty($busqueda) || !empty($filtroEstado)): ?>
                                            Intente modificar los filtros.
                                        <?php elseif (hasPermission('clientes', 'create')): ?>
                                            <a href="cliente_form.php">Crear un nuevo cliente</a>.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clientes as $cliente): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($cliente['apellido'] . ', ' . $cliente['nombre']) ?></td>
                                        <td><?= htmlspecialchars($cliente['tipo_documento_codigo'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($cliente['numero_documento'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($cliente['email'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($cliente['telefono'] ?? 'N/A') ?></td>
                                        <td class="text-center">
                                            <?php if ($cliente['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center actions-btn-group">
                                            <?php if (hasPermission('clientes', 'edit')): ?>
                                                <a href="cliente_form.php?id=<?= $cliente['id'] ?>" class="btn btn-sm btn-warning me-1" title="Editar Cliente"><i class="bi bi-pencil-fill"></i></a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('clientes', 'toggle_status')): ?>
                                                <?php if ($cliente['activo']): ?>
                                                    <form action="cliente_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea desactivar este cliente?');">
                                                        <input type="hidden" name="accion" value="desactivar">
                                                        <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-secondary me-1" title="Desactivar Cliente"><i class="bi bi-toggle-off"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <form action="cliente_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea activar este cliente?');">
                                                        <input type="hidden" name="accion" value="activar">
                                                        <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-success me-1" title="Activar Cliente"><i class="bi bi-toggle-on"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if (hasPermission('clientes', 'delete')): ?>
                                                <form action="cliente_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¡ATENCIÓN! Esta acción eliminará permanentemente al cliente y no se podrá deshacer. ¿Está seguro?');">
                                                    <input type="hidden" name="accion" value="eliminar_permanente">
                                                    <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar Permanentemente"><i class="bi bi-trash3-fill"></i></button>
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
                <nav aria-label="Paginación de clientes">
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
<?php /* El script de confirmación ya no es necesario si los onsubmit están en los forms
<script>\ndocument.querySelectorAll(\'.action-confirm\').forEach(button => {\n    button.addEventListener(\'click\', function(event) {\n        const message = this.dataset.confirmMessage || \'¿Está seguro de que desea realizar esta acción?\';\n        if (!confirm(message)) {\n            event.preventDefault();\n        }\n    });\n});\n</script> \n*/ ?> 