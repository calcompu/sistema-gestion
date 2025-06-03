<?php
require_once __DIR__ . '/../../config.php';
requirePermission('admin_backup', 'manage'); // Permiso para ver y gestionar backups

$pageTitle = "Gestión de Backups";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Función para obtener la lista de backups
function obtenerListaDeBackups() {
    if (!defined('BACKUP_DIR') || !is_dir(BACKUP_DIR)) {
        return [];
    }
    $files = scandir(BACKUP_DIR);
    $backups = [];
    foreach ($files as $file) {
        // Filtrar solo archivos .sql (o .sql.gz si se implementa compresión)
        if (is_file(BACKUP_DIR . $file) && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backups[] = [
                'name' => $file,
                'size' => filesize(BACKUP_DIR . $file),
                'date' => filemtime(BACKUP_DIR . $file)
            ];
        }
    }
    // Ordenar por fecha descendente (más reciente primero)
    usort($backups, function ($a, $b) {
        return $b['date'] - $a['date'];
    });
    return $backups;
}

$listaBackups = obtenerListaDeBackups();

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <?php if (hasPermission('admin_backup', 'manage')): // O un permiso más específico como 'create_backup' ?>
                <form action="backup_acciones.php" method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="accion" value="crear_backup">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-plus-circle-fill me-1"></i> Crear Nuevo Backup
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Listado de Backups del Sistema</h6>
            </div>
            <div class="card-body">
                <?php if (!defined('BACKUP_DIR') || !is_dir(BACKUP_DIR) || !is_writable(BACKUP_DIR)): ?>
                    <div class="alert alert-danger">
                        <strong>Error de Configuración:</strong> El directorio de backups (<code><?= defined('BACKUP_DIR') ? htmlspecialchars(BACKUP_DIR) : 'BACKUP_DIR no definido' ?></code>) no está correctamente configurado, no existe o no tiene permisos de escritura. Por favor, verifique la constante <code>BACKUP_DIR</code> en <code>config.php</code> y los permisos del directorio en el servidor.
                    </div>
                <?php elseif (empty($listaBackups)): ?>
                    <div class="alert alert-info">No se encontraron backups. Puede crear uno usando el botón "Crear Nuevo Backup".</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="dataTableBackups" width="100%" cellspacing="0">
                            <thead class="table-dark">
                                <tr>
                                    <th>Nombre del Archivo</th>
                                    <th>Fecha de Creación</th>
                                    <th>Tamaño</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($listaBackups as $backup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($backup['name']) ?></td>
                                        <td><?= htmlspecialchars(date('Y-m-d H:i:s', $backup['date'])) ?></td>
                                        <td><?= htmlspecialchars(formatBytes($backup['size'])) ?></td>
                                        <td>
                                            <?php 
                                            $safeFileNameForJs = htmlspecialchars($backup['name'], ENT_QUOTES, 'UTF-8');
                                            $csrfTokenForJs = htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8');
                                            ?>
                                            <?php if (hasPermission('admin_backup', 'manage')): // O un permiso específico 'download_backup' ?>
                                                <a href="backup_acciones.php?accion=descargar_backup&file=<?= urlencode($backup['name']) ?>&csrf_token=<?= $csrfTokenForJs ?>" class="btn btn-sm btn-info me-1" title="Descargar Backup">
                                                    <i class="bi bi-download"></i> Descargar
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php if (hasPermission('admin_backup', 'manage')): // O un permiso específico 'restore_backup' ?>
                                                <button type="button" class="btn btn-sm btn-warning me-1" onclick="confirmRestore('<?= $safeFileNameForJs ?>', '<?= $csrfTokenForJs ?>')" title="Restaurar Backup (¡Acción Delicada!)">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Restaurar
                                                </button>
                                            <?php endif; ?>

                                            <?php if (hasPermission('admin_backup', 'manage')): // O un permiso específico 'delete_backup' ?>
                                                <button type="button" class="btn btn-sm btn-danger" onclick="confirmDelete('<?= $safeFileNameForJs ?>', '<?= $csrfTokenForJs ?>')" title="Eliminar Backup">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                 <div class="alert alert-warning mt-3">
                    <strong>Importante:</strong> La restauración de un backup sobreescribirá TODOS los datos actuales de la base de datos. Proceda con extrema cautela.
                </div>
                <div class="alert alert-info mt-1">
                    <strong>Nota:</strong> La creación y gestión de backups puede requerir configuración adicional en el servidor
                    y permisos de escritura en el directorio de backups. Asegúrese de que el directorio
                    <code><?= defined('BACKUP_DIR') ? htmlspecialchars(BACKUP_DIR) : 'BACKUP_DIR_NO_DEFINIDO' ?></code> exista, tenga los permisos correctos y que la utilidad <code>mysqldump</code> sea accesible por el servidor web si la usa para crear backups.
                </div>
            </div>
        </div>
    </div>
</main>

<script>
function confirmDelete(fileName, csrfToken) {
    if (confirm("¿Está seguro de que desea eliminar el archivo de backup '" + fileName + "'? Esta acción no se puede deshacer.")) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = 'backup_acciones.php';
        
        let csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        let actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'accion';
        actionInput.value = 'eliminar_backup';
        form.appendChild(actionInput);

        let fileInput = document.createElement('input');
        fileInput.type = 'hidden';
        fileInput.name = 'backup_file';
        fileInput.value = fileName;
        form.appendChild(fileInput);

        document.body.appendChild(form);
        form.submit();
    }
}

function confirmRestore(fileName, csrfToken) {
    let message = "¡ADVERTENCIA EXTREMA!\\n\\nEstás a punto de restaurar la base de datos desde el archivo '" + fileName + "'.\\n\\nEsta acción SOBREESCRIBIRÁ TODOS LOS DATOS ACTUALES de la base de datos con el contenido de este backup.\\n\\nNO HAY DESHACER para esta operación.\\n\\n¿Estás ABSOLUTAMENTE SEGURO de que deseas continuar?";
    if (confirm(message)) {
        let finalConfirmation = prompt("Para confirmar la restauración, por favor escribe la palabra 'RESTAURAR' (en mayúsculas) en el siguiente campo y presiona Aceptar.\\nNombre del archivo: " + fileName);
        if (finalConfirmation === 'RESTAURAR') {
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'backup_acciones.php';
            
            let csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);

            let actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'accion';
            actionInput.value = 'restaurar_backup';
            form.appendChild(actionInput);

            let fileInput = document.createElement('input');
            fileInput.type = 'hidden';
            fileInput.name = 'backup_file';
            fileInput.value = fileName;
            form.appendChild(fileInput);

            document.body.appendChild(form);
            form.submit();
        } else {
            alert('Restauración cancelada. La palabra de confirmación no fue correcta.');
        }
    }
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 