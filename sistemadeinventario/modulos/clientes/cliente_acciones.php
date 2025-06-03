<?php
require_once __DIR__ . '/../../config.php';
// requireLogin() se llamará dentro de requirePermission
require_once __DIR__ . '/../../includes/functions.php';

$accion = $_POST['accion'] ?? '';
$request_method = $_SERVER['REQUEST_METHOD'];

// Validar CSRF token - ahora solo para POST
if ($request_method === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 
            'Token CSRF inválido en clientes.', 'Clientes', 
            ['accion' => $accion, 'method' => $request_method, 'request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'post_data_keys' => array_keys($_POST)]
        );
        header('Location: ' . APP_URL . '/Modulos/Clientes/index.php?status=csrf_error');
        exit;
    }
} else {
    // Si no es POST, es una solicitud no válida para este script de acciones
    setGlobalMessage("Método de solicitud no permitido.", "danger");
    logSystemEvent($pdo, 'WARNING', 'INVALID_REQUEST_METHOD', 
        'Intento de acceso a cliente_acciones.php sin método POST.', 'Clientes', 
        ['request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $request_method]
    );
    header('Location: ' . APP_URL . '/Modulos/Clientes/index.php?status=invalid_method');
    exit;
}

// Aplicar permisos ANTES de la transacción y el switch
switch ($accion) {
    case 'crear':
        requirePermission('clientes', 'create');
        break;
    case 'actualizar':
        requirePermission('clientes', 'edit');
        break;
    case 'activar':
    case 'desactivar':
        requirePermission('clientes', 'toggle_status');
        break;
    case 'eliminar_permanente':
        requirePermission('clientes', 'delete');
        break;
    default:
        // Para acciones desconocidas, no se requiere un permiso específico aquí,
        // se manejará como error más adelante.
        break;
}

$redirectUrl = APP_URL . '/Modulos/Clientes/index.php';

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear':
            $datos_cliente = $_POST['cliente'] ?? [];
            
            $nombre = sanitizeInput($datos_cliente['nombre'] ?? '');
            $apellido = sanitizeInput($datos_cliente['apellido'] ?? '');
            $tipo_documento_id = filter_var($datos_cliente['tipo_documento_id'] ?? '', FILTER_VALIDATE_INT);
            $numero_documento = sanitizeInput($datos_cliente['numero_documento'] ?? '');
            $email = !empty($datos_cliente['email']) ? sanitizeInput($datos_cliente['email']) : null;
            $telefono = !empty($datos_cliente['telefono']) ? sanitizeInput($datos_cliente['telefono']) : null;
            $direccion = !empty($datos_cliente['direccion']) ? sanitizeInput($datos_cliente['direccion']) : null;
            $activo = isset($datos_cliente['activo']) ? (int)$datos_cliente['activo'] : 1;

            $errors = [];
            if (empty($nombre)) $errors[] = "El nombre es obligatorio.";
            if (empty($apellido)) $errors[] = "El apellido es obligatorio.";
            if (empty($tipo_documento_id)) $errors[] = "Debe seleccionar un tipo de documento.";
            if (empty($numero_documento)) $errors[] = "El número de documento es obligatorio.";
            // Aquí podrías añadir validaciones de formato para numero_documento según el tipo_documento_id si fuera necesario

            if (!empty($errors)) {
                $_SESSION['form_data']['cliente'] = $datos_cliente;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                header('Location: ' . APP_URL . '/Modulos/Clientes/cliente_form.php');
                exit;
            }

            // Verificar unicidad (tipo_documento_id, numero_documento)
            $stmt_check_doc = $pdo->prepare("SELECT id FROM clientes WHERE tipo_documento_id = ? AND numero_documento = ?");
            $stmt_check_doc->execute([$tipo_documento_id, $numero_documento]);
            if ($stmt_check_doc->fetch()) {
                $_SESSION['form_data']['cliente'] = $datos_cliente;
                setGlobalMessage("Ya existe un cliente con el mismo tipo y número de documento.", "warning");
                header('Location: ' . APP_URL . '/Modulos/Clientes/cliente_form.php');
                exit;
            }

            $sql = "INSERT INTO clientes (nombre, apellido, tipo_documento_id, numero_documento, email, telefono, direccion, activo, usuario_id_crea, fecha_creacion) 
                    VALUES (:nombre, :apellido, :tipo_documento_id, :numero_documento, :email, :telefono, :direccion, :activo, :usuario_id, NOW())";
            $stmt = $pdo->prepare($sql);
            $params = [
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':tipo_documento_id' => $tipo_documento_id,
                ':numero_documento' => $numero_documento,
                ':email' => $email,
                ':telefono' => $telefono,
                ':direccion' => $direccion,
                ':activo' => $activo,
                ':usuario_id' => $_SESSION['user_id']
            ];
            $stmt->execute($params);
            $cliente_creado_id = $pdo->lastInsertId();
            logSystemEvent($pdo, 'INFO', 'CLIENT_CREATE', "Cliente '{$nombre} {$apellido}' (ID: {$cliente_creado_id}) creado. Documento: {$tipo_documento_id}-{$numero_documento}", 'Clientes', ['cliente_id' => $cliente_creado_id, 'tipo_doc' => $tipo_documento_id, 'num_doc' => $numero_documento]);
            setGlobalMessage("Cliente '" . htmlspecialchars($nombre) . " " . htmlspecialchars($apellido) . "' creado exitosamente.", "success");
            $redirectUrl .= '?status=success_create';
            break;

        case 'actualizar':
            $cliente_id = filter_var($_POST['cliente_id'] ?? '', FILTER_VALIDATE_INT);
            $datos_cliente = $_POST['cliente'] ?? [];

            if (!$cliente_id) {
                setGlobalMessage("ID de cliente no válido.", "danger");
                break; 
            }
            
            $nombre = sanitizeInput($datos_cliente['nombre'] ?? '');
            $apellido = sanitizeInput($datos_cliente['apellido'] ?? '');
            $tipo_documento_id = filter_var($datos_cliente['tipo_documento_id'] ?? '', FILTER_VALIDATE_INT);
            $numero_documento = sanitizeInput($datos_cliente['numero_documento'] ?? '');
            $email = !empty($datos_cliente['email']) ? sanitizeInput($datos_cliente['email']) : null;
            $telefono = !empty($datos_cliente['telefono']) ? sanitizeInput($datos_cliente['telefono']) : null;
            $direccion = !empty($datos_cliente['direccion']) ? sanitizeInput($datos_cliente['direccion']) : null;
            $activo = isset($datos_cliente['activo']) ? (int)$datos_cliente['activo'] : 0;

            $errors = [];
            if (empty($nombre)) $errors[] = "El nombre es obligatorio.";
            if (empty($apellido)) $errors[] = "El apellido es obligatorio.";
            if (empty($tipo_documento_id)) $errors[] = "Debe seleccionar un tipo de documento.";
            if (empty($numero_documento)) $errors[] = "El número de documento es obligatorio.";

            if (!empty($errors)) {
                $_SESSION['form_data']['cliente'] = $datos_cliente;
                foreach ($errors as $error) setGlobalMessage($error, "danger");
                header('Location: ' . APP_URL . '/Modulos/Clientes/cliente_form.php?id=' . $cliente_id);
                exit;
            }

            // Verificar unicidad (tipo_documento_id, numero_documento) excluyendo el cliente actual
            $stmt_check_doc = $pdo->prepare("SELECT id FROM clientes WHERE tipo_documento_id = ? AND numero_documento = ? AND id != ?");
            $stmt_check_doc->execute([$tipo_documento_id, $numero_documento, $cliente_id]);
            if ($stmt_check_doc->fetch()) {
                $_SESSION['form_data']['cliente'] = $datos_cliente;
                setGlobalMessage("Ya existe otro cliente con el mismo tipo y número de documento.", "warning");
                header('Location: ' . APP_URL . '/Modulos/Clientes/cliente_form.php?id=' . $cliente_id);
                exit;
            }
            
            $sql = "UPDATE clientes SET 
                        nombre = :nombre, 
                        apellido = :apellido, 
                        tipo_documento_id = :tipo_documento_id,
                        numero_documento = :numero_documento,
                        email = :email, 
                        telefono = :telefono, 
                        direccion = :direccion, 
                        activo = :activo, 
                        usuario_id_actualiza = :usuario_id, 
                        fecha_actualizacion = NOW()
                    WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $params = [
                ':nombre' => $nombre,
                ':apellido' => $apellido,
                ':tipo_documento_id' => $tipo_documento_id,
                ':numero_documento' => $numero_documento,
                ':email' => $email,
                ':telefono' => $telefono,
                ':direccion' => $direccion,
                ':activo' => $activo,
                ':usuario_id' => $_SESSION['user_id'],
                ':id' => $cliente_id
            ];
            $stmt->execute($params);
            logSystemEvent($pdo, 'INFO', 'CLIENT_UPDATE', "Cliente '{$nombre} {$apellido}' (ID: {$cliente_id}) actualizado. Documento: {$tipo_documento_id}-{$numero_documento}", 'Clientes', ['cliente_id' => $cliente_id, 'tipo_doc' => $tipo_documento_id, 'num_doc' => $numero_documento]);
            setGlobalMessage("Cliente '" . htmlspecialchars($nombre) . " " . htmlspecialchars($apellido) . "' actualizado exitosamente.", "success");
            $redirectUrl .= '?status=success_update';
            break;

        case 'desactivar':
        case 'activar':
            $cliente_id = filter_var($_POST['cliente_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$cliente_id) {
                setGlobalMessage("ID de cliente no válido.", "danger");
                break;
            }
            $nuevo_estado = ($accion === 'activar') ? 1 : 0;
            $stmt = $pdo->prepare("UPDATE clientes SET activo = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?");
            $stmt->execute([$nuevo_estado, $_SESSION['user_id'], $cliente_id]);
            $accion_texto = ($accion === 'activar') ? 'activado' : 'desactivado';
            logSystemEvent($pdo, 'INFO', ($accion === 'activar' ? 'CLIENT_ACTIVATE' : 'CLIENT_DEACTIVATE'), "Cliente ID {$cliente_id} " . $accion_texto . ".", 'Clientes', ['cliente_id' => $cliente_id, 'nuevo_estado' => $nuevo_estado]);
            setGlobalMessage("Cliente " . $accion_texto . " exitosamente.", "info");
            $redirectUrl .= ($nuevo_estado == 1) ? '?status=success_activate' : '?status=success_deactivate';
            break;

        case 'eliminar_permanente':
            $cliente_id = filter_var($_POST['cliente_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$cliente_id) {
                setGlobalMessage("ID de cliente no válido.", "danger");
                break;
            }
            $stmt = $pdo->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->execute([$cliente_id]);
            if ($stmt->rowCount() > 0) {
                 logSystemEvent($pdo, 'INFO', 'CLIENT_DELETE_PERMANENT', "Cliente ID {$cliente_id} eliminado permanentemente.", 'Clientes', ['cliente_id' => $cliente_id]);
                 setGlobalMessage("Cliente eliminado permanentemente.", "success");
                 $redirectUrl .= '?status=success_delete';
            } else {
                 logSystemEvent($pdo, 'WARNING', 'CLIENT_DELETE_NOT_FOUND', "Intento de eliminar permanentemente cliente ID {$cliente_id} no encontrado o ya eliminado.", 'Clientes', ['cliente_id' => $cliente_id]);
                 setGlobalMessage("No se pudo eliminar el cliente o ya no existía.", "warning");
                 $redirectUrl .= '?status=error_delete_notfound';
            }
            break;

        default:
            logSystemEvent($pdo, 'WARNING', 'CLIENT_UNKNOWN_ACTION', "Acción desconocida '{$accion}' intentada en clientes.", 'Clientes', ['accion' => $accion, 'request_data' => $_REQUEST]);
            setGlobalMessage("Acción no reconocida: " . htmlspecialchars($accion), "danger");
            break;
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error PDO en cliente_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD: " . $e->getMessage(), 'Clientes', ['accion' => $accion]);
    setGlobalMessage("Error de base de datos: " . htmlspecialchars($e->getMessage()), "danger");
    // ... (resto del manejo de excepciones sin cambios) ...
    if ($accion === 'crear' || $accion === 'actualizar') {
        $_SESSION['form_data']['cliente'] = $_POST['cliente'] ?? [];
        $form_action_page = ($accion === 'crear') ? 'cliente_form.php' : 'cliente_form.php?id=' . ($_POST['cliente_id'] ?? '');
        header('Location: ' . APP_URL . '/Modulos/Clientes/' . $form_action_page);
        exit;
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general en cliente_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general: " . $e->getMessage(), 'Clientes', ['accion' => $accion]);
    setGlobalMessage("Error general: " . htmlspecialchars($e->getMessage()), "danger");
}

header('Location: ' . $redirectUrl);
exit;
?> 