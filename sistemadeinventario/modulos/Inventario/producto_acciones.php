<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado
require_once __DIR__ . '/../../includes/functions.php';

// Directorio de subida para las imágenes de los productos
define('UPLOAD_DIR_PRODUCTOS', __DIR__ . '/../../assets/uploads/productos/');
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    // Validar CSRF token para todas las acciones POST
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent(
            $pdo,
            'SECURITY',
            'CSRF_VALIDATION_FAILED',
            'Intento de operación con token CSRF inválido o ausente en productos.',
            'Inventario - Productos',
            [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'post_data_keys' => array_keys($_POST)
            ]
        );
        header('Location: ' . APP_URL . '/Modulos/Inventario/index.php?status=csrf_error');
        exit;
    }

    $accion = $_POST['accion'];
    $redirectUrl = APP_URL . '/Modulos/Inventario/index.php';

    try {
        $pdo->beginTransaction();

        switch ($accion) {
            case 'crear':
                requirePermission('inventario_productos', 'create');
                // Validación básica (se puede expandir)
                $nombre = sanitizeInput($_POST['nombre'] ?? '');
                $codigo = sanitizeInput($_POST['codigo'] ?? '');
                $precio_venta = filter_var($_POST['precio_venta'] ?? '0', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);
                
                if (empty($nombre) || empty($codigo) || $precio_venta === false || $categoria_id === false) {
                    $_SESSION['form_error'] = "Nombre, código, precio de venta y categoría son obligatorios.";
                    $_SESSION['form_data'] = $_POST;
                    header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php');
                    exit;
                }

                // Comprobar si el código ya existe
                $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? AND activo = 1");
                $stmtCheck->execute([$codigo]);
                if ($stmtCheck->fetch()) {
                    $_SESSION['form_error'] = "El código de producto \'{$codigo}\' ya existe.";
                    $_SESSION['form_data'] = $_POST;
                    header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php');
                    exit;
                }

                $imagen_nombre = null;
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
                    $imagen_nombre = handleImageUpload($_FILES['imagen'], UPLOAD_DIR_PRODUCTOS, MAX_FILE_SIZE, $allowedMimeTypes);
                    if ($imagen_nombre === false) { // Error en la subida
                        // El error ya se almacena en la sesión dentro de handleImageUpload
                        $_SESSION['form_data'] = $_POST;
                        header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php');
                        exit;
                    }
                }

                $sql = "INSERT INTO productos (nombre, codigo, descripcion, categoria_id, lugar_id, stock, stock_minimo, precio_compra, precio_venta, imagen, usuario_id_crea, fecha_creacion) \
                        VALUES (:nombre, :codigo, :descripcion, :categoria_id, :lugar_id, :stock, :stock_minimo, :precio_compra, :precio_venta, :imagen, :usuario_id, NOW())";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':nombre' => $nombre,
                    ':codigo' => $codigo,
                    ':descripcion' => sanitizeInput($_POST['descripcion'] ?? null),
                    ':categoria_id' => $categoria_id,
                    ':lugar_id' => empty($_POST['lugar_id']) ? null : (int)$_POST['lugar_id'],
                    ':stock' => (int)($_POST['stock'] ?? 0),
                    ':stock_minimo' => (int)($_POST['stock_minimo'] ?? 1),
                    ':precio_compra' => parseCurrency($_POST['precio_compra'] ?? '0.00'),
                    ':precio_venta' => parseCurrency($precio_venta),
                    ':imagen' => $imagen_nombre,
                    ':usuario_id' => $_SESSION['user_id']
                ];
                $stmt->execute($params);
                $producto_creado_id = $pdo->lastInsertId();
                logSystemEvent($pdo, 'INFO', 'PRODUCT_CREATE', "Producto '{$nombre}' (ID: {$producto_creado_id}) creado.", 'Inventario - Productos', ['producto_id' => $producto_creado_id, 'codigo' => $codigo, 'nombre' => $nombre]);
                $redirectUrl .= '?status=success_create';
                break;

            case 'actualizar':
                requirePermission('inventario_productos', 'edit');
                $producto_id = filter_var($_POST['producto_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$producto_id) {
                    $redirectUrl .= '?status=error';
                    break;
                }

                // Validación básica
                $nombre = sanitizeInput($_POST['nombre'] ?? '');
                $codigo = sanitizeInput($_POST['codigo'] ?? '');
                $precio_venta = filter_var($_POST['precio_venta'] ?? '0', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                $categoria_id = filter_var($_POST['categoria_id'] ?? '', FILTER_VALIDATE_INT);

                if (empty($nombre) || empty($codigo) || $precio_venta === false || $categoria_id === false) {
                    $_SESSION['form_error'] = "Nombre, código, precio de venta y categoría son obligatorios.";
                    $_SESSION['form_data'] = $_POST; // Guardar datos para repopular
                    header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php?id=' . $producto_id);
                    exit;
                }
                
                // Comprobar si el código ya existe para OTRO producto
                $stmtCheck = $pdo->prepare("SELECT id FROM productos WHERE codigo = ? AND id != ? AND activo = 1");
                $stmtCheck->execute([$codigo, $producto_id]);
                if ($stmtCheck->fetch()) {
                    $_SESSION['form_error'] = "El código de producto \'{$codigo}\' ya está en uso por otro producto.";
                    $_SESSION['form_data'] = $_POST;
                    header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php?id=' . $producto_id);
                    exit;
                }

                $imagen_actual = $_POST['imagen_actual'] ?? null;
                $imagen_nombre = $imagen_actual; // Por defecto, mantener la imagen actual

                // Si se marcó eliminar imagen actual
                if (isset($_POST['eliminar_imagen']) && $_POST['eliminar_imagen'] == '1') {
                    if ($imagen_actual && file_exists(UPLOAD_DIR_PRODUCTOS . $imagen_actual)) {
                        unlink(UPLOAD_DIR_PRODUCTOS . $imagen_actual);
                    }
                    $imagen_nombre = null;
                }

                // Si se sube una nueva imagen
                if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
                    // Eliminar la imagen anterior si existe y es diferente de la nueva (aunque handleImageUpload genera nombres únicos)
                    if ($imagen_actual && file_exists(UPLOAD_DIR_PRODUCTOS . $imagen_actual)) {
                        unlink(UPLOAD_DIR_PRODUCTOS . $imagen_actual);
                    }
                    $imagen_nombre = handleImageUpload($_FILES['imagen'], UPLOAD_DIR_PRODUCTOS, MAX_FILE_SIZE, $allowedMimeTypes);
                    if ($imagen_nombre === false) {
                        $_SESSION['form_data'] = $_POST;
                        header('Location: ' . APP_URL . '/Modulos/Inventario/producto_form.php?id=' . $producto_id);
                        exit;
                    }
                }
                
                $sql = "UPDATE productos SET 
                            nombre = :nombre, 
                            codigo = :codigo, 
                            descripcion = :descripcion, 
                            categoria_id = :categoria_id, 
                            lugar_id = :lugar_id, 
                            stock = :stock, 
                            stock_minimo = :stock_minimo, 
                            precio_compra = :precio_compra, 
                            precio_venta = :precio_venta, 
                            imagen = :imagen,
                            usuario_id_actualiza = :usuario_id,
                            fecha_actualizacion = NOW()
                        WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $params = [
                    ':nombre' => $nombre,
                    ':codigo' => $codigo,
                    ':descripcion' => sanitizeInput($_POST['descripcion'] ?? null),
                    ':categoria_id' => $categoria_id,
                    ':lugar_id' => empty($_POST['lugar_id']) ? null : (int)$_POST['lugar_id'],
                    ':stock' => (int)($_POST['stock'] ?? 0),
                    ':stock_minimo' => (int)($_POST['stock_minimo'] ?? 1),
                    ':precio_compra' => parseCurrency($_POST['precio_compra'] ?? '0.00'),
                    ':precio_venta' => parseCurrency($precio_venta),
                    ':imagen' => $imagen_nombre,
                    ':usuario_id' => $_SESSION['user_id'],
                    ':id' => $producto_id
                ];
                $stmt->execute($params);
                logSystemEvent($pdo, 'INFO', 'PRODUCT_UPDATE', "Producto '{$nombre}' (ID: {$producto_id}) actualizado.", 'Inventario - Productos', ['producto_id' => $producto_id, 'codigo' => $codigo, 'nombre' => $nombre, 'changed_fields' => array_keys($params)]);
                $redirectUrl .= '?status=success_update';
                break;

            case 'eliminar_permanente': // Cambiado desde 'eliminar' para coincidir con el form
                requirePermission('inventario_productos', 'delete');
                $producto_id = filter_var($_POST['producto_id'] ?? '', FILTER_VALIDATE_INT);
                if (!$producto_id) {
                    $redirectUrl .= '?status=error';
                    break;
                }

                // Opcional: obtener nombre de imagen para borrarla del servidor
                $stmtImg = $pdo->prepare("SELECT imagen FROM productos WHERE id = ?");
                $stmtImg->execute([$producto_id]);
                $imagen_a_eliminar = $stmtImg->fetchColumn();

                // Eliminar el producto de la base de datos
                // Se podría cambiar por UPDATE productos SET activo = 0 WHERE id = ? si se prefiere desactivar
                $stmt = $pdo->prepare("DELETE FROM productos WHERE id = ?");
                $stmt->execute([$producto_id]);

                if ($stmt->rowCount() > 0) {
                    // Si se eliminó el producto y tenía imagen, borrarla del servidor
                    if ($imagen_a_eliminar && file_exists(UPLOAD_DIR_PRODUCTOS . $imagen_a_eliminar)) {
                        unlink(UPLOAD_DIR_PRODUCTOS . $imagen_a_eliminar);
                    }
                    logSystemEvent($pdo, 'INFO', 'PRODUCT_DELETE_PERMANENT', "Producto ID {$producto_id} eliminado permanentemente.", 'Inventario - Productos', ['producto_id' => $producto_id, 'imagen_eliminada' => $imagen_a_eliminar]);
                    $redirectUrl .= '?status=success_delete';
                } else {
                    logSystemEvent($pdo, 'WARNING', 'PRODUCT_DELETE_NOT_FOUND', "Intento de eliminar permanentemente producto ID {$producto_id} no encontrado o ya eliminado.", 'Inventario - Productos', ['producto_id' => $producto_id]);
                    $redirectUrl .= '?status=error_not_found'; // O algún otro error
                }
                break;
            
            // case 'desactivar': // Ejemplo si se quisiera desactivar en lugar de eliminar
            //     $producto_id = filter_var($_POST['producto_id'] ?? '', FILTER_VALIDATE_INT);
            //     if (!$producto_id) {
            //         $redirectUrl .= '?status=error';
            //         break;
            //     }
            //     $stmt = $pdo->prepare("UPDATE productos SET activo = 0, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?");
            //     $stmt->execute([$_SESSION['user_id'], $producto_id]);
            //     $redirectUrl .= '?status=success_deactivated';
            //     break;

            default:
                // No se requiere permiso específico para acción desconocida, ya que se registrará y redirigirá.
                logSystemEvent($pdo, 'WARNING', 'PRODUCT_UNKNOWN_ACTION', "Acción desconocida '{$accion}' intentada en productos.", 'Inventario - Productos', ['accion' => $accion, 'post_data' => $_POST, 'user_id' => $_SESSION['user_id'] ?? null]);
                $redirectUrl .= '?status=error_unknown_action';
                break;
        }

        $pdo->commit();

    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log("Error en producto_acciones.php: " . $e->getMessage());
        logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD durante acción '{$accion}' en productos. Error: " . $e->getMessage(), 'Inventario - Productos', ['accion' => $accion, 'post_data' => $_POST]);
        // Guardar datos del formulario en sesión para repopular si es relevante
        if ($accion === 'crear' || $accion === 'actualizar') {
            $_SESSION['form_data'] = $_POST;
             $form_page = ($accion === 'crear') ? 'producto_form.php' : 'producto_form.php?id=' . ($_POST['producto_id'] ?? '');
             $_SESSION['form_error'] = "Error de base de datos al procesar la solicitud. Intente nuevamente.";
             header('Location: ' . APP_URL . '/Modulos/Inventario/' . $form_page);
             exit;
        } else {
            $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'status=error_db';
        }
    } catch (Exception $e) { // Para errores de subida de imagen u otros
        $pdo->rollBack();
        error_log("Error general en producto_acciones.php: " . $e->getMessage());
        logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general durante acción '{$accion}' en productos. Error: " . $e->getMessage(), 'Inventario - Productos', ['accion' => $accion, 'post_data' => $_POST]);
         if ($accion === 'crear' || $accion === 'actualizar') {
            $_SESSION['form_data'] = $_POST;
             $form_page = ($accion === 'crear') ? 'producto_form.php' : 'producto_form.php?id=' . ($_POST['producto_id'] ?? '');
             $_SESSION['form_error'] = $e->getMessage(); // Usar el mensaje de la excepción, como el de handleImageUpload
             header('Location: ' . APP_URL . '/Modulos/Inventario/' . $form_page);
             exit;
        } else {
            $redirectUrl .= (strpos($redirectUrl, '?') === false ? '?' : '&') . 'status=error_exception';
        }
    }

    header('Location: ' . $redirectUrl);
    exit;

} else {
    // Redirigir si no es POST o no hay acción
    header('Location: ' . APP_URL . '/Modulos/Inventario/index.php?status=error_invalid_request');
    exit;
}

?> 