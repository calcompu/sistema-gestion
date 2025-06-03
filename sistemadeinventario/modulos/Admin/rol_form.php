<?php
require_once __DIR__ . '/../../config.php';

// El permiso 'manage' para 'admin_roles' debe permitir editar los permisos de cualquier rol.
requirePermission('admin_roles', 'manage'); 

$pageTitle = "Editar Permisos de Rol";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Acceder a la configuración global de permisos
global $permissions;

// Determinar el rol a editar
$rolKeyAEditar = $_GET['rol'] ?? null;
$rolNombreDescriptivo = USER_ROLES[$rolKeyAEditar] ?? 'Rol Desconocido';

if (!$rolKeyAEditar || !isset(USER_ROLES[$rolKeyAEditar])) {
    setGlobalMessage("Rol no especificado o no válido.", "danger");
    header('Location: roles_index.php');
    exit;
}

// Obtener los permisos actuales para este rol
$permisosActualesDelRol = $permissions[$rolKeyAEditar] ?? [];

// Variable para determinar si los checkboxes individuales deben estar deshabilitados
$disableIndividualPermissions = ($rolKeyAEditar === 'admin' && isset($permisosActualesDelRol['all_modules']) && $permisosActualesDelRol['all_modules'] === true);

// Definir una lista maestra de todos los módulos y acciones posibles
// Esto podría ser dinámico o expandirse según sea necesario.
// Por ahora, lo derivamos de lo que ya existe en la estructura de $permissions 
// para otros roles y añadimos explícitamente los módulos de admin si no están.
$modulosYAccionesDisponibles = [];

// Módulos y acciones estándar (se pueden añadir más o hacer esto más dinámico)
$listaModulosPrincipales = [
    'inventario_productos' => ['manage' => 'Gestionar (Ver, Crear, Editar, Eliminar, Detalle)', 'export' => 'Exportar'],
    'inventario_categorias' => ['manage' => 'Gestionar (Ver, Crear, Editar, Eliminar, Cambiar Estado)'],
    'inventario_lugares' => ['manage' => 'Gestionar (Ver, Crear, Editar, Eliminar, Cambiar Estado)'],
    'pedidos' => ['view_list' => 'Ver Listado', 'view_detail' => 'Ver Detalle', 'create' => 'Crear', 'edit' => 'Editar', 'cancel' => 'Cancelar'],
    'clientes' => ['view_list' => 'Ver Listado', 'create' => 'Crear', 'edit' => 'Editar', 'delete' => 'Eliminar', 'toggle_status' => 'Cambiar Estado'],
    'facturacion' => ['view_list' => 'Ver Listado', 'view_detail' => 'Ver Detalle', 'create_from_order' => 'Crear desde Pedido', 'void' => 'Anular'],
    'admin_usuarios' => ['manage' => 'Gestionar Usuarios'],
    'admin_logs' => ['view' => 'Ver Logs del Sistema'],
    'admin_roles' => ['manage' => 'Gestionar Roles y Permisos'],
    'admin_backup' => ['manage' => 'Gestionar Backups'],
    'menu_principal' => ['view' => 'Ver Dashboard Principal']
    // Añadir cualquier otro módulo/acción que deba ser asignable
];

// El permiso especial 'all_modules' para admin
$permisosEspeciales = [
    'all_modules' => 'Permitir acceso completo a todos los módulos (Super Administrador)'
];

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?>: <span class="text-primary"><?= htmlspecialchars($rolNombreDescriptivo) ?> (<?= htmlspecialchars($rolKeyAEditar) ?>)</span></h1>
            <a href="roles_index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado de Roles
            </a>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="rol_acciones.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="actualizar_permisos_rol">
                    <input type="hidden" name="rol_key" value="<?= htmlspecialchars($rolKeyAEditar) ?>">

                    <div class="mb-3">
                        <div class="alert alert-secondary">
                            Marque las casillas correspondientes a los permisos que desea asignar al rol '<strong><?= htmlspecialchars($rolNombreDescriptivo) ?></strong>'. <br>
                            Desmarcar una casilla y guardar revocará ese permiso específico. 
                            La opción 'all_modules' otorga acceso total y sobreescribe otros permisos para este rol si está marcada.
                            <?php if ($disableIndividualPermissions): ?>
                                <br><strong>Nota:</strong> Como 'Permitir acceso completo a todos los módulos' está activo para el rol Admin, los permisos individuales están deshabilitados y no se guardarán.
                            <?php endif; ?>
                        </div>
                    </div>

                    <fieldset class="mb-4">
                        <legend class="h5">Permisos Especiales</legend>
                        <?php foreach ($permisosEspeciales as $permisoKey => $descripcion): ?>
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="permisos[<?= htmlspecialchars($permisoKey) ?>]" id="perm_<?= htmlspecialchars($permisoKey) ?>" value="true" 
                                    <?php if (isset($permisosActualesDelRol[$permisoKey]) && $permisosActualesDelRol[$permisoKey] === true) echo 'checked'; ?>
                                    <?php if ($rolKeyAEditar === 'admin' && $permisoKey !== 'all_modules' && $disableIndividualPermissions) echo 'disabled'; // Deshabilitar otros especiales si all_modules está on para admin ?>
                                    >
                                <label class="form-check-label" for="perm_<?= htmlspecialchars($permisoKey) ?>">
                                    <strong><?= htmlspecialchars($descripcion) ?></strong>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>
                    <hr>

                    <fieldset>
                        <legend class="h5">Permisos por Módulo</legend>
                        <?php foreach ($listaModulosPrincipales as $moduloKey => $acciones): ?>
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h6 class="mb-0">Módulo: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $moduloKey))) ?></h6>
                                </div>
                                <div class="card-body">
                                    <?php foreach ($acciones as $accionKey => $descripcionAccion): ?>
                                        <div class="form-check form-switch mb-2">
                                            <input class="form-check-input" type="checkbox" name="permisos[<?= htmlspecialchars($moduloKey) ?>][<?= htmlspecialchars($accionKey) ?>]" id="perm_<?= htmlspecialchars($moduloKey) ?>_<?= htmlspecialchars($accionKey) ?>" value="true"
                                                <?php if (isset($permisosActualesDelRol[$moduloKey][$accionKey]) && $permisosActualesDelRol[$moduloKey][$accionKey] === true) echo 'checked'; ?>
                                                <?php if ($disableIndividualPermissions) echo 'disabled'; ?> >
                                            <label class="form-check-label" for="perm_<?= htmlspecialchars($moduloKey) ?>_<?= htmlspecialchars($accionKey) ?>">
                                                <?= htmlspecialchars($descripcionAccion) ?> (<code><?= htmlspecialchars($accionKey) ?></code>)
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </fieldset>

                    <hr class="my-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save-fill me-1"></i> Guardar Cambios en Permisos</button>
                    <a href="roles_index.php" class="btn btn-secondary">Cancelar</a>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 