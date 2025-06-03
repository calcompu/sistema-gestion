<?php
require_once __DIR__ . '/../../config.php';
requireLogin(); // Es bueno mantenerlo explícito aunque requirePermission lo llama.
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/pedidos_functions.php'; // Funciones específicas de pedidos

$request_method = $_SERVER['REQUEST_METHOD'];
$accion = $_REQUEST['accion'] ?? ''; // Usar _REQUEST para flexibilidad inicial, luego validar método.

// ---- Verificación de Permisos y CSRF ----
$csrf_token_valid = false;
if ($request_method === 'POST') {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $csrf_token_valid = true;
    }
    if (!$csrf_token_valid) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 
            'Intento de operación POST con token CSRF inválido en pedidos.', 'Pedidos', 
            ['request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $request_method, 'post_data_keys' => array_keys($_POST)]
        );
        // Redirigir de forma segura
        $redirect_csrf_fail = APP_URL . '/Modulos/Pedidos/index.php?status=csrf_error';
        if (isset($_POST['accion'])) {
            $current_action = $_POST['accion'];
            $pedido_id_for_redirect = $_POST['pedido_id'] ?? null;
            if ($current_action === 'actualizar' && $pedido_id_for_redirect) {
                $redirect_csrf_fail = APP_URL . '/Modulos/Pedidos/pedido_form.php?id=' . $pedido_id_for_redirect . '&status=csrf_error';
            } elseif ($current_action === 'crear') {
                $redirect_csrf_fail = APP_URL . '/Modulos/Pedidos/pedido_form.php?status=csrf_error';
            }
            if (in_array($current_action, ['crear', 'actualizar'])) {
                 $_SESSION['form_data'] = $_POST; // Guardar datos del formulario para repopular
            }
        }
        header('Location: ' . $redirect_csrf_fail);
        exit;
    }
}
// El CSRF para GET no se maneja aquí explícitamente ya que las acciones principales son POST.
// Si se implementa una acción GET que modifica datos, requeriría un token en URL y validación aquí.

// Aplicar permisos ANTES de la transacción y el switch principal.
// Todas las acciones que modifican datos ('crear', 'actualizar', 'cancelar') son POST.
if ($request_method === 'POST') {
    switch ($accion) {
        case 'crear':
            requirePermission('pedidos', 'create');
            break;
        case 'actualizar':
            requirePermission('pedidos', 'edit');
            break;
        case 'cancelar':
            // Se requiere permiso para 'cancel' que podría ser un sub-permiso de 'edit' o uno propio.
            // Asumiendo que 'edit' cubre la cancelación o que existe un 'cancel'.
            // Si 'cancel' es un permiso granular, usar requirePermission('pedidos', 'cancel');
            // Por ahora, si puede editar, puede intentar cancelar, la lógica interna lo validará.
            requirePermission('pedidos', 'edit'); // O requirePermission('pedidos', 'cancel');
            break;
        default:
            setGlobalMessage("Acción POST desconocida o no permitida.", "danger");
            logSystemEvent($pdo, 'WARNING', 'PEDIDO_UNKNOWN_POST_ACTION', 
                "Intento de acción POST desconocida: '{$accion}'.", 'Pedidos', 
                ['accion' => $accion, 'method' => $request_method, 'post_data' => $_POST]
            );
            header('Location: ' . APP_URL . '/Modulos/Pedidos/index.php');
            exit;
    }
} else {
    // Si no es POST, y no es una acción GET permitida (que no hay ahora mismo para modificar datos), redirigir.
    setGlobalMessage("Método de solicitud no permitido para esta acción.", "warning");
    logSystemEvent($pdo, 'WARNING', 'PEDIDO_INVALID_METHOD', 
        "Intento de acceso con método {$request_method} para acción '{$accion}'.", 'Pedidos', 
        ['accion' => $accion, 'method' => $request_method]
    );
    header('Location: ' . APP_URL . '/Modulos/Pedidos/index.php');
    exit;
}


// ---- Lógica Principal de Acciones (Solo POST a partir de aquí) ----
$redirectUrl = APP_URL . '/Modulos/Pedidos/index.php';
$datos_pedido = $_POST['pedido'] ?? [];
$items_pedido = $_POST['items'] ?? [];

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear':
            if (empty($datos_pedido['cliente_id']) || empty($datos_pedido['fecha_pedido'])) {
                $_SESSION['form_error'] = "Cliente y fecha son obligatorios.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . APP_URL . '/Modulos/Pedidos/pedido_form.php');
                exit;
            }
            if (empty($items_pedido)) {
                $_SESSION['form_error'] = "Debe añadir al menos un producto al pedido.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . APP_URL . '/Modulos/Pedidos/pedido_form.php');
                exit;
            }

            // Obtener ID del estado "En Preparación"
            $stmt_estado_preparacion = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'en preparación'");
            $stmt_estado_preparacion->execute();
            $estado_en_preparacion_id = $stmt_estado_preparacion->fetchColumn();
            if (!$estado_en_preparacion_id) {
                throw new Exception("Configuración crítica: Estado 'En Preparación' no encontrado en la base de datos.");
            }

            $numero_pedido_generado = generarCodigoPedido($pdo);

            $sql_pedido = "INSERT INTO pedidos (cliente_id, numero_pedido, fecha_pedido, estado_id, subtotal, impuestos, total, observaciones, usuario_id_crea, fecha_creacion) 
                           VALUES (:cliente_id, :numero_pedido, :fecha_pedido, :estado_id, :subtotal, :impuestos, :total, :observaciones, :usuario_id_crea, NOW())";
            $stmt_pedido = $pdo->prepare($sql_pedido);
            $params_pedido = [
                ':cliente_id' => (int)$datos_pedido['cliente_id'],
                ':numero_pedido' => $numero_pedido_generado, 
                ':fecha_pedido' => sanitizeInput($datos_pedido['fecha_pedido']),
                ':estado_id' => $estado_en_preparacion_id, // Estado por defecto "En Preparación"
                ':subtotal' => parseCurrency($datos_pedido['subtotal'] ?? '0'),
                ':impuestos' => parseCurrency($datos_pedido['impuestos'] ?? '0'),
                ':total' => parseCurrency($datos_pedido['total'] ?? '0'),
                ':observaciones' => sanitizeInput($datos_pedido['observaciones'] ?? null),
                ':usuario_id_crea' => $_SESSION['user_id']
            ];
            $stmt_pedido->execute($params_pedido);
            $pedido_id = $pdo->lastInsertId();

            $sql_item = "INSERT INTO pedido_items (pedido_id, producto_id, cantidad_solicitada, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item = $pdo->prepare($sql_item);

            foreach ($items_pedido as $item) {
                $producto_id = (int)$item['producto_id'];
                // cantidad_solicitada y cantidad (a facturar) son la misma al crear
                $cantidad_solicitada = parseCurrency($item['cantidad_solicitada'] ?? $item['cantidad']); // Fallback por si no viene cantidad_solicitada explícitamente
                $cantidad_a_facturar = parseCurrency($item['cantidad']); 
                $precio_unitario = parseCurrency($item['precio_unitario']);
                $subtotal_item = $cantidad_a_facturar * $precio_unitario;

                // No insertar si la cantidad a facturar es 0 o menos al crear, a menos que la solicitada fuera mayor.
                // Decidimos registrar el item incluso si la cantidad a facturar es 0 para mantener la cantidad solicitada original.
                // if ($cantidad_a_facturar <= 0 && $cantidad_solicitada <=0) continue; 

                $stmt_item->execute([$pedido_id, $producto_id, $cantidad_solicitada, $cantidad_a_facturar, $precio_unitario, $subtotal_item]);
            }
            logSystemEvent($pdo, 'INFO', 'ORDER_CREATE', "Pedido #{$numero_pedido_generado} (ID: {$pedido_id}) creado en estado 'En Preparación'. Stock no modificado.", 'Pedidos', ['pedido_id' => $pedido_id, 'numero_pedido' => $numero_pedido_generado]);
            setGlobalMessage("Pedido #".htmlspecialchars($numero_pedido_generado)." creado exitosamente en estado 'En Preparación'. El stock no ha sido modificado.", "success");
            $redirectUrl .= '?status=success_create&id=' . $pedido_id;
            break;

        case 'actualizar':
            $pedido_id = filter_var($_POST['pedido_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$pedido_id) {
                setGlobalMessage("ID de pedido no válido.", "danger");
                header('Location: ' . $redirectUrl . '?status=error_id'); exit;
            }

            if (empty($datos_pedido['cliente_id']) || empty($datos_pedido['numero_pedido']) || empty($datos_pedido['fecha_pedido'])) {
                $_SESSION['form_error'] = "Cliente, número de pedido y fecha son obligatorios.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . APP_URL . '/Modulos/Pedidos/pedido_form.php?id=' . $pedido_id); exit;
            }
             if (empty($items_pedido)) {
                $_SESSION['form_error'] = "Debe añadir al menos un producto al pedido.";
                $_SESSION['form_data'] = $_POST;
                header('Location: ' . APP_URL . '/Modulos/Pedidos/pedido_form.php?id=' . $pedido_id); exit;
            }
            
            $numero_pedido_actual = sanitizeInput($datos_pedido['numero_pedido']);

            // Obtener el ID del estado 'Facturado' y 'Cancelado' para validaciones y lógica.
            $stmt_estado_facturado = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'facturado'");
            $stmt_estado_facturado->execute();
            $estado_facturado_id_db = $stmt_estado_facturado->fetchColumn();
            if (!$estado_facturado_id_db) {
                // Este estado es crucial para la lógica de stock, pero su ausencia no debe detener la actualización
                // si el pedido no se está moviendo a/desde este estado. Podría ser un log de advertencia.
                // Sin embargo, si la lógica depende críticamente de él, podría ser una excepción.
                // Por ahora, se asume que si se usa, debe existir.
                // Para la lógica de actualización de stock (que ahora está en facturación), esto es menos crítico aquí.
            }
            
            $stmt_estado_cancelado = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'cancelado'");
            $stmt_estado_cancelado->execute();
            $estado_cancelado_id_db = $stmt_estado_cancelado->fetchColumn();


            $nuevo_estado_id_form = (int)$datos_pedido['estado_id'];

            // Obtener estado actual del pedido ANTES de cualquier cambio
            $stmt_estado_actual_pedido = $pdo->prepare("SELECT p.estado_id, e.nombre as estado_nombre_actual FROM pedidos p JOIN estados_pedido e ON p.estado_id = e.id WHERE p.id = ?");
            $stmt_estado_actual_pedido->execute([$pedido_id]);
            $info_estado_actual_pedido = $stmt_estado_actual_pedido->fetch(PDO::FETCH_ASSOC);
            $estado_actual_pedido_db = $info_estado_actual_pedido ? $info_estado_actual_pedido['estado_id'] : null;
            $estado_actual_nombre_lower = $info_estado_actual_pedido ? strtolower($info_estado_actual_pedido['estado_nombre_actual']) : null;

            // Obtener IDs de estados 'En Preparación' y 'A Facturar'
            $stmt_estado_en_prep = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'en preparación'");
            $stmt_estado_en_prep->execute();
            $estado_en_preparacion_id_ref = $stmt_estado_en_prep->fetchColumn();

            $stmt_estado_a_fact = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'a facturar'");
            $stmt_estado_a_fact->execute();
            $estado_a_facturar_id_ref = $stmt_estado_a_fact->fetchColumn(); 

            if (!$estado_en_preparacion_id_ref || !$estado_a_facturar_id_ref) {
                throw new Exception("Error de configuración: Estados 'En Preparación' o 'A Facturar' no encontrados en la BD.");
            }

            // Validar si se puede editar el pedido (ya se hace en pedido_form.php, pero doble check)
            if ($estado_actual_nombre_lower === 'facturado' || $estado_actual_nombre_lower === 'cancelado') {
                 setGlobalMessage("El pedido #".htmlspecialchars($numero_pedido_actual)." no puede ser modificado porque está '{$estado_actual_nombre_lower}'.", "warning");
                 logSystemEvent($pdo, 'WARNING', 'ORDER_UPDATE_DENIED_STATE', "Intento de modificar pedido #{$numero_pedido_actual} (ID: {$pedido_id}) en estado '{$estado_actual_nombre_lower}'.", 'Pedidos', ['pedido_id' => $pedido_id, 'estado_actual' => $estado_actual_nombre_lower]);
                 header('Location: ' . APP_URL . '/Modulos/Pedidos/pedido_detalle.php?id=' . $pedido_id . '&status=error_locked'); exit;
            }
            
            // La lógica de revertir stock de items originales se elimina, ya que el stock no se tocó
            // hasta la facturación. Si se cambian items antes de facturar, simplemente se actualizan los items.

            // Procesar items: actualizar existentes, insertar nuevos, eliminar los que ya no vienen (si aplica)
            $ids_items_del_formulario = [];
            if (!empty($items_pedido)) {
                $stmt_update_item = $pdo->prepare("UPDATE pedido_items SET cantidad = ?, precio_unitario = ?, subtotal = ? WHERE id = ? AND pedido_id = ?");
                $stmt_insert_item = $pdo->prepare("INSERT INTO pedido_items (pedido_id, producto_id, cantidad_solicitada, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?)");

                foreach ($items_pedido as $item_form) {
                    $item_id_form = $item_form['id'] ?? null;
                    $producto_id_form = (int)$item_form['producto_id'];
                    $cantidad_solicitada_form = parseCurrency($item_form['cantidad_solicitada'] ?? $item_form['cantidad']); // Leído del form
                    $cantidad_a_facturar_form = parseCurrency($item_form['cantidad']); // Cantidad editable, puede ser 0
                    $precio_unitario_form = parseCurrency($item_form['precio_unitario']);
                    $subtotal_item_form = $cantidad_a_facturar_form * $precio_unitario_form;

                    if ($item_id_form) { // Item existente, actualizarlo
                        $stmt_update_item->execute([
                            $cantidad_a_facturar_form, 
                            $precio_unitario_form, 
                            $subtotal_item_form, 
                            $item_id_form, 
                            $pedido_id
                        ]);
                        $ids_items_del_formulario[] = $item_id_form;
                    } else { // Item nuevo, insertarlo
                        // Solo insertar si el producto_id es válido.
                        // El form debería asegurar que producto_id se envía para items nuevos.
                        if ($producto_id_form > 0) {
                             $stmt_insert_item->execute([
                                $pedido_id, 
                                $producto_id_form, 
                                $cantidad_solicitada_form, // Esta es la cantidad original cuando se añadió al form
                                $cantidad_a_facturar_form, 
                                $precio_unitario_form, 
                                $subtotal_item_form
                            ]);
                            $ids_items_del_formulario[] = $pdo->lastInsertId(); // Guardar ID del nuevo item
                        }
                    }
                }
            }
            
            // Eliminar items que estaban en la BD pero no en el formulario (solo si el pedido estaba 'En Preparación')
            if ($estado_actual_pedido_db == $estado_en_preparacion_id_ref) {
                $stmt_get_all_db_items = $pdo->prepare("SELECT id FROM pedido_items WHERE pedido_id = ?");
                $stmt_get_all_db_items->execute([$pedido_id]);
                $db_item_ids = $stmt_get_all_db_items->fetchAll(PDO::FETCH_COLUMN);

                $items_to_delete = array_diff($db_item_ids, $ids_items_del_formulario);
                if (!empty($items_to_delete)) {
                    $stmt_delete_item = $pdo->prepare("DELETE FROM pedido_items WHERE id = ? AND pedido_id = ?");
                    foreach ($items_to_delete as $item_id_to_delete) {
                        $stmt_delete_item->execute([$item_id_to_delete, $pedido_id]);
                         logSystemEvent($pdo, 'DEBUG', 'ORDER_ITEM_DELETE', "Item ID {$item_id_to_delete} eliminado del pedido #{$numero_pedido_actual} (estaba En Preparación).", 'Pedidos', ['pedido_id' => $pedido_id, 'item_id_deleted' => $item_id_to_delete]);
                    }
                }
            }

            // Determinar el estado final del pedido
            $estado_final_pedido = $nuevo_estado_id_form; // Tomar el estado del formulario como base
            // Si el pedido estaba 'En Preparación' y se modificaron items o el estado del form es 'A Facturar',
            // se fuerza a 'A Facturar' a menos que el form explícitamente diga otra cosa válida.
            if ($estado_actual_pedido_db == $estado_en_preparacion_id_ref && 
                ($nuevo_estado_id_form == $estado_en_preparacion_id_ref || $nuevo_estado_id_form == $estado_a_facturar_id_ref)) {
                 // Si hubo cambios en los items (detectar si $items_pedido fue modificado o si $ids_items_del_formulario no matchea 100% con los originales)
                 // o si el usuario explícitamente seleccionó 'A Facturar'
                 // Forzamos a 'A Facturar' si estaba en 'En Preparación' y se guardan cambios en items.
                 // Una heurística simple: si el estado del formulario es 'A Facturar' o si sigue 'En Preparación' pero se hicieron cambios.
                 // Por ahora, si el estado del form es A Facturar, lo tomamos. Si sigue En Preparación, lo dejamos así.
                 // La lógica del form es que si se edita en "En Prep", y se guardan cambios en items, el estado debería ser "A Facturar".
                 // Si el usuario lo deja en "En Preparación" en el form, lo respetamos, pero se recomienda pasarlo a "A Facturar".
                 // La modificación más robusta es si el estado del form es explícitamente A Facturar, o si estaba En Preparación y hay una diferencia en los items (más complejo de detectar aquí sin query previa de items)
                if ($nuevo_estado_id_form == $estado_en_preparacion_id_ref && !empty($items_pedido)) { // Si se queda en prep y hay items, tal vez deberia ser a facturar?
                    // Podríamos forzar a $estado_a_facturar_id_ref si se detectan cambios en items.
                    // Por ahora, usamos el estado del form $nuevo_estado_id_form.
                } 
            }

            // Actualizar el pedido principal (cabecera)
            $sql_pedido_update = "UPDATE pedidos SET 
                                    cliente_id = :cliente_id, 
                                    fecha_pedido = :fecha_pedido, 
                                    estado_id = :estado_id, 
                                    subtotal = :subtotal, 
                                    impuestos = :impuestos, 
                                    total = :total, 
                                    observaciones = :observaciones, 
                                    usuario_id_actualiza = :usuario_id_actualiza,
                                    fecha_actualizacion = NOW() 
                                WHERE id = :id";
            $stmt_pedido_update = $pdo->prepare($sql_pedido_update);
            $params_pedido_update = [
                ':cliente_id' => (int)$datos_pedido['cliente_id'],
                ':fecha_pedido' => sanitizeInput($datos_pedido['fecha_pedido']),
                ':estado_id' => $estado_final_pedido, // Usar el estado determinado
                ':subtotal' => parseCurrency($datos_pedido['subtotal'] ?? '0'),
                ':impuestos' => parseCurrency($datos_pedido['impuestos'] ?? '0'),
                ':total' => parseCurrency($datos_pedido['total'] ?? '0'),
                ':observaciones' => sanitizeInput($datos_pedido['observaciones'] ?? null),
                ':usuario_id_actualiza' => $_SESSION['user_id'],
                ':id' => $pedido_id
            ];
            $stmt_pedido_update->execute($params_pedido_update);
            
            // Ya no se insertan items aquí porque se manejaron arriba (update/insert)

            // Ya no se llama a completarPedido ni se descuenta stock aquí.
            // El stock se maneja exclusivamente en el proceso de facturación.
            // Obtener nombre del nuevo estado para el log/mensaje
            $stmt_nuevo_estado_info = $pdo->prepare("SELECT nombre FROM estados_pedido WHERE id = ?");
            $stmt_nuevo_estado_info->execute([$estado_final_pedido]);
            $nuevo_estado_nombre = $stmt_nuevo_estado_info->fetchColumn();

            $log_message = "Pedido #{$numero_pedido_actual} (ID: {$pedido_id}) actualizado.";
            $user_message = "Pedido #".htmlspecialchars($numero_pedido_actual)." actualizado exitosamente.";

            if ($nuevo_estado_nombre) {
                $log_message .= " Nuevo estado: '{$nuevo_estado_nombre}'.";
                $user_message .= " Nuevo estado: '".htmlspecialchars($nuevo_estado_nombre)."'.";
            }
            $log_message .= " Stock no modificado en esta operación.";
            $user_message .= " El stock no ha sido modificado.";


            logSystemEvent($pdo, 'INFO', 'ORDER_UPDATE', $log_message, 'Pedidos', ['pedido_id' => $pedido_id, 'numero_pedido' => $numero_pedido_actual, 'nuevo_estado_id' => $nuevo_estado_id_form]);
            setGlobalMessage($user_message, "success");
            
            $redirectUrl .= '?status=success_update&id=' . $pedido_id;
            break;

        case 'cancelar':
            $pedido_id = filter_var($_POST['pedido_id'] ?? '', FILTER_VALIDATE_INT);
            if (!$pedido_id) {
                setGlobalMessage("ID de pedido no válido.", "danger");
                $redirectUrl .= '?status=error_id_cancel'; break;
            }

            // Obtener estado_id y numero_pedido para logs y validaciones
            $stmt_pedido_info = $pdo->prepare("SELECT p.estado_id, p.numero_pedido, e.nombre as estado_nombre FROM pedidos p JOIN estados_pedido e ON p.estado_id = e.id WHERE p.id = ?");
            $stmt_pedido_info->execute([$pedido_id]);
            $pedido_info = $stmt_pedido_info->fetch(PDO::FETCH_ASSOC);
            $numero_pedido_log = $pedido_info ? $pedido_info['numero_pedido'] : $pedido_id;

            if (!$pedido_info) {
                setGlobalMessage("Pedido #".htmlspecialchars($numero_pedido_log)." no encontrado.", "warning");
                logSystemEvent($pdo, 'WARNING', 'ORDER_CANCEL_NOT_FOUND', "Intento de cancelar pedido ID {$pedido_id} no encontrado.", 'Pedidos', ['pedido_id' => $pedido_id]);
                $redirectUrl .= '?status=error_not_found'; break;
            }

            $estado_actual_id = $pedido_info['estado_id'];
            $estado_actual_nombre_lower = strtolower($pedido_info['estado_nombre']);
            
            $stmt_estado_cancelado_info = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'cancelado'");
            $stmt_estado_cancelado_info->execute();
            $estado_cancelado_id_ref = $stmt_estado_cancelado_info->fetchColumn();
            if (!$estado_cancelado_id_ref) {
                 throw new Exception("Configuración crítica: Estado 'Cancelado' no encontrado en la base de datos.");
            }

            // No se puede cancelar si ya está cancelado
            if ($estado_actual_id == $estado_cancelado_id_ref) {
                setGlobalMessage("El pedido #".htmlspecialchars($numero_pedido_log)." ya se encuentra cancelado.", "info");
                logSystemEvent($pdo, 'INFO', 'ORDER_CANCEL_ALREADY_CANCELLED', "Pedido #{$numero_pedido_log} (ID: {$pedido_id}) ya estaba cancelado.", 'Pedidos', ['pedido_id' => $pedido_id]);
                $redirectUrl .= '?status=info_already_cancelled'; break;
            }

            // No se puede cancelar si está FACTURADO desde aquí, se debe ANULAR LA FACTURA primero.
            // Esta restricción se ha movido/reforzado en el módulo de facturación también.
            if ($estado_actual_nombre_lower === 'facturado') {
                setGlobalMessage("El pedido #".htmlspecialchars($numero_pedido_log)." está Facturado. Debe anular la factura asociada primero si desea cancelar el pedido.", "warning");
                logSystemEvent($pdo, 'WARNING', 'ORDER_CANCEL_FAILED_IS_BILLED', "Intento de cancelar pedido #{$numero_pedido_actual} (ID: {$pedido_id}) que está facturado.", 'Pedidos', ['pedido_id' => $pedido_id]);
                $redirectUrl = APP_URL . '/Modulos/Pedidos/pedido_detalle.php?id=' . $pedido_id . '&status=error_is_billed';
                // No hacemos break aquí para que el commit no ocurra si la redirección es directa. Sino, la transacción se cierra.
                // Es mejor hacer el header y exit dentro del bloque try, o manejar la URL de redirección y hacerla al final.
                // Para este caso, dado que no hay más lógica después en este 'case', podemos redirigir y salir.
                $pdo->rollBack(); // Revertir la transacción ya que no se completará la acción.
                header('Location: ' . $redirectUrl); 
                exit;
            }


            $stock_modificado_msg = " El stock no fue modificado ya que el pedido no había sido facturado.";
            $reponer_stock = false;

            if ($estado_actual_id == $estado_facturado_id_db) {
                // Solo reponer stock si el pedido estaba previamente 'Facturado'
                // Esta lógica asume que la anulación de la factura asociada también ocurre o se maneja por separado.
                // Aquí solo nos enfocamos en el pedido y su stock.
                $stmt_items_pedido = $pdo->prepare("SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ?");
                $stmt_items_pedido->execute([$pedido_id]);
                $items_a_revertir = $stmt_items_pedido->fetchAll(PDO::FETCH_ASSOC);

                $stmt_revertir_stock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                foreach ($items_a_revertir as $item_revertir) {
                    $stmt_revertir_stock->execute([$item_revertir['cantidad'], $item_revertir['producto_id']]);
                }
                logSystemEvent($pdo, 'INFO', 'ORDER_CANCEL_STOCK_RESTORED', "Stock restaurado para pedido cancelado #{$numero_pedido_log} (ID: {$pedido_id}) porque estaba facturado.", 'Pedidos', ['pedido_id' => $pedido_id]);
                $stock_modificado_msg = " Stock restaurado porque el pedido estaba facturado.";
                $reponer_stock = true; // Indicador para el log general de cancelación
            } else {
                logSystemEvent($pdo, 'INFO', 'ORDER_CANCEL_NO_STOCK_CHANGE', "Pedido #{$numero_pedido_log} (ID: {$pedido_id}) cancelado. No se requiere cambio de stock (no estaba facturado).", 'Pedidos', ['pedido_id' => $pedido_id]);
            }
            
            $sql_cancelar = "UPDATE pedidos SET estado_id = :estado_id, usuario_id_actualiza = :usuario_id_actualiza, fecha_actualizacion = NOW() WHERE id = :id";
            $stmt_cancelar = $pdo->prepare($sql_cancelar);
            $stmt_cancelar->execute([
                ':estado_id' => $estado_cancelado_id_ref, 
                ':usuario_id_actualiza' => $_SESSION['user_id'], 
                ':id' => $pedido_id
            ]);

            if ($stmt_cancelar->rowCount() > 0) {
                setGlobalMessage("Pedido #".htmlspecialchars($numero_pedido_log)." cancelado." . $stock_modificado_msg, "success");
                logSystemEvent($pdo, 'INFO', 'ORDER_CANCEL', "Pedido #{$numero_pedido_log} (ID: {$pedido_id}) cancelado.", 'Pedidos', ['pedido_id' => $pedido_id, 'stock_message' => trim($stock_modificado_msg), 'stock_restored' => $reponer_stock]);
                $redirectUrl .= '?status=success_cancel';
            } else {
                setGlobalMessage("Error al actualizar estado del pedido #".htmlspecialchars($numero_pedido_log)." a cancelado.", "warning");
                logSystemEvent($pdo, 'WARNING', 'ORDER_CANCEL_STATE_UPDATE_FAILED', "No se pudo actualizar estado a cancelado para pedido #{$numero_pedido_log} (ID: {$pedido_id}).", 'Pedidos', ['pedido_id' => $pedido_id]);
                $redirectUrl .= '?status=error_cancel_failed';
            }
            break;

        default:
            setGlobalMessage("Acción desconocida: " . htmlspecialchars($accion), "danger");
            logSystemEvent($pdo, 'ERROR', 'ORDER_UNKNOWN_ACTION_MAIN_SWITCH', 
                "Acción desconocida '{$accion}' en switch principal de pedidos.", 'Pedidos', 
                ['accion' => $accion, 'post_data' => $_POST]
            );
            $redirectUrl .= '?status=error_unknown_action';
            break;
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("PDO Error en pedido_acciones.php ({$accion}): " . $e->getMessage());
    setGlobalMessage("Error de base de datos: " . htmlspecialchars($e->getMessage()), "danger");
    logSystemEvent($pdo, 'ERROR', 'PEDIDO_PDO_EXCEPTION', "Acción: {$accion}. Error: " . $e->getMessage(), 'Pedidos', ['accion' => $accion, 'exception_trace' => $e->getTraceAsString()]);
    
    if ($accion === 'crear' || $accion === 'actualizar') {
        $_SESSION['form_data'] = $_POST; // Guardar datos del formulario para repopular
        $form_redirect = APP_URL . '/Modulos/Pedidos/pedido_form.php';
        if ($accion === 'actualizar' && isset($_POST['pedido_id'])) {
            $form_redirect .= '?id=' . $_POST['pedido_id'];
        }
        header('Location: ' . $form_redirect . '&status=db_error');
        exit;
    }
    $redirectUrl .= '?status=db_error';

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("General Error en pedido_acciones.php ({$accion}): " . $e->getMessage());
    setGlobalMessage("Error general: " . htmlspecialchars($e->getMessage()), "danger");
    logSystemEvent($pdo, 'ERROR', 'PEDIDO_GENERAL_EXCEPTION', "Acción: {$accion}. Error: " . $e->getMessage(), 'Pedidos', ['accion' => $accion, 'exception_trace' => $e->getTraceAsString()]);

    if ($accion === 'crear' || $accion === 'actualizar') {
        $_SESSION['form_data'] = $_POST; // Guardar datos del formulario para repopular
        $form_redirect = APP_URL . '/Modulos/Pedidos/pedido_form.php';
        if ($accion === 'actualizar' && isset($_POST['pedido_id'])) {
            $form_redirect .= '?id=' . $_POST['pedido_id'];
        }
        header('Location: ' . $form_redirect . '&status=error');
        exit;
    }
    $redirectUrl .= '?status=error';
}

// Limpiar datos de formulario de la sesión si todo fue bien y no es una redirección por error de formulario.
if (!isset($_SESSION['form_error']) && !str_contains($redirectUrl, 'status=db_error') && !str_contains($redirectUrl, 'status=error') && !str_contains($redirectUrl, 'status=error_locked')) {
    unset($_SESSION['form_data']);
}

header('Location: ' . $redirectUrl);
exit;
?> 
?> 
