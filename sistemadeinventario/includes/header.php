<?php
// config.php ya maneja session_start() y define APP_URL y APP_NAME
if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} elseif (file_exists(__DIR__ . '/../../config.php')) { // Para cuando header es llamado desde un subdirectorio de modulo
    require_once __DIR__ . '/../../config.php';
} else {
    die("Error: No se pudo encontrar config.php");
}

// Si $pageTitle no está definida, usa un título por defecto
if (!isset($pageTitle)) {
    $pageTitle = APP_NAME;
}

$current_page = basename($_SERVER['PHP_SELF']);
$current_module = basename(dirname($_SERVER['PHP_SELF']));

// Función auxiliar para determinar si un enlace de navegación está activo
function isActive($paths, $currentPage, $currentModule) {
    if (!is_array($paths)) $paths = [$paths];
    foreach ($paths as $path) {
        // Comprobar si el path es solo el nombre del archivo o modulo/archivo
        if (strpos($path, '/') !== false) {
            list($module, $file) = explode('/', $path);
            if ($module == $currentModule && $file == $currentPage) return 'active';
        } elseif ($path == $currentPage && ($currentModule == 'sistemadeinventario' || $currentModule == '')) { // Para archivos en la raíz
             return 'active';
        }
    }
    return '';
}

// Función auxiliar para determinar si un dropdown de módulo está activo
function isModuleActive($moduleName, $currentModule) {
    return $moduleName == $currentModule ? 'active' : '';
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Asegúrate de que la ruta a tu style.css sea correcta desde donde se incluya el header -->
    <?php 
    // Ajustar la ruta de style.css según la profundidad del archivo actual
    $pathToRoot = '';
    $depth = substr_count(realpath(dirname($current_page)), DIRECTORY_SEPARATOR) - substr_count(realpath($_SERVER['DOCUMENT_ROOT']), DIRECTORY_SEPARATOR);
    // Esto es una heurística, puede necesitar ajustes.
    // Si estamos en /Modulos/ModuloX/archivo.php, necesitamos ../../assets/css/style.css
    // Si estamos en /archivo.php, necesitamos assets/css/style.css
    $pathPrefix = '';
    if (strpos($_SERVER['REQUEST_URI'], '/Modulos/') !== false) {
        $pathPrefix = '../../'; 
    } else {
        $pathPrefix = ''; // Para archivos en la raíz
        // Si menu_principal.php o index.php están en la raíz, y assets está en la raíz
        // Esta lógica podría necesitar ser más robusta dependiendo de la estructura final.
    }
    echo '<link rel="stylesheet" href="' . $pathPrefix . 'assets/css/style.css">';
    ?>
    <style>
        body {
            padding-top: 4.5rem; /* Ajuste para la navbar fija */
        }
        .sidebar {
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100; /* Detrás de la navbar pero encima del contenido */
            padding: 48px 0 0; /* Altura de la navbar */
            box-shadow: inset -1px 0 0 rgba(0, 0, 0, .1);
            width: 250px; /* Ancho del sidebar */
            transition: margin-left .3s;
        }
        .main-content {
            margin-left: 250px; /* Mismo ancho que el sidebar */
            padding: 20px;
            transition: margin-left .3s;
        }
        /* Cuando el sidebar está colapsado */
        body.sidebar-collapsed .sidebar {
            margin-left: -250px;
        }
        body.sidebar-collapsed .main-content {
            margin-left: 0;
        }
        .navbar-brand img {
            max-height: 40px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?= APP_URL ?>/menu_principal.php">
                <!-- <img src="<?= $pathPrefix ?>assets/img/logo.png" alt="Logo" class="me-2"> -->
                <?= APP_NAME ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNavDropdown" aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('menu_principal.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/menu_principal.php"><i class="bi bi-house-door-fill"></i> Dashboard</a>
                    </li>
                    <li class="nav-item dropdown <?= isModuleActive('Inventario', $current_module) ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="inventarioDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-box-seam-fill"></i> Inventario
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="inventarioDropdown">
                            <li><a class="dropdown-item <?= isActive('Inventario/index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Inventario/index.php">Productos</a></li>
                            <li><a class="dropdown-item <?= isActive('Inventario/producto_form.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Inventario/producto_form.php">Nuevo Producto</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item <?= isActive('Inventario/categorias.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Inventario/categorias.php">Categorías</a></li>
                            <li><a class="dropdown-item <?= isActive('Inventario/lugares.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Inventario/lugares.php">Lugares</a></li>
                             <li><a class="dropdown-item <?= isActive('Inventario/exportar_excel.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Inventario/exportar_excel.php">Exportar Inventario</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown <?= isModuleActive('Pedidos', $current_module) ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="pedidosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cart-fill"></i> Pedidos
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="pedidosDropdown">
                            <li><a class="dropdown-item <?= isActive('Pedidos/index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Pedidos/index.php">Ver Pedidos</a></li>
                            <li><a class="dropdown-item <?= isActive('Pedidos/pedido_form.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Pedidos/pedido_form.php">Nuevo Pedido</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown <?= isModuleActive('Clientes', $current_module) ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="clientesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-people-fill"></i> Clientes
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="clientesDropdown">
                            <li><a class="dropdown-item <?= isActive('Clientes/index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Clientes/index.php">Ver Clientes</a></li>
                            <li><a class="dropdown-item <?= isActive('Clientes/cliente_form.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Clientes/cliente_form.php">Nuevo Cliente</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown <?= isModuleActive('Facturacion', $current_module) ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="facturacionDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-receipt-cutoff"></i> Facturación
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="facturacionDropdown">
                            <li><a class="dropdown-item <?= isActive('Facturacion/index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Facturacion/index.php">Ver Facturas</a></li>
                            <li><a class="dropdown-item <?= isActive('Facturacion/factura_form.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Facturacion/factura_form.php">Nueva Factura</a></li>
                        </ul>
                    </li>
                     <?php // Menú de Administración Dinámico basado en permisos
                     // Primero verificamos si el usuario es admin o tiene algún permiso específico de admin para mostrar el dropdown
                     if (isset($_SESSION['user_id']) && (
                         (isset($_SESSION['user_rol']) && $_SESSION['user_rol'] === 'admin') || // Si es admin general
                         hasPermission('admin_usuarios', 'manage') || // O tiene alguno de los permisos clave
                         hasPermission('admin_logs', 'view') ||
                         hasPermission('admin_roles', 'manage') ||
                         hasPermission('admin_backup', 'manage')
                     )):
                     ?>
                    <li class="nav-item dropdown <?= isModuleActive('Admin', $current_module) ?>">
                        <a class="nav-link dropdown-toggle" href="#" id="adminDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-gear-fill"></i> Administración
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminDropdown">
                            <?php if (hasPermission('admin_usuarios', 'manage')): ?>
                                <li><a class="dropdown-item <?= isActive('Admin/usuarios_index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Admin/usuarios_index.php">Gestión de Usuarios</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('admin_logs', 'view')): ?>
                                <li><a class="dropdown-item <?= isActive('Admin/logs_index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Admin/logs_index.php">Logs del Sistema</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('admin_roles', 'manage')): // Permiso futuro para gestión de roles ?>
                                <li><a class="dropdown-item <?= isActive('Admin/roles_index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Admin/roles_index.php">Gestión de Roles</a></li>
                            <?php endif; ?>
                            <?php if (hasPermission('admin_backup', 'manage')): // Permiso futuro para backups ?>
                                <li><a class="dropdown-item <?= isActive('Admin/backup_index.php', $current_page, $current_module) ?>" href="<?= APP_URL ?>/Modulos/Admin/backup_index.php">Gestión de Backups</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i> <?= isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Usuario' ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="#"><i class="bi bi-person-fill-gear"></i> Mi Perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= APP_URL ?>/logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- El contenido principal de cada página irá aquí -->
    <!-- No cierro body y html aquí, eso se hace en footer.php -->