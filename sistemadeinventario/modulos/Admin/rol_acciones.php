<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    setGlobalMessage('Acceso no permitido.', 'danger');
    header('Location: roles_index.php');
    exit;
}

if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    setGlobalMessage("Error de validación CSRF.", "danger");
    logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 'rol_acciones.php');
    header('Location: roles_index.php');
    exit;
}

requirePermission('admin_roles', 'manage');

$accion = $_POST['accion'] ?? '';
$permissions_file_path = __DIR__ . '/../../permissions.config.php';

try {
    // NOTA: Las acciones de rol NO usarán transacciones de BD aquí porque 
    // actualmente los roles y permisos se manejan en config.php.
    // Si se migrara a BD, aquí iría $pdo->beginTransaction();

    if ($accion === 'actualizar_permisos_rol') {
        $rolKey = $_POST['rol_key'] ?? null;
        $nuevosPermisosEnviados = $_POST['permisos'] ?? [];

        if (!$rolKey || !isset(USER_ROLES[$rolKey])) {
            setGlobalMessage('Rol no válido o no especificado para actualizar.', 'danger');
            logSystemEvent($pdo, 'WARNING', 'ROLE_PERMISSION_UPDATE_INVALID_ROLE', 'Rol: ' . $rolKey);
            header('Location: roles_index.php');
            exit;
        }

        // Cargar los permisos actuales (desde el archivo si existe, sino desde la config global)
        $current_all_permissions = $permissions; // Inicia con los permisos cargados por config.php
        // Si el archivo permissions.config.php existe, su contenido tiene precedencia para $current_all_permissions
        // Sin embargo, config.php ya debería haberlo cargado si existe. 
        // La variable $permissions global ya reflejaría el estado más actual.

        $permisosParaEsteRol = [];
        if (isset($nuevosPermisosEnviados['all_modules']) && $nuevosPermisosEnviados['all_modules'] === 'true') {
            $permisosParaEsteRol['all_modules'] = true;
        } else {
            $listaModulosPrincipales = [
                'inventario_productos' => ['manage' => true, 'export' => true],
                'inventario_categorias' => ['manage' => true],
                'inventario_lugares' => ['manage' => true],
                'pedidos' => ['view_list' => true, 'view_detail' => true, 'create' => true, 'edit' => true, 'cancel' => true],
                'clientes' => ['view_list' => true, 'create' => true, 'edit' => true, 'delete' => true, 'toggle_status' => true],
                'facturacion' => ['view_list' => true, 'view_detail' => true, 'create_from_order' => true, 'void' => true],
                'admin_usuarios' => ['manage' => true],
                'admin_logs' => ['view' => true],
                'admin_roles' => ['manage' => true],
                'admin_backup' => ['manage' => true],
                'admin_dashboard' => ['view' => true],
                'menu_principal' => ['view' => true]
            ];

            foreach ($listaModulosPrincipales as $moduloKey => $accionesDefinidas) {
                if (isset($nuevosPermisosEnviados[$moduloKey]) && is_array($nuevosPermisosEnviados[$moduloKey])) {
                    foreach ($accionesDefinidas as $accionKey => $descripcion) {
                        if (isset($nuevosPermisosEnviados[$moduloKey][$accionKey]) && $nuevosPermisosEnviados[$moduloKey][$accionKey] === 'true') {
                            if (!isset($permisosParaEsteRol[$moduloKey])) {
                                $permisosParaEsteRol[$moduloKey] = [];
                            }
                            $permisosParaEsteRol[$moduloKey][$accionKey] = true;
                        } else {
                            // Asegurar que si no está marcado, el permiso sea false o no exista
                            // $permisosParaEsteRol[$moduloKey][$accionKey] = false; // Opcional: explícitamente false
                        }
                    }
                }
            }
        }
        
        // Actualizar la estructura global de permisos SÓLO para el rol editado
        $current_all_permissions[$rolKey] = $permisosParaEsteRol;

        // Guardar la estructura $current_all_permissions completa en permissions.config.php
        $exported_permissions = var_export($current_all_permissions, true);
        $file_content = "<?php\n// Este archivo es generado automáticamente. No editar manualmente.\n\n\$permissions = {$exported_permissions};\n?>";
        
        if (file_put_contents($permissions_file_path, $file_content) !== false) {
            setGlobalMessage('Permisos para el rol ' . htmlspecialchars(USER_ROLES[$rolKey]) . ' (' . htmlspecialchars($rolKey) . ') actualizados y guardados correctamente.', 'success');
            logSystemEvent($pdo, 'INFO', 'ROLE_PERMISSIONS_SAVED', 'Permisos guardados para rol: ' . $rolKey . '. Contenido: ' . json_encode($permisosParaEsteRol));
        } else {
            setGlobalMessage('Error al guardar el archivo de configuración de permisos. Verifique los permisos del directorio.', 'danger');
            logSystemEvent($pdo, 'ERROR', 'ROLE_PERMISSIONS_SAVE_FAILED', 'No se pudo escribir en: ' . $permissions_file_path);
        }

    } elseif ($accion === 'crear_rol') {
        setGlobalMessage('Creación de rol (funcionalidad pendiente). Por ahora, los roles se definen en config.php.', 'warning');
        logSystemEvent($pdo, 'INFO', 'ROLE_CREATE_ATTEMPT', 'Intento de crear rol (pendiente)');

    } elseif ($accion === 'eliminar_rol') {
        $rol_id_key = $_POST['rol_id_key'] ?? null;
        setGlobalMessage('Eliminación de rol (funcionalidad pendiente). Por ahora, los roles se definen en config.php.', 'warning');
        logSystemEvent($pdo, 'INFO', 'ROLE_DELETE_ATTEMPT', 'Intento de eliminar rol ID: ' . $rol_id_key . ' (pendiente)');

    } else {
        setGlobalMessage('Acción desconocida.', 'danger');
        logSystemEvent($pdo, 'WARNING', 'ROLE_UNKNOWN_ACTION', 'Acción: ' . $accion);
    }

    header('Location: roles_index.php');
    exit;

} catch (Exception $e) {
    setGlobalMessage('Error al procesar la solicitud de rol: ' . $e->getMessage(), 'danger');
    logSystemEvent($pdo, 'ERROR', 'ROLE_ACTION_EXCEPTION', $e->getMessage());
    header('Location: roles_index.php');
    exit;
}
?> 