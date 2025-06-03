<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

// Acciones de backup (crear, eliminar, restaurar) son POST. Descarga es GET con CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['accion']) && $_GET['accion'] === 'descargar_backup')) {
    $isDownloadAction = ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['accion']) && $_GET['accion'] === 'descargar_backup');
    $csrfToken = $isDownloadAction ? ($_GET['csrf_token'] ?? '') : ($_POST['csrf_token'] ?? '');

    if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        setGlobalMessage("Error de validación CSRF.", "danger");
        logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 'backup_acciones.php');
        header('Location: backup_index.php');
        exit;
    }
    requirePermission('admin_backup', 'manage');
} else {
    setGlobalMessage('Acceso no permitido.', 'danger');
    header('Location: backup_index.php');
    exit;
}

$accion = $_POST['accion'] ?? $_GET['accion'] ?? '';

if (!defined('BACKUP_DIR')) {
    // Esto es un fallback, BACKUP_DIR debe estar en config.php
    define('BACKUP_DIR', __DIR__ . '/../../backups/'); 
}

if ($accion !== 'descargar_backup') {
    if (!is_dir(BACKUP_DIR)) {
        if (!@mkdir(BACKUP_DIR, 0755, true)) { // @ para suprimir error si ya existe (carrera)
            setGlobalMessage('Error: No se pudo crear el dir de backups: ' . htmlspecialchars(BACKUP_DIR), 'danger');
            logSystemEvent($pdo, 'ERROR', 'BACKUP_DIR_CREATION_FAILED', 'Directorio: ' . BACKUP_DIR);
            header('Location: backup_index.php'); exit;
        }
    }
    if (!is_writable(BACKUP_DIR)) {
        setGlobalMessage('Error: Dir de backups no tiene permisos de escritura: ' . htmlspecialchars(BACKUP_DIR), 'danger');
        logSystemEvent($pdo, 'ERROR', 'BACKUP_DIR_NOT_WRITABLE', 'Directorio: ' . BACKUP_DIR);
        header('Location: backup_index.php'); exit;
    }
}

try {
    if ($accion === 'crear_backup') {
        $backup_file = BACKUP_DIR . 'backup_' . DB_NAME . '_' . date('Y-m-d_H-i-s') . '.sql';
        $command = sprintf("mysqldump --host=%s --user=%s --password=%s %s --result-file=%s", 
            escapeshellarg(DB_HOST), escapeshellarg(DB_USER), escapeshellarg(DB_PASS), escapeshellarg(DB_NAME), escapeshellarg($backup_file)
        );
        exec($command . ' 2>&1', $output, $return_var);
        if ($return_var === 0 && file_exists($backup_file)) {
            setGlobalMessage('Backup creado: ' . htmlspecialchars(basename($backup_file)), 'success');
            logSystemEvent($pdo, 'INFO', 'BACKUP_CREATED', 'Archivo: ' . basename($backup_file));
        } else {
            setGlobalMessage('Error al crear backup: ' . implode("\n", array_map('htmlspecialchars', $output)), 'danger');
            logSystemEvent($pdo, 'ERROR', 'BACKUP_CREATION_FAILED', 'Comando: ' . $command . ' Salida: ' . implode("\n", $output));
        }
        header('Location: backup_index.php'); exit;

    } elseif ($accion === 'eliminar_backup') {
        $backup_file_name = basename($_POST['backup_file'] ?? '');
        if (empty($backup_file_name)) {
            setGlobalMessage('Nombre de archivo no proporcionado.', 'warning');
        } else {
            $backup_file_path = realpath(BACKUP_DIR . $backup_file_name);
            if ($backup_file_path && strpos($backup_file_path, realpath(BACKUP_DIR)) === 0 && file_exists($backup_file_path)) {
                if (unlink($backup_file_path)) {
                    setGlobalMessage('Backup ' . htmlspecialchars($backup_file_name) . ' eliminado.', 'success');
                    logSystemEvent($pdo, 'INFO', 'BACKUP_DELETED', 'Archivo: ' . $backup_file_name);
                } else {
                    setGlobalMessage('Error al eliminar backup ' . htmlspecialchars($backup_file_name) . '.', 'danger');
                    logSystemEvent($pdo, 'ERROR', 'BACKUP_DELETE_FAILED', 'Archivo: ' . $backup_file_name);
                }
            } else {
                setGlobalMessage('Archivo no válido o no encontrado: ' . htmlspecialchars($backup_file_name), 'danger');
                logSystemEvent($pdo, 'WARNING', 'BACKUP_DELETE_INVALID_FILE', 'Archivo: ' . $backup_file_name);
            }
        }
        header('Location: backup_index.php'); exit;

    } elseif ($accion === 'descargar_backup') {
        $backup_file_name = basename($_GET['file'] ?? '');
        if (empty($backup_file_name)) {
            setGlobalMessage('Nombre de archivo de backup no proporcionado para descarga.', 'warning');
            header('Location: backup_index.php');
            exit;
        }

        $sane_backup_file_name = basename($backup_file_name); // Sanear nombre
        $backup_file_path = realpath(BACKUP_DIR . $sane_backup_file_name);

        if ($backup_file_path && strpos($backup_file_path, realpath(BACKUP_DIR)) === 0 && file_exists($backup_file_path) && is_readable($backup_file_path)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream'); // O application/sql, application/x-sql
            header('Content-Disposition: attachment; filename="' . $sane_backup_file_name . '"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            header('Content-Length: ' . filesize($backup_file_path));
            readfile($backup_file_path);
            logSystemEvent($pdo, 'INFO', 'BACKUP_DOWNLOADED', 'Archivo: ' . $sane_backup_file_name);
            exit;
        } else {
            setGlobalMessage('Archivo de backup no válido, no encontrado o no legible: ' . htmlspecialchars($sane_backup_file_name), 'danger');
            logSystemEvent($pdo, 'WARNING', 'BACKUP_DOWNLOAD_INVALID_FILE', 'Archivo: ' . $sane_backup_file_name . ', Path intentado: ' . BACKUP_DIR . $sane_backup_file_name);
            header('Location: backup_index.php');
            exit;
        }

    } elseif ($accion === 'restaurar_backup') {
        $backup_file_name = basename($_POST['backup_file'] ?? '');
        if (empty($backup_file_name)) {
            setGlobalMessage('Nombre de archivo no proporcionado para restaurar.', 'warning');
            header('Location: backup_index.php'); exit;
        }
        $backup_file_path = realpath(BACKUP_DIR . $backup_file_name);
        if (!$backup_file_path || strpos($backup_file_path, realpath(BACKUP_DIR)) !== 0 || !file_exists($backup_file_path) || !is_readable($backup_file_path)) {
            setGlobalMessage('Archivo de backup no válido, no encontrado o no legible: ' . htmlspecialchars($backup_file_name), 'danger');
            logSystemEvent($pdo, 'ERROR', 'BACKUP_RESTORE_INVALID_FILE', 'Archivo: ' . $backup_file_name);
            header('Location: backup_index.php'); exit;
        }

        // Comando para restaurar usando mysql client. ¡PELIGROSO!
        $command = sprintf("mysql --host=%s --user=%s --password=%s %s < %s",
            escapeshellarg(DB_HOST), escapeshellarg(DB_USER), escapeshellarg(DB_PASS), escapeshellarg(DB_NAME), escapeshellarg($backup_file_path)
        );
        exec($command . ' 2>&1', $output, $return_var);

        if ($return_var === 0) {
            setGlobalMessage('Base de datos restaurada exitosamente desde: ' . htmlspecialchars($backup_file_name) . '. Se recomienda verificar la integridad de los datos.', 'success');
            logSystemEvent($pdo, 'CRITICAL', 'BACKUP_RESTORED', 'Restaurado desde: ' . $backup_file_name . '. Salida: ' . implode("\n", $output));
        } else {
            setGlobalMessage('Error CRÍTICO al restaurar la base de datos: ' . implode("\n", array_map('htmlspecialchars', $output)), 'danger');
            logSystemEvent($pdo, 'CRITICAL', 'BACKUP_RESTORE_FAILED', 'Archivo: ' . $backup_file_name . ' Comando: ' . $command . ' Salida: ' . implode("\n", $output));
        }
        header('Location: backup_index.php'); exit;

    } else {
        setGlobalMessage('Acción desconocida.', 'danger');
        logSystemEvent($pdo, 'WARNING', 'BACKUP_UNKNOWN_ACTION', 'Acción: ' . $accion);
        header('Location: backup_index.php'); exit;
    }

} catch (Exception $e) {
    setGlobalMessage('Error al procesar la solicitud de backup: ' . htmlspecialchars($e->getMessage()), 'danger');
    logSystemEvent($pdo, 'ERROR', 'BACKUP_ACTION_EXCEPTION', $e->getMessage());
    header('Location: backup_index.php');
    exit;
}
?> 