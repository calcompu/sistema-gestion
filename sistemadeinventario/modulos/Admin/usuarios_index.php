<?php
require_once __DIR__ . '/../../config.php';
requirePermission('admin_usuarios', 'manage');

$pageTitle = "Gestión de Usuarios";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Configuración de paginación
$usuarios_por_pagina = 10;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $usuarios_por_pagina;

// Filtros
$filtro_busqueda = isset($_GET['busqueda']) ? sanitizeInput($_GET['busqueda']) : '';
$filtro_rol = isset($_GET['rol']) ? sanitizeInput($_GET['rol']) : '';
$filtro_estado = isset($_GET['estado']) ? sanitizeInput($_GET['estado']) : ''; // '1' activo, '0' inactivo, '' todos

// Construir la consulta SQL base
$sql_base = "FROM usuarios u WHERE 1=1";
$params = [];

if (!empty($filtro_busqueda)) {
    $sql_base .= " AND (u.username LIKE :busqueda OR u.nombre_completo LIKE :busqueda OR u.email LIKE :busqueda)";
    $params[':busqueda'] = "%$filtro_busqueda%";
}
if (!empty($filtro_rol)) {
    $sql_base .= " AND u.rol = :rol";
    $params[':rol'] = $filtro_rol;
}
if ($filtro_estado !== '') {
    $sql_base .= " AND u.activo = :estado";
    $params[':estado'] = (int)$filtro_estado;
}

// Obtener el total de usuarios para la paginación
$stmt_total = $pdo->prepare("SELECT COUNT(*) " . $sql_base);
$stmt_total->execute($params);
$total_usuarios = $stmt_total->fetchColumn();
$total_paginas = ceil($total_usuarios / $usuarios_por_pagina);

// Obtener los usuarios para la página actual
$sql_usuarios = "SELECT u.id, u.username, u.nombre_completo, u.email, u.rol, u.activo, u.fecha_creacion, u.ultimo_acceso " . $sql_base . " ORDER BY u.id DESC LIMIT :limit OFFSET :offset";
$stmt_usuarios = $pdo->prepare($sql_usuarios);
$stmt_usuarios->bindParam(':limit', $usuarios_por_pagina, PDO::PARAM_INT);
$stmt_usuarios->bindParam(':offset', $offset, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt_usuarios->bindValue($key, $value);
}
$stmt_usuarios->execute();
$usuarios = $stmt_usuarios->fetchAll();

// Obtener todos los roles distintos para el dropdown de filtro (opcional, pero útil)
$roles_disponibles = [];
$stmt_roles = $pdo->query("SELECT DISTINCT rol FROM usuarios ORDER BY rol ASC");
if ($stmt_roles) {
    $roles_disponibles = $stmt_roles->fetchAll(PDO::FETCH_COLUMN);
}

?>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?></h1>
            <a href="usuario_form.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-plus-lg me-1"></i> Añadir Nuevo Usuario
            </a>
        </div>

        <?php displayGlobalMessages(); ?>

        <!-- Formulario de Filtros -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><i class="bi bi-filter me-2"></i>Filtrar Usuarios</h6>
            </div>
            <div class="card-body">
                <form method="GET" action="usuarios_index.php" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="busqueda" class="form-label">Buscar:</label>
                        <input type="text" class="form-control" id="busqueda" name="busqueda" value="<?= htmlspecialchars($filtro_busqueda) ?>" placeholder="Username, Nombre, Email...">
                    </div>
                    <div class="col-md-3">
                        <label for="rol" class="form-label">Rol:</label>
                        <select class="form-select" id="rol" name="rol">
                            <option value="">Todos los roles</option>
                            <?php foreach ($roles_disponibles as $rol_item): ?>
                                <option value="<?= htmlspecialchars($rol_item) ?>" <?= ($filtro_rol == $rol_item) ? 'selected' : '' ?>><?= ucfirst(htmlspecialchars($rol_item)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="estado" class="form-label">Estado:</label>
                        <select class="form-select" id="estado" name="estado">
                            <option value="">Todos</option>
                            <option value="1" <?= ($filtro_estado === '1') ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= ($filtro_estado === '0') ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-info me-2"><i class="bi bi-search me-1"></i>Filtrar</button>
                        <a href="usuarios_index.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Limpiar</a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de Usuarios -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Usuarios (<?= $total_usuarios ?>)</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="dataTableUsuarios" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Últ. Acceso</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($usuarios)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No se encontraron usuarios con los filtros aplicados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($usuario['id']) ?></td>
                                        <td><?= htmlspecialchars($usuario['username']) ?></td>
                                        <td><?= htmlspecialchars($usuario['nombre_completo'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($usuario['email'] ?? 'N/A') ?></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst(htmlspecialchars($usuario['rol'])) ?></span></td>
                                        <td>
                                            <?php if ($usuario['activo']): ?>
                                                <span class="badge bg-success">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars(date("d/m/Y H:i", strtotime($usuario['fecha_creacion']))) ?></td>
                                        <td><?= $usuario['ultimo_acceso'] ? htmlspecialchars(date("d/m/Y H:i", strtotime($usuario['ultimo_acceso']))) : 'Nunca' ?></td>
                                        <td>
                                            <a href="usuario_form.php?id=<?= $usuario['id'] ?>" class="btn btn-sm btn-warning me-1" title="Editar Usuario">
                                                <i class="bi bi-pencil-square"></i>
                                            </a>
                                            <form action="usuario_acciones.php" method="POST" class="d-inline" onsubmit="return confirm('¿Está seguro de que desea cambiar el estado de este usuario?');">
                                                <input type="hidden" name="accion" value="cambiar_estado">
                                                <input type="hidden" name="usuario_id" value="<?= $usuario['id'] ?>">
                                                <input type="hidden" name="estado_actual" value="<?= $usuario['activo'] ?>">
                                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $usuario['activo'] ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $usuario['activo'] ? 'Desactivar' : 'Activar' ?> Usuario">
                                                    <i class="bi <?= $usuario['activo'] ? 'bi-x-circle' : 'bi-check-circle' ?>"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginación de usuarios">
                    <ul class="pagination justify-content-center mt-4">
                        <?php 
                        // Construir query string para paginación manteniendo filtros
                        $query_string_filtros = http_build_query(array_filter([
                            'busqueda' => $filtro_busqueda,
                            'rol' => $filtro_rol,
                            'estado' => $filtro_estado
                        ]));
                        ?>
                        <li class="page-item <?= ($pagina_actual <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina_actual - 1 ?>&<?= $query_string_filtros ?>">Anterior</a>
                        </li>
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= ($i == $pagina_actual) ? 'active' : '' ?>">
                                <a class="page-link" href="?pagina=<?= $i ?>&<?= $query_string_filtros ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($pagina_actual >= $total_paginas) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?pagina=<?= $pagina_actual + 1 ?>&<?= $query_string_filtros ?>">Siguiente</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 