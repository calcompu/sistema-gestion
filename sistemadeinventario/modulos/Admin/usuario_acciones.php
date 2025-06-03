<?php
require_once __DIR__ . '/../../config.php';
requirePermission('admin_usuarios', 'manage');
require_once __DIR__ . '/../../includes/functions.php';

// Roles definidos (igual que en usuario_form.php para validación)
$roles_sistema_acciones = [
    'admin' => 'Administrador',
    'editor' => 'Editor',
    'usuario' => 'Usuario'
];

// Validar CSRF token para todas las acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 'Intento de operación POST con token CSRF inválido o ausente en gestión de usuarios.', 'Admin - Usuarios', ['request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'request_method' => 'POST', 'post_data_keys' => array_keys($_POST)]);
        header('Location: ' . APP_URL . '/Modulos/Admin/usuarios_index.php');
        exit;
    }
}

$accion = $_POST['accion'] ?? '';
$redirectUrl = APP_URL . '/Modulos/Admin/usuarios_index.php'; // URL de redirección por defecto

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                setGlobalMessage("Método no permitido.", "danger");
                break;
            }

            $username = sanitizeInput($_POST['username'] ?? '');
            $nombre_completo = sanitizeInput($_POST['nombre_completo'] ?? null);
            $email = filter_var(sanitizeInput($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $rol = sanitizeInput($_POST['rol'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            $activo = isset($_POST['activo']) ? 1 : 0;

            // Validaciones
            $errors = [];
            if (empty($username)) $errors[] = "El nombre de usuario es obligatorio.";
            if (strlen($username) > 50) $errors[] = "El nombre de usuario no puede exceder los 50 caracteres.";
            if (empty($email)) $errors[] = "El correo electrónico es obligatorio.";
            if (!$email) $errors[] = "El formato del correo electrónico no es válido.";
            if (strlen($_POST['email'] ?? '') > 100) $errors[] = "El email no puede exceder los 100 caracteres.";
            if (empty($rol) || !array_key_exists($rol, $roles_sistema_acciones)) $errors[] = "Debe seleccionar un rol válido.";
            if (empty($password)) $errors[] = "La contraseña es obligatoria.";
            if (strlen($password) < 6) $errors[] = "La contraseña debe tener al menos 6 caracteres.";
            if ($password !== $password_confirm) $errors[] = "Las contraseñas no coinciden.";

            // Verificar unicidad de username
            $stmt_check_user = $pdo->prepare("SELECT id FROM usuarios WHERE username = ?");
            $stmt_check_user->execute([$username]);
            if ($stmt_check_user->fetch()) $errors[] = "El nombre de usuario ya está en uso.";

            // Verificar unicidad de email
            $stmt_check_email = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt_check_email->execute([$email]);
            if ($stmt_check_email->fetch()) $errors[] = "El correo electrónico ya está registrado.";

            if (!empty($errors)) {
                $_SESSION['form_data']['usuario'] = $_POST; // Guardar datos para repoblar
                foreach ($errors as $error) {
                    setGlobalMessage($error, "danger");
                }
                $redirectUrl = APP_URL . '/Modulos/Admin/usuario_form.php';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $sql_insert = "INSERT INTO usuarios (username, nombre_completo, email, password, rol, activo, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, NOW())";
                $stmt_insert = $pdo->prepare($sql_insert);
                $stmt_insert->execute([$username, $nombre_completo, $email, $hashed_password, $rol, $activo]);
                $nuevo_usuario_id = $pdo->lastInsertId();
                logSystemEvent($pdo, 'INFO', 'USER_CREATE', "Usuario '{$username}' (ID: {$nuevo_usuario_id}) creado por Admin ID {$_SESSION['user_id']}.", 'Admin - Usuarios', ['created_user_id' => $nuevo_usuario_id, 'username' => $username, 'rol' => $rol, 'admin_user_id' => $_SESSION['user_id']]);
                setGlobalMessage("Usuario '" . htmlspecialchars($username) . "' creado exitosamente.", "success");
            }
            break;

        case 'actualizar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                setGlobalMessage("Método no permitido.", "danger");
                break;
            }
            $usuario_id = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$usuario_id) {
                setGlobalMessage("ID de usuario no válido.", "danger");
                break;
            }

            $username = sanitizeInput($_POST['username'] ?? '');
            $nombre_completo = sanitizeInput($_POST['nombre_completo'] ?? null);
            $email = filter_var(sanitizeInput($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
            $rol = sanitizeInput($_POST['rol'] ?? '');
            $password = $_POST['password'] ?? '';
            $password_confirm = $_POST['password_confirm'] ?? '';
            $activo = isset($_POST['activo']) ? 1 : 0;

            // Validaciones
            $errors = [];
            if (empty($username)) $errors[] = "El nombre de usuario es obligatorio.";
            if (strlen($username) > 50) $errors[] = "El nombre de usuario no puede exceder los 50 caracteres.";
            if (empty($email)) $errors[] = "El correo electrónico es obligatorio.";
            if (!$email) $errors[] = "El formato del correo electrónico no es válido.";
            if (strlen($_POST['email'] ?? '') > 100) $errors[] = "El email no puede exceder los 100 caracteres.";
            if (empty($rol) || !array_key_exists($rol, $roles_sistema_acciones)) $errors[] = "Debe seleccionar un rol válido.";

            // Verificar unicidad de username (si cambió)
            $stmt_check_user = $pdo->prepare("SELECT id FROM usuarios WHERE username = ? AND id != ?");
            $stmt_check_user->execute([$username, $usuario_id]);
            if ($stmt_check_user->fetch()) $errors[] = "El nombre de usuario ya está en uso por otro usuario.";

            // Verificar unicidad de email (si cambió)
            $stmt_check_email = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
            $stmt_check_email->execute([$email, $usuario_id]);
            if ($stmt_check_email->fetch()) $errors[] = "El correo electrónico ya está registrado por otro usuario.";

            if (!empty($password)) { // Solo validar contraseña si se ingresó una nueva
                if (strlen($password) < 6) $errors[] = "La nueva contraseña debe tener al menos 6 caracteres.";
                if ($password !== $password_confirm) $errors[] = "Las nuevas contraseñas no coinciden.";
            }

            if (!empty($errors)) {
                $_SESSION['form_data']['usuario'] = $_POST; // Guardar datos para repoblar
                foreach ($errors as $error) {
                    setGlobalMessage($error, "danger");
                }
                $redirectUrl = APP_URL . '/Modulos/Admin/usuario_form.php?id=' . $usuario_id;
            } else {
                $sql_update = "UPDATE usuarios SET username = ?, nombre_completo = ?, email = ?, rol = ?, activo = ?, fecha_actualizacion = NOW()";
                $params_update = [$username, $nombre_completo, $email, $rol, $activo];
                if (!empty($password)) {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $sql_update .= ", password = ?";
                    $params_update[] = $hashed_password;
                }
                $sql_update .= " WHERE id = ?";
                $params_update[] = $usuario_id;

                $stmt_update = $pdo->prepare($sql_update);
                $stmt_update->execute($params_update);
                logSystemEvent($pdo, 'INFO', 'USER_UPDATE', "Usuario '{$username}' (ID: {$usuario_id}) actualizado por Admin ID {$_SESSION['user_id']}.", 'Admin - Usuarios', ['updated_user_id' => $usuario_id, 'username' => $username, 'rol' => $rol, 'admin_user_id' => $_SESSION['user_id']]);
                setGlobalMessage("Usuario '" . htmlspecialchars($username) . "' actualizado exitosamente.", "success");
                $redirectUrl = APP_URL . '/Modulos/Admin/usuarios_index.php'; // O de vuelta al form: '/Modulos/Admin/usuario_form.php?id=' . $usuario_id;
            }
            break;

        case 'cambiar_estado':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                setGlobalMessage("Método no permitido.", "danger");
                break;
            }
            $usuario_id_estado = filter_var($_POST['usuario_id'] ?? null, FILTER_VALIDATE_INT);
            $estado_actual = isset($_POST['estado_actual']) ? (int)$_POST['estado_actual'] : null;

            if (!$usuario_id_estado || $estado_actual === null) {
                setGlobalMessage("Datos no válidos para cambiar estado.", "danger");
                break;
            }
            
            // No permitir desactivar al usuario actualmente logueado si es el mismo que se está intentando desactivar.
            if ($usuario_id_estado == $_SESSION['user_id'] && $estado_actual == 1) {
                setGlobalMessage("No puede desactivar su propia cuenta de administrador.", "warning");
                break;
            }

            $nuevo_estado = $estado_actual ? 0 : 1;
            $stmt_estado = $pdo->prepare("UPDATE usuarios SET activo = ?, fecha_actualizacion = NOW() WHERE id = ?");
            $stmt_estado->execute([$nuevo_estado, $usuario_id_estado]);
            $accion_texto = $nuevo_estado ? 'activado' : 'desactivado';
            logSystemEvent($pdo, 'INFO', 'USER_STATUS_CHANGE', "Estado del Usuario ID {$usuario_id_estado} cambiado a '{$accion_texto}' por Admin ID {$_SESSION['user_id']}.", 'Admin - Usuarios', ['target_user_id' => $usuario_id_estado, 'new_status' => $nuevo_estado, 'admin_user_id' => $_SESSION['user_id']]);
            setGlobalMessage("Usuario " . $accion_texto . " exitosamente.", "info");
            break;

        default:
            logSystemEvent($pdo, 'WARNING', 'USER_UNKNOWN_ACTION', "Acción desconocida '{$accion}' intentada en gestión de usuarios por Admin ID {$_SESSION['user_id']}.", 'Admin - Usuarios', ['accion' => $accion, 'post_data' => $_POST, 'admin_user_id' => $_SESSION['user_id']]);
            setGlobalMessage("Acción no reconocida.", "danger");
            break;
    }

    $pdo->commit();

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error PDO en usuario_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD durante acción '{$accion}' en gestión de usuarios. Error: " . $e->getMessage(), 'Admin - Usuarios', ['accion' => $accion, 'post_data' => $_POST, 'admin_user_id' => $_SESSION['user_id']]);
    setGlobalMessage("Error de base de datos al procesar la acción: " . $e->getMessage(), "danger");
    // Si hay error y se estaba creando/editando, guardar datos y redirigir al form
    if (($accion === 'crear' || $accion === 'actualizar') && isset($_POST['username'])){
        $_SESSION['form_data']['usuario'] = $_POST;
        $redirectUrl = APP_URL . '/Modulos/Admin/usuario_form.php';
        if($accion === 'actualizar' && isset($_POST['usuario_id'])) {
            $redirectUrl .= '?id=' . $_POST['usuario_id'];
        }
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error general en usuario_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general durante acción '{$accion}' en gestión de usuarios. Error: " . $e->getMessage(), 'Admin - Usuarios', ['accion' => $accion, 'post_data' => $_POST, 'admin_user_id' => $_SESSION['user_id']]);
    setGlobalMessage("Error general al procesar la acción: " . $e->getMessage(), "danger");
}

header('Location: ' . $redirectUrl);
exit;
?> 