<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado
require_once __DIR__ . '/../../includes/functions.php';

// Validar CSRF token para todas las acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent(
            $pdo, 
            'SECURITY',
            'CSRF_VALIDATION_FAILED',
            'Intento de operación con token CSRF inválido o ausente en lugares.',
            'Inventario - Lugares',
            [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'post_data_keys' => array_keys($_POST)
            ]
        );
        header('Location: ' . APP_URL . '/Modulos/Inventario/lugares_index.php');
        exit;
    }
} else { 
    setGlobalMessage("Acceso no permitido.", "danger");
    header('Location: ' . APP_URL . '/Modulos/Inventario/lugares_index.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$redirectUrl = APP_URL . '/Modulos/Inventario/lugares_index.php';

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear':
            requirePermission('inventario_lugares', 'create');
            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $descripcion = sanitizeInput($_POST['descripcion'] ?? null);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $errors = [];

            if (empty($nombre)) {
                $errors[] = "El nombre del lugar es obligatorio.";
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM lugares WHERE nombre = ?");
            $stmtCheck->execute([$nombre]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Ya existe un lugar con el nombre '" . htmlspecialchars($nombre) . "'.";
            }

            if (!empty($errors)) {
                $_SESSION['form_data'] = $_POST;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                $redirectUrl = APP_URL . '/Modulos/Inventario/lugar_form.php';
            } else {
                $sql = "INSERT INTO lugares (nombre, descripcion, activo, usuario_id_crea, fecha_creacion) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $activo, $_SESSION['user_id']]);
                $lugar_creado_id = $pdo->lastInsertId();
                logSystemEvent($pdo, 'INFO', 'PLACE_CREATE', "Lugar '{$nombre}' (ID: {$lugar_creado_id}) creado.", 'Inventario - Lugares', ['lugar_id' => $lugar_creado_id, 'nombre' => $nombre]);
                setGlobalMessage("Lugar '" . htmlspecialchars($nombre) . "' creado exitosamente.", "success");
            }
            break;

        case 'actualizar':
            requirePermission('inventario_lugares', 'edit');
            $lugar_id = filter_var($_POST['lugar_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$lugar_id) {
                setGlobalMessage("ID de lugar no válido.", "danger");
                break; 
            }

            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $descripcion = sanitizeInput($_POST['descripcion'] ?? null);
            $activo = isset($_POST['activo']) ? 1 : 0;
            $errors = [];

            if (empty($nombre)) {
                $errors[] = "El nombre del lugar es obligatorio.";
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM lugares WHERE nombre = ? AND id != ?");
            $stmtCheck->execute([$nombre, $lugar_id]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Ya existe otro lugar con el nombre '" . htmlspecialchars($nombre) . "'.";
            }

            if ($activo == 0) {
                $stmtCheckProductos = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE lugar_id = ? AND activo = 1");
                $stmtCheckProductos->execute([$lugar_id]);
                if ($stmtCheckProductos->fetchColumn() > 0) {
                    $errors[] = "Error: El lugar está en uso por productos activos y no puede ser desactivado desde el formulario. Use la opción del listado.";
                }
            }

            if (!empty($errors)) {
                $_SESSION['form_data'] = $_POST;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                $redirectUrl = APP_URL . '/Modulos/Inventario/lugar_form.php?id=' . $lugar_id;
            } else {
                $sql = "UPDATE lugares SET nombre = ?, descripcion = ?, activo = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $activo, $_SESSION['user_id'], $lugar_id]);
                logSystemEvent($pdo, 'INFO', 'PLACE_UPDATE', "Lugar '{$nombre}' (ID: {$lugar_id}) actualizado.", 'Inventario - Lugares', ['lugar_id' => $lugar_id, 'nombre' => $nombre, 'activo' => $activo]);
                setGlobalMessage("Lugar '" . htmlspecialchars($nombre) . "' actualizado exitosamente.", "success");
            }
            break;

        case 'activar':
        case 'desactivar':
            requirePermission('inventario_lugares', 'toggle_status');
            $lugar_id = filter_var($_POST['lugar_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$lugar_id) {
                setGlobalMessage("ID de lugar no válido.", "danger");
                break;
            }
            $nuevoEstado = ($accion === 'activar') ? 1 : 0;
            $accionTexto = ($accion === 'activar') ? 'activado' : 'desactivado';

            if ($nuevoEstado == 0) { // Si se está desactivando
                $stmtCheckProductos = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE lugar_id = ? AND activo = 1");
                $stmtCheckProductos->execute([$lugar_id]);
                if ($stmtCheckProductos->fetchColumn() > 0) {
                    setGlobalMessage("El lugar no puede ser desactivado porque está en uso por productos activos.", "warning");
                    header('Location: ' . $redirectUrl);
                    exit;
                }
            }

            $sql = "UPDATE lugares SET activo = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nuevoEstado, $_SESSION['user_id'], $lugar_id]);
            logSystemEvent($pdo, 'INFO', ($accion === 'activar' ? 'PLACE_ACTIVATE' : 'PLACE_DEACTIVATE'), "Lugar ID {$lugar_id} " . $accionTexto . ".", 'Inventario - Lugares', ['lugar_id' => $lugar_id, 'nuevo_estado' => $nuevoEstado]);
            setGlobalMessage("Lugar " . $accionTexto . " exitosamente.", "info");
            break;

        case 'eliminar':
            requirePermission('inventario_lugares', 'delete');
            $lugar_id = filter_var($_POST['lugar_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$lugar_id) {
                setGlobalMessage("ID de lugar no válido.", "danger");
                break;
            }

            $stmtCheck = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE lugar_id = ?");
            $stmtCheck->execute([$lugar_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                setGlobalMessage("El lugar no puede ser eliminado porque tiene productos asociados. Considere desactivarlo.", "warning");
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM lugares WHERE id = ?");
            $stmt->execute([$lugar_id]);
            if ($stmt->rowCount() > 0) {
                logSystemEvent($pdo, 'INFO', 'PLACE_DELETE', "Lugar ID {$lugar_id} eliminado.", 'Inventario - Lugares', ['lugar_id' => $lugar_id]);
                setGlobalMessage("Lugar eliminado permanentemente.", "success");
            } else {
                logSystemEvent($pdo, 'WARNING', 'PLACE_DELETE_NOT_FOUND', "Intento de eliminar lugar ID {$lugar_id} no encontrado o ya eliminado.", 'Inventario - Lugares', ['lugar_id' => $lugar_id]);
                setGlobalMessage("Error: No se encontró el lugar para eliminar o ya fue eliminado.", "warning");
            }
            break;

        default:
            setGlobalMessage("Acción desconocida '{$accion}'.", "danger");
            logSystemEvent($pdo, 'WARNING', 'PLACE_UNKNOWN_ACTION', "Acción desconocida '{$accion}' intentada en lugares.", 'Inventario - Lugares', ['accion' => $accion, 'post_data_keys' => array_keys($_POST), 'user_id' => $_SESSION['user_id'] ?? null]);
            break;
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error PDO en lugar_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD durante acción '{$accion}' en lugares. Error: " . $e->getMessage(), 'Inventario - Lugares', ['accion' => $accion, 'post_data_keys' => array_keys($_POST)]);
    setGlobalMessage("Error de base de datos al procesar la solicitud. Consulte los logs.", "danger");
    if (($accion === 'crear' || $accion === 'actualizar') && isset($_POST['nombre'])){
        $_SESSION['form_data'] = $_POST;
        $redirectUrl = APP_URL . '/Modulos/Inventario/lugar_form.php';
        if($accion === 'actualizar' && isset($_POST['lugar_id'])) {
            $redirectUrl .= '?id=' . $_POST['lugar_id'];
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error general en lugar_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general durante acción '{$accion}' en lugares. Error: " . $e->getMessage(), 'Inventario - Lugares', ['accion' => $accion, 'post_data_keys' => array_keys($_POST)]);
    setGlobalMessage("Ocurrió un error inesperado al procesar la solicitud. Consulte los logs.", "danger");
}

header('Location: ' . $redirectUrl);
exit;
?> 