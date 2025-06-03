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
            'Intento de operación con token CSRF inválido o ausente en categorías.',
            'Inventario - Categorías',
            [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'post_data_keys' => array_keys($_POST)
            ]
        );
        header('Location: ' . APP_URL . '/Modulos/Inventario/categorias_index.php');
        exit;
    }
} else { // Si no es POST, redirigir, ya que este script solo maneja POST
    setGlobalMessage("Acceso no permitido.", "danger");
    // No se puede loguear el evento aquí si $pdo no está disponible o la config no cargó
    header('Location: ' . APP_URL . '/Modulos/Inventario/categorias_index.php');
    exit;
}

$accion = $_POST['accion'] ?? '';
$redirectUrl = APP_URL . '/Modulos/Inventario/categorias_index.php';

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear':
            requirePermission('inventario_categorias', 'create');
            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $descripcion = sanitizeInput($_POST['descripcion'] ?? null);
            $activa = isset($_POST['activa']) ? 1 : 0;
            $errors = [];

            if (empty($nombre)) {
                $errors[] = "El nombre de la categoría es obligatorio.";
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ?");
            $stmtCheck->execute([$nombre]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Ya existe una categoría con el nombre '" . htmlspecialchars($nombre) . "'.";
            }

            if (!empty($errors)) {
                $_SESSION['form_data'] = $_POST;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                $redirectUrl = APP_URL . '/Modulos/Inventario/categoria_form.php';
            } else {
                $sql = "INSERT INTO categorias (nombre, descripcion, activa, usuario_id_crea, fecha_creacion) VALUES (?, ?, ?, ?, NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $activa, $_SESSION['user_id']]);
                $categoria_creada_id = $pdo->lastInsertId();
                logSystemEvent($pdo, 'INFO', 'CATEGORY_CREATE', "Categoría '{$nombre}' (ID: {$categoria_creada_id}) creada.", 'Inventario - Categorías', ['categoria_id' => $categoria_creada_id, 'nombre' => $nombre]);
                setGlobalMessage("Categoría '" . htmlspecialchars($nombre) . "' creada exitosamente.", "success");
            }
            break;

        case 'actualizar':
            requirePermission('inventario_categorias', 'edit');
            $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$categoria_id) {
                setGlobalMessage("ID de categoría no válido.", "danger");
                break; 
            }

            $nombre = sanitizeInput($_POST['nombre'] ?? '');
            $descripcion = sanitizeInput($_POST['descripcion'] ?? null);
            $activa = isset($_POST['activa']) ? 1 : 0;
            $errors = [];

            if (empty($nombre)) {
                $errors[] = "El nombre de la categoría es obligatorio.";
            }

            $stmtCheck = $pdo->prepare("SELECT id FROM categorias WHERE nombre = ? AND id != ?");
            $stmtCheck->execute([$nombre, $categoria_id]);
            if ($stmtCheck->fetch()) {
                $errors[] = "Ya existe otra categoría con el nombre '" . htmlspecialchars($nombre) . "'.";
            }
            
            // No se permite desactivar si tiene productos activos (esta validación ya está en 'desactivar' también)
            // pero es bueno tenerla aquí si el checkbox de 'activa' se desmarca en el form.
            if ($activa == 0) {
                $stmtCheckProductos = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE categoria_id = ? AND activo = 1");
                $stmtCheckProductos->execute([$categoria_id]);
                if ($stmtCheckProductos->fetchColumn() > 0) {
                    $errors[] = "Error: La categoría está en uso por productos activos y no puede ser desactivada desde el formulario. Utilice la opción 'Desactivar' del listado.";
                }
            }

            if (!empty($errors)) {
                $_SESSION['form_data'] = $_POST;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                $redirectUrl = APP_URL . '/Modulos/Inventario/categoria_form.php?id=' . $categoria_id;
            } else {
                $sql = "UPDATE categorias SET nombre = ?, descripcion = ?, activa = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$nombre, $descripcion, $activa, $_SESSION['user_id'], $categoria_id]);
                logSystemEvent($pdo, 'INFO', 'CATEGORY_UPDATE', "Categoría '{$nombre}' (ID: {$categoria_id}) actualizada.", 'Inventario - Categorías', ['categoria_id' => $categoria_id, 'nombre' => $nombre, 'activa' => $activa]);
                setGlobalMessage("Categoría '" . htmlspecialchars($nombre) . "' actualizada exitosamente.", "success");
            }
            break;

        case 'activar':
        case 'desactivar':
            requirePermission('inventario_categorias', 'toggle_status');
            $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$categoria_id) {
                setGlobalMessage("ID de categoría no válido.", "danger");
                break;
            }
            $nuevoEstado = ($accion === 'activar') ? 1 : 0;
            $accionTexto = ($accion === 'activar') ? 'activada' : 'desactivada';

            if ($nuevoEstado == 0) { // Si se está desactivando
                $stmtCheckProductos = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE categoria_id = ? AND activo = 1");
                $stmtCheckProductos->execute([$categoria_id]);
                if ($stmtCheckProductos->fetchColumn() > 0) {
                    setGlobalMessage("La categoría no puede ser desactivada porque está en uso por productos activos.", "warning");
                    // No se realiza la acción, se rompe y se redirige
                    // $pdo->rollBack(); // No es necesario aquí si no hemos hecho commit y no salimos del script.
                    header('Location: ' . $redirectUrl); // Redirige, el commit no se hará.
                    exit;
                }
            }

            $sql = "UPDATE categorias SET activa = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nuevoEstado, $_SESSION['user_id'], $categoria_id]);
            logSystemEvent($pdo, 'INFO', ($accion === 'activar' ? 'CATEGORY_ACTIVATE' : 'CATEGORY_DEACTIVATE'), "Categoría ID {$categoria_id} " . $accionTexto . ".", 'Inventario - Categorías', ['categoria_id' => $categoria_id, 'nuevo_estado' => $nuevoEstado]);
            setGlobalMessage("Categoría " . $accionTexto . " exitosamente.", "info");
            break;

        case 'eliminar':
            requirePermission('inventario_categorias', 'delete');
            $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$categoria_id) {
                setGlobalMessage("ID de categoría no válido.", "danger");
                break;
            }

            $stmtCheck = $pdo->prepare("SELECT COUNT(id) FROM productos WHERE categoria_id = ?");
            $stmtCheck->execute([$categoria_id]);
            if ($stmtCheck->fetchColumn() > 0) {
                setGlobalMessage("La categoría no puede ser eliminada porque tiene productos asociados. Considere desactivarla.", "warning");
                // $pdo->rollBack(); // No es necesario aquí.
                header('Location: ' . $redirectUrl);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM categorias WHERE id = ?");
            $stmt->execute([$categoria_id]);
            if ($stmt->rowCount() > 0) {
                logSystemEvent($pdo, 'INFO', 'CATEGORY_DELETE', "Categoría ID {$categoria_id} eliminada.", 'Inventario - Categorías', ['categoria_id' => $categoria_id]);
                setGlobalMessage("Categoría eliminada permanentemente.", "success");
            } else {
                logSystemEvent($pdo, 'WARNING', 'CATEGORY_DELETE_NOT_FOUND', "Intento de eliminar categoría ID {$categoria_id} no encontrada o ya eliminada.", 'Inventario - Categorías', ['categoria_id' => $categoria_id]);
                setGlobalMessage("Error: No se encontró la categoría para eliminar o ya fue eliminada.", "warning");
            }
            break;

        default:
            // No se necesita requirePermission para acción desconocida.
            setGlobalMessage("Acción desconocida '{$accion}'.", "danger");
            logSystemEvent($pdo, 'WARNING', 'CATEGORY_UNKNOWN_ACTION', "Acción desconocida '{$accion}' intentada en categorías.", 'Inventario - Categorías', ['accion' => $accion, 'post_data' => $_POST, 'user_id' => $_SESSION['user_id'] ?? null]);
            break;
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error PDO en categoria_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD durante acción '{$accion}' en categorías. Error: " . $e->getMessage(), 'Inventario - Categorías', ['accion' => $accion, 'post_data_keys' => array_keys($_POST)]);
    setGlobalMessage("Error de base de datos al procesar la solicitud. Consulte los logs.", "danger");
    if (($accion === 'crear' || $accion === 'actualizar') && isset($_POST['nombre'])){
        $_SESSION['form_data'] = $_POST;
        $redirectUrl = APP_URL . '/Modulos/Inventario/categoria_form.php';
        if($accion === 'actualizar' && isset($_POST['categoria_id'])) {
            $redirectUrl .= '?id=' . $_POST['categoria_id'];
        }
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("Error general en categoria_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general durante acción '{$accion}' en categorías. Error: " . $e->getMessage(), 'Inventario - Categorías', ['accion' => $accion, 'post_data_keys' => array_keys($_POST)]);
    setGlobalMessage("Ocurrió un error inesperado al procesar la solicitud. Consulte los logs.", "danger");
}

header('Location: ' . $redirectUrl);
exit;
?> 