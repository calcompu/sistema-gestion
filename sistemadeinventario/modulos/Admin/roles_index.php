<?php
require_once __DIR__ . '/../../config.php';
requirePermission('admin_roles', 'manage');

$pageTitle = "Gestión de Roles y Permisos";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Obtener los roles definidos en config.php
$rolesDefinidos = USER_ROLES;
// Obtener la configuración de permisos para referencia (opcional aquí, más útil en el form)
// global $permissions;

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (hasPermission('admin_roles', 'manage')): ?>
                <a href="rol_form.php" class="btn btn-primary">
                    <i class="bi bi-plus-circle-fill me-1"></i> Nuevo Rol (Funcionalidad Futura)
                </a>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Roles del Sistema</h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <p class="mb-0">Actualmente, los roles base están definidos en la configuración del sistema. La creación de nuevos roles personalizados y la asignación granular de permisos por rol se implementará en futuras actualizaciones.</p>
                    <p class="mb-0">Por ahora, puedes ver los roles existentes y sus descripciones. La edición se enfocará en los permisos asociados a estos roles base a través del formulario (próximamente).</p>
                </div>

                <?php if (!empty($rolesDefinidos)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="dataTableRoles" width="100%" cellspacing="0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Identificador del Rol (Clave)</th>
                                    <th>Nombre Descriptivo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rolesDefinidos as $claveRol => $nombreRol): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($claveRol) ?></td>
                                        <td><?= htmlspecialchars($nombreRol) ?></td>
                                        <td>
                                            <?php if (hasPermission('admin_roles', 'manage')): // o 'edit_role_permissions' ?>
                                                <a href="rol_form.php?rol=<?= htmlspecialchars($claveRol) ?>" class="btn btn-sm btn-warning me-1" title="Editar Permisos del Rol">
                                                    <i class="bi bi-pencil-square"></i> Editar Permisos
                                                </a>
                                            <?php endif; ?>
                                            <?php /* Futuro: Botón de eliminar rol, con validaciones (ej. no eliminar 'admin')
                                            <?php if (hasPermission('admin_roles', 'manage') && $claveRol !== 'admin'): ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDeleteRol('<?= htmlspecialchars($claveRol) ?>')" title="Eliminar Rol (Funcionalidad Futura)">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            <?php endif; ?>
                                            */ ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p>No hay roles definidos en el sistema. Esto es inesperado, por favor revise la configuración (USER_ROLES).</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

<?php /* Modal de confirmación para eliminar (si se implementa el botón) 
<script>
function confirmDeleteRol(rolId) {
    if (confirm("¿Está seguro de que desea eliminar el rol '" + rolId + "'? Esta acción no se puede deshacer y podría afectar a los usuarios asignados a este rol.")) {
        // Crear un formulario dinámicamente y enviarlo para la acción de eliminar
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = 'rol_acciones.php';
        
        let csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = '<?= $_SESSION["csrf_token"] ?>'; // Asegúrate que el token CSRF esté disponible
        form.appendChild(csrfInput);

        let actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'accion';
        actionInput.value = 'eliminar_rol';
        form.appendChild(actionInput);

        let rolIdInput = document.createElement('input');
        rolIdInput.type = 'hidden';
        rolIdInput.name = 'rol_id_key'; // Usar rol_id_key para el identificador clave
        rolIdInput.value = rolId;
        form.appendChild(rolIdInput);

        document.body.appendChild(form);
        form.submit();
    }
}
</script>
*/ ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 