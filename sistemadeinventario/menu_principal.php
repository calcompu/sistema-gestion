<?php 
require_once 'config.php';
requirePermission('menu_principal', 'view'); // Permiso para ver el dashboard

$pageTitle = "Dashboard";
require_once 'includes/header.php'; 
require_once 'includes/functions.php'; // Asegurar que functions.php está incluido para formatCurrency

// Datos para el dashboard (se obtendrán condicionalmente)
$totalProductos = 0;
$totalClientes = 0;
$totalPedidosPendientes = 0;
$totalPedidosCompletados = 0;
$ultimasFacturas = [];

// Conteo de Productos Totales
if (hasPermission('inventario_productos', 'view_list')) {
    $stmtProductos = $pdo->query("SELECT COUNT(*) FROM productos WHERE activo = 1");
    $totalProductos = $stmtProductos->fetchColumn();
}

// Conteo de Clientes Activos
if (hasPermission('clientes', 'view_list')) {
    $stmtClientes = $pdo->query("SELECT COUNT(*) FROM clientes_pedidos WHERE activo = 1");
    $totalClientes = $stmtClientes->fetchColumn();
}

// Conteo de Pedidos Pendientes
if (hasPermission('pedidos', 'view_list')) {
    $stmtPedidosPendientes = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'pendiente'");
    $totalPedidosPendientes = $stmtPedidosPendientes->fetchColumn();
}

// Conteo de Pedidos Completados
if (hasPermission('pedidos', 'view_list')) {
    $stmtPedidosCompletados = $pdo->query("SELECT COUNT(*) FROM pedidos WHERE estado = 'completado'");
    $totalPedidosCompletados = $stmtPedidosCompletados->fetchColumn();
}

// Últimas Facturas Emitidas
if (hasPermission('facturacion', 'view_list')) {
    // Asumiendo que facturas tiene una columna id_factura o similar para el enlace
    $stmtUltimasFacturas = $pdo->query("SELECT id_factura, numero_factura, total, fecha_emision FROM facturas WHERE estado = 'emitida' ORDER BY fecha_emision DESC LIMIT 5");
    $ultimasFacturas = $stmtUltimasFacturas->fetchAll();
}

?>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
            <!-- Podrías agregar un botón de "Generar Reporte Rápido" o similar aquí -->
            <!-- <a href="#" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm"><i class="bi bi-download me-2"></i> Generar Reporte</a> -->
        </div>

        <?php displayGlobalMessages(); ?>

        <!-- Fila de Tarjetas de Resumen -->
        <div class="row">
            <?php if (hasPermission('inventario_productos', 'view_list')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Productos Activos</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalProductos ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-box-seam-fill fs-2 text-gray-300"></i>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/Modulos/Inventario/index.php" class="stretched-link" title="Ir a Inventario"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('clientes', 'view_list')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Clientes Activos</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalClientes ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people-fill fs-2 text-gray-300"></i>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/Modulos/Clientes/index.php" class="stretched-link" title="Ir a Clientes"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('pedidos', 'view_list')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Pedidos Pendientes</div>
                                <div class="h5 mb-0 mr-3 font-weight-bold text-gray-800"><?= $totalPedidosPendientes ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-cart-check-fill fs-2 text-gray-300"></i>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/Modulos/Pedidos/index.php?estado=pendiente" class="stretched-link" title="Ver Pedidos Pendientes"></a>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pedidos por Facturar</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800"><?= $totalPedidosCompletados ?></div>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-receipt fs-2 text-gray-300"></i>
                            </div>
                        </div>
                         <a href="<?= APP_URL ?>/Modulos/Pedidos/index.php?estado=completado" class="stretched-link" title="Ver Pedidos por Facturar"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Fila de Contenido Principal -->
        <div class="row">
            <?php if (hasPermission('facturacion', 'view_list')): ?>
            <div class="col-lg-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Últimas Facturas Emitidas</h6>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($ultimasFacturas)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead><tr><th>N° Factura</th><th>Fecha</th><th class="text-end">Total</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($ultimasFacturas as $factura): ?>
                                            <tr>
                                                <td>
                                                    <a href="<?= APP_URL ?>/Modulos/Facturacion/factura_detalle.php?id_factura=<?= $factura['id_factura'] ?? '#' ?>">
                                                        <?= htmlspecialchars($factura['numero_factura']) ?>
                                                    </a>
                                                </td>
                                                <td><?= htmlspecialchars(date('d/m/Y', strtotime($factura['fecha_emision']))) ?></td>
                                                <td class="text-end"><?= function_exists('formatCurrency') ? formatCurrency($factura['total']) : htmlspecialchars($factura['total']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                             <div class="text-center mt-2">
                                <a href="<?= APP_URL ?>/Modulos/Facturacion/index.php">Ver Todas las Facturas &rarr;</a>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">No hay facturas recientes.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="col-lg-6 mb-4">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Accesos Rápidos</h6>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <?php if (hasPermission('inventario_productos', 'create')): ?>
                            <a href="<?= APP_URL ?>/Modulos/Inventario/producto_form.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-plus-circle-fill me-2"></i> Nuevo Producto
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('pedidos', 'create')): ?>
                            <a href="<?= APP_URL ?>/Modulos/Pedidos/pedido_form.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-cart-plus-fill me-2"></i> Nuevo Pedido
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('clientes', 'create')): ?>
                            <a href="<?= APP_URL ?>/Modulos/Clientes/cliente_form.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-person-plus-fill me-2"></i> Nuevo Cliente
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('facturacion', 'create_from_order')): // O el permiso que sea para crear facturas directamente
                            ?>
                            <a href="<?= APP_URL ?>/Modulos/Facturacion/factura_form.php" class="list-group-item list-group-item-action">
                                <i class="bi bi-file-earmark-plus-fill me-2"></i> Nueva Factura (desde Pedido)
                            </a>
                            <?php endif; ?>
                            <?php if (hasPermission('admin_usuarios', 'manage')): ?>
                                <a href="<?= APP_URL ?>/Modulos/Admin/usuarios_index.php" class="list-group-item list-group-item-action">
                                    <i class="bi bi-people-fill me-2"></i> Gestionar Usuarios
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?> 