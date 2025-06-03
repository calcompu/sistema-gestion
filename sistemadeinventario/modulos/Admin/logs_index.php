<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Comentado porque requirePermission ya lo incluye
requirePermission('admin_logs', 'view'); // Nuevo chequeo de permisos
require_once __DIR__ . '/../../includes/functions.php';

$pageTitle = "Logs del Sistema";
require_once __DIR__ . '/../../includes/header.php';

// Configuración de paginación
$elementosPorPagina = 20;
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($paginaActual < 1) $paginaActual = 1;
$offset = ($paginaActual - 1) * $elementosPorPagina;

// Filtros y búsqueda
$busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$filtroLevel = isset($_GET['level']) ? sanitizeInput($_GET['level']) : '';
$filtroUserId = isset($_GET['user_id']) ? filter_var(sanitizeInput($_GET['user_id']), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : '';
$filtroFechaDesde = isset($_GET['fecha_desde']) ? sanitizeInput($_GET['fecha_desde']) : '';
$filtroFechaHasta = isset($_GET['fecha_hasta']) ? sanitizeInput($_GET['fecha_hasta']) : '';


$whereClauses = [];
$params = [];

if (!empty($busqueda)) {
    $whereClauses[] = "(sl.message LIKE :busqueda OR sl.action LIKE :busqueda OR sl.module LIKE :busqueda OR u.username LIKE :busqueda OR sl.ip_address LIKE :busqueda)";
    $params[':busqueda'] = "%{$busqueda}%";
}

if (!empty($filtroLevel)) {
    $whereClauses[] = "sl.level = :level";
    $params[':level'] = $filtroLevel;
}

if (!empty($filtroUserId)) {
    $whereClauses[] = "sl.user_id = :user_id";
    $params[':user_id'] = $filtroUserId;
}

if (!empty($filtroFechaDesde)) {
    $whereClauses[] = "sl.timestamp >= :fecha_desde";
    $params[':fecha_desde'] = $filtroFechaDesde . " 00:00:00";
}

if (!empty($filtroFechaHasta)) {
    $whereClauses[] = "sl.timestamp <= :fecha_hasta";
    $params[':fecha_hasta'] = $filtroFechaHasta . " 23:59:59";
}


$whereSql = "";
if (!empty($whereClauses)) {
    $whereSql = "WHERE " . implode(" AND ", $whereClauses);
}

// Obtener total de logs para paginación
$stmtTotal = $pdo->prepare("SELECT COUNT(sl.id) 
                           FROM system_logs sl 
                           LEFT JOIN usuarios u ON sl.user_id = u.id
                           $whereSql");
$stmtTotal->execute($params);
$totalLogs = $stmtTotal->fetchColumn();
$totalPaginas = ceil($totalLogs / $elementosPorPagina);

// Obtener logs para la página actual
$sql = "SELECT sl.*, u.username 
        FROM system_logs sl
        LEFT JOIN usuarios u ON sl.user_id = u.id
        $whereSql 
        ORDER BY sl.timestamp DESC 
        LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':limit', $elementosPorPagina, PDO::PARAM_INT);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value); // Usar bindValue para los parámetros del WHERE
}
$stmt->execute();
$logs = $stmt->fetchAll();

$logLevels = ['INFO', 'WARNING', 'ERROR', 'SECURITY', 'DEBUG'];

?>
<main class="main-content">
    <div class="container-fluid">
        <h1 class="h3 mb-4 text-gray-800"><?= $pageTitle ?></h1>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Filtros de Logs</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="logs_index.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Buscar</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($busqueda) ?>" placeholder="Mensaje, acción, módulo, usuario, IP...">
                    </div>
                    <div class="col-md-2">
                        <label for="level" class="form-label">Nivel</label>
                        <select class="form-select" id="level" name="level">
                            <option value="">Todos</option>
                            <?php foreach ($logLevels as $level): ?>
                                <option value="<?= $level ?>" <?= ($filtroLevel === $level) ? 'selected' : '' ?>><?= $level ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="user_id" class="form-label">ID Usuario</label>
                        <input type="number" class="form-control" id="user_id" name="user_id" value="<?= htmlspecialchars($filtroUserId) ?>" placeholder="Ej: 1">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_desde" class="form-label">Desde</label>
                        <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= htmlspecialchars($filtroFechaDesde) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="fecha_hasta" class="form-label">Hasta</label>
                        <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" value="<?= htmlspecialchars($filtroFechaHasta) ?>">
                    </div>
                    <div class="col-md-12 d-flex mt-3">
                        <button type="submit" class="btn btn-info me-2"><i class="bi bi-search"></i> Filtrar</button>
                        <a href="logs_index.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i> Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Logs del Sistema</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableLogs" width="100%" cellspacing="0">
                        <thead class="table-dark">
                            <tr>
                                <th>Timestamp</th>
                                <th>Usuario</th>
                                <th>IP</th>
                                <th>Nivel</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                                <th>Mensaje</th>
                                <th style="min-width: 200px;">Detalles</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No se encontraron logs con los filtros aplicados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($log['timestamp']) ?></td>
                                        <td><?= $log['user_id'] ? htmlspecialchars($log['username'] ?? 'ID: ' . $log['user_id']) : 'Sistema/Anónimo' ?></td>
                                        <td><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></td>
                                        <td>
                                            <?php
                                            $levelClass = '';
                                            switch (strtoupper($log['level'])) {
                                                case 'ERROR': $levelClass = 'text-danger fw-bold'; break;
                                                case 'WARNING': $levelClass = 'text-warning fw-bold'; break;
                                                case 'SECURITY': $levelClass = 'text-info fw-bold'; break;
                                                case 'DEBUG': $levelClass = 'text-muted'; break;
                                            }
                                            ?>
                                            <span class="<?= $levelClass ?>"><?= htmlspecialchars($log['level']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($log['module'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($log['action']) ?></td>
                                        <td><?= nl2br(htmlspecialchars($log['message'])) ?></td>
                                        <td>
                                            <?php if (!empty($log['details'])): ?>
                                                <pre style="white-space: pre-wrap; word-break: break-all; max-height: 100px; overflow-y: auto; background-color: #f8f9fa; padding: 5px; border-radius: 4px; font-size: 0.8em;"><?= htmlspecialchars($log['details']) ?></pre>
                                            <?php else: ?>
                                                N/A
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
                <nav aria-label="Paginación de logs" class="mt-4">
                    <ul class="pagination justify-content-center">
                        <?php 
                        $queryParams = $_GET;
                        unset($queryParams['pagina']);
                        $queryString = http_build_query($queryParams);
                        ?>
                        <li class="page-item <?= ($paginaActual <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $paginaActual - 1 ?>&<?= $queryString ?>">Anterior</a>
                        </li>
                        <?php
                        // Lógica para mostrar un rango de páginas (ej. 2 a cada lado de la actual)
                        $rangoVisible = 2;
                        $inicioRango = max(1, $paginaActual - $rangoVisible);
                        $finRango = min($totalPaginas, $paginaActual + $rangoVisible);

                        if ($inicioRango > 1) {
                            echo '<li class="page-item"><a class="page-link" href="?pagina=1&'.$queryString.'">1</a></li>';
                            if ($inicioRango > 2) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                        }

                        for ($i = $inicioRango; $i <= $finRango; $i++): ?>
                            <li class="page-item <?= ($i == $paginaActual) ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&<?= $queryString ?>"><?= $i ?></a>
                            </li>
                        <?php endfor;

                        if ($finRango < $totalPaginas) {
                            if ($finRango < $totalPaginas - 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            echo '<li class="page-item"><a class="page-link" href="?pagina='.$totalPaginas.'&'.$queryString.'">'.$totalPaginas.'</a></li>';
                        }
                        ?>
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