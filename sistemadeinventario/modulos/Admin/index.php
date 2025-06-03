<?php
require_once __DIR__ . '/../../config.php';
requirePermission('admin_dashboard', 'view'); // Permiso para ver el panel de administración

$pageTitle = "Panel de Administración";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';
?>

<main class="main-content">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-12">
                <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
                <p class="text-muted">Bienvenido al panel de administración. Desde aquí puede gestionar usuarios, roles, logs, backups y otras configuraciones del sistema.</p>
            </div>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="row">
            <?php if (hasPermission('admin_usuarios', 'manage')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-primary shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                    Usuarios</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Gestión de Usuarios</div>
                                <small class="text-muted">Crear, editar y administrar cuentas.</small>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-people-fill fs-1 text-gray-300"></i>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/Modulos/Admin/usuarios_index.php" class="stretched-link" title="Ir a Gestión de Usuarios"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('admin_roles', 'manage')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-success shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                    Roles</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Gestión de Roles</div>
                                <small class="text-muted">Definir roles y asignar permisos.</small>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-person-check-fill fs-1 text-gray-300"></i>
                            </div>
                        </div>
                        <a href="<?= APP_URL ?>/Modulos/Admin/roles_index.php" class="stretched-link" title="Ir a Gestión de Roles"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('admin_logs', 'view')): ?>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-info shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                    Auditoría</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Logs del Sistema</div>
                                <small class="text-muted">Revisar registros y eventos.</small>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-card-list fs-1 text-gray-300"></i>
                            </div>
                        </div>
                         <a href="<?= APP_URL ?>/Modulos/Admin/logs_index.php" class="stretched-link" title="Ir a Logs del Sistema"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission('admin_backup', 'manage')): ?>
             <div class="col-xl-3 col-md-6 mb-4">
                <div class="card border-left-warning shadow h-100 py-2">
                    <div class="card-body">
                        <div class="row no-gutters align-items-center">
                            <div class="col mr-2">
                                <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                    Mantenimiento</div>
                                <div class="h5 mb-0 font-weight-bold text-gray-800">Gestión de Backups</div>
                                <small class="text-muted">Crear y restaurar copias de seguridad.</small>
                            </div>
                            <div class="col-auto">
                                <i class="bi bi-database-fill-gear fs-1 text-gray-300"></i>
                            </div>
                        </div>
                         <a href="<?= APP_URL ?>/Modulos/Admin/backup_index.php" class="stretched-link" title="Ir a Gestión de Backups"></a>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div>

        <div class="row mt-4">
            <div class="col-md-12">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Información Adicional</h6>
                    </div>
                    <div class="card-body">
                        <p>Este panel le permite administrar aspectos críticos de la aplicación. Asegúrese de que sólo personal autorizado tenga acceso a estas funciones.</p>
                        <p>Recuerde que las acciones realizadas aquí pueden tener un impacto significativo en el funcionamiento del sistema.</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</main>

<style>
.card.border-left-primary {
    border-left: .25rem solid #4e73df!important;
}
.card.border-left-success {
    border-left: .25rem solid #1cc88a!important;
}
.card.border-left-info {
    border-left: .25rem solid #36b9cc!important;
}
.card.border-left-warning {
    border-left: .25rem solid #f6c23e!important;
}
.text-xs {
    font-size: .7rem;
}
</style>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 