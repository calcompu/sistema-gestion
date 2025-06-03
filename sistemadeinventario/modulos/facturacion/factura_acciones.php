<?php
require_once __DIR__ . '/../../config.php';
requireLogin();
require_once __DIR__ . '/../../includes/functions.php';

$request_method = $_SERVER['REQUEST_METHOD'];
$accion = $_REQUEST['accion'] ?? '';

// ---- Verificación de Permisos y CSRF ----
if ($request_method === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        setGlobalMessage("Error de validación CSRF. Inténtelo de nuevo.", "danger");
        logSystemEvent($pdo, 'SECURITY', 'CSRF_VALIDATION_FAILED', 
            'Intento de operación POST con token CSRF inválido en facturación.', 'Facturacion', 
            ['request_uri' => $_SERVER['REQUEST_URI'] ?? '', 'method' => $request_method, 'post_data_keys' => array_keys($_POST)]
        );
        $redirect_csrf_fail = APP_URL . '/modulos/Facturacion/index.php?status=csrf_error';
        if (isset($_POST['accion']) && $_POST['accion'] === 'crear_desde_pedido' && isset($_POST['pedido_id'])) {
            $_SESSION['form_data']['factura'] = $_POST['factura'] ?? [];
            $redirect_csrf_fail = APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $_POST['pedido_id'] . '&status=csrf_error';
        }
        header('Location: ' . $redirect_csrf_fail);
        exit;
    }

    switch ($accion) {
        case 'crear_desde_pedido':
            requirePermission('facturacion', 'create_from_order');
            break;
        case 'anular':
            requirePermission('facturacion', 'void');
            break;
        default:
            setGlobalMessage("Acción POST desconocida o no permitida en facturación.", "danger");
            logSystemEvent($pdo, 'WARNING', 'INVOICE_UNKNOWN_POST_ACTION', 
                "Intento de acción POST desconocida en facturación: '{$$accion}'.", 'Facturacion', 
                ['accion' => $accion, 'method' => $request_method, 'post_data' => $_POST]
            );
            header('Location: ' . APP_URL . '/modulos/Facturacion/index.php');
            exit;
    }
} else {
    setGlobalMessage("Método de solicitud no permitido para acciones de facturación.", "warning");
    logSystemEvent($pdo, 'WARNING', 'INVOICE_INVALID_METHOD', 
        "Intento de acceso con método {$$request_method} para acción de facturación '{$$accion}'.", 'Facturacion', 
        ['accion' => $accion, 'method' => $request_method]
    );
    header('Location: ' . APP_URL . '/modulos/Facturacion/index.php');
    exit;
}

$redirectUrl = APP_URL . '/modulos/Facturacion/index.php';

// Obtener IDs de estados relevantes
$estado_facturado_id = $pdo->query("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'facturado'")->fetchColumn();
$estado_a_facturar_id = $pdo->query("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'a facturar'")->fetchColumn();

if (!$estado_facturado_id || !$estado_a_facturar_id) {
    setGlobalMessage("Error crítico: No se pudieron encontrar los estados 'facturado' o 'a facturar' en la BD.", "danger");
    logSystemEvent($pdo, 'ERROR', 'DB_CONFIG_MISSING_STATES', 'Estados de pedido facturado/a facturar no encontrados.', 'Facturacion');
    header('Location: ' . $redirectUrl); // Redirigir a un lugar seguro
    exit;
}

try {
    $pdo->beginTransaction();

    switch ($accion) {
        case 'crear_desde_pedido':
            // POST ya verificado arriba
            $pedido_id = filter_var($_POST['pedido_id'] ?? null, FILTER_VALIDATE_INT);
            $cliente_id = filter_var($_POST['cliente_id'] ?? null, FILTER_VALIDATE_INT);
            $datos_factura = $_POST['factura'] ?? [];
            $items_factura_input = $_POST['items'] ?? [];

            if (!$pedido_id || !$cliente_id) {
                setGlobalMessage("ID de pedido o cliente no válido.", "danger");
                header('Location: ' . APP_URL . '/modulos/Pedidos/index.php?estado_id=' . $estado_a_facturar_id); // Link to completed orders
                exit;
            }
            if (empty($datos_factura['numero_factura']) || empty($datos_factura['fecha_emision']) || empty($datos_factura['estado'])) {
                $_SESSION['form_data']['factura'] = $datos_factura;
                setGlobalMessage("Número de factura, fecha de emisión y estado son obligatorios.", "danger");
                header('Location: ' . APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $pedido_id);
                exit;
            }
            if (empty($items_factura_input)) {
                 setGlobalMessage("No se recibieron items para facturar.", "danger");
                 header('Location: ' . APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $pedido_id);
                 exit;
            }

            $stmt_check_f = $pdo->prepare("SELECT id FROM facturas WHERE pedido_id = ?");
            $stmt_check_f->execute([$pedido_id]);
            if ($stmt_check_f->fetch()) {
                setGlobalMessage("Error: Este pedido ya ha sido facturado.", "warning");
                $redirectUrl = APP_URL . '/modulos/Pedidos/pedido_detalle.php?id=' . $pedido_id;
                break; 
            }
            
            $stmt_check_num_f = $pdo->prepare("SELECT id FROM facturas WHERE numero_factura = ?");
            $stmt_check_num_f->execute([sanitizeInput($datos_factura['numero_factura'])]);
            if ($stmt_check_num_f->fetch()) {
                $_SESSION['form_data']['factura'] = $datos_factura;
                setGlobalMessage("El número de factura ingresado ya existe.", "warning");
                header('Location: ' . APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $pedido_id);
                exit;
            }

            $sql_factura = "INSERT INTO facturas (pedido_id, cliente_id, numero_factura, fecha_emision, fecha_vencimiento, estado, subtotal, impuestos, total, observaciones, usuario_id_crea, fecha_creacion) 
                            VALUES (:pedido_id, :cliente_id, :numero_factura, :fecha_emision, :fecha_vencimiento, :estado, :subtotal, :impuestos, :total, :observaciones, :usuario_id, NOW())";
            $stmt_factura = $pdo->prepare($sql_factura);
            $params_factura = [
                ':pedido_id' => $pedido_id,
                ':cliente_id' => $cliente_id,
                ':numero_factura' => sanitizeInput($datos_factura['numero_factura']),
                ':fecha_emision' => sanitizeInput($datos_factura['fecha_emision']),
                ':fecha_vencimiento' => !empty($datos_factura['fecha_vencimiento']) ? sanitizeInput($datos_factura['fecha_vencimiento']) : null,
                ':estado' => sanitizeInput($datos_factura['estado']), // Estado de la FACTURA (pendiente_pago, pagada, etc)
                ':subtotal' => parseCurrency($datos_factura['subtotal'] ?? '0'),
                ':impuestos' => parseCurrency($datos_factura['impuestos'] ?? '0'),
                ':total' => parseCurrency($datos_factura['total'] ?? '0'),
                ':observaciones' => !empty($datos_factura['observaciones']) ? sanitizeInput($datos_factura['observaciones']) : null,
                ':usuario_id' => $_SESSION['user_id']
            ];
            $stmt_factura->execute($params_factura);
            $factura_id = $pdo->lastInsertId();

            $sql_item_factura = "INSERT INTO factura_items (factura_id, producto_id, descripcion_producto, cantidad, precio_unitario, subtotal_item) 
                                 VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_item_factura = $pdo->prepare($sql_item_factura);
            
            // --- INICIO: Descuento de Stock ---
            $stmt_update_stock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt_check_stock = $pdo->prepare("SELECT nombre, stock FROM productos WHERE id = ?");

            foreach ($items_factura_input as $item_input) {
                $producto_id_stock = (int)$item_input['producto_id'];
                $cantidad_stock = parseCurrency($item_input['cantidad']);

                if ($cantidad_stock <= 0) continue; // No descontar si la cantidad es cero o negativa

                // Verificar stock antes de intentar descontar
                $stmt_check_stock->execute([$producto_id_stock]);
                $producto_info_stock = $stmt_check_stock->fetch(PDO::FETCH_ASSOC);

                if (!$producto_info_stock) {
                    throw new Exception("Producto ID {$producto_id_stock} no encontrado para verificar stock.");
                }

                if ($producto_info_stock['stock'] < $cantidad_stock) {
                    throw new Exception("Stock insuficiente para el producto '{$producto_info_stock['nombre']}' (ID: {$producto_id_stock}). Stock disponible: {$producto_info_stock['stock']}, Cantidad solicitada: {$cantidad_stock}. Factura no generada.");
                }

                // Intentar descontar stock
                $stmt_update_stock->execute([$cantidad_stock, $producto_id_stock, $cantidad_stock]);
                if ($stmt_update_stock->rowCount() == 0) {
                    // Este caso podría ocurrir si hubo una condición de carrera y el stock cambió entre el check y el update.
                    // O si el producto simplemente no existe (aunque el check anterior debería haberlo capturado).
                    throw new Exception("No se pudo descontar el stock para el producto '{$producto_info_stock['nombre']}' (ID: {$producto_id_stock}). Verifique el stock y vuelva a intentarlo. Factura no generada.");
                }
                logSystemEvent($pdo, 'INFO', 'STOCK_DECREASED_INVOICE', "Stock descontado: {$cantidad_stock} unidades del Producto ID {$producto_id_stock} por Factura ID {$factura_id}.", 'Facturacion', 
                    ['factura_id' => $factura_id, 'producto_id' => $producto_id_stock, 'cantidad_descontada' => $cantidad_stock, 'numero_factura' => $params_factura[':numero_factura']]
                );

                // Insertar el item de la factura DESPUÉS de confirmar que el stock se pudo descontar
                $stmt_item_factura->execute([
                    $factura_id,
                    $producto_id_stock,
                    sanitizeInput($item_input['descripcion_producto']),
                    $cantidad_stock,
                    parseCurrency($item_input['precio_unitario']),
                    parseCurrency($item_input['subtotal_item'])
                ]);
            }
            // --- FIN: Descuento de Stock ---

            // Actualizar estado_id del pedido a 'Facturado' y registrar auditoría
            $sql_update_pedido = "UPDATE pedidos SET estado_id = ?, factura_id = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?"; 
            $stmt_update_pedido = $pdo->prepare($sql_update_pedido);
            $stmt_update_pedido->execute([$estado_facturado_id, $factura_id, $_SESSION['user_id'], $pedido_id]); 
            
            logSystemEvent($pdo, 'INFO', 'INVOICE_CREATE_FROM_ORDER', "Factura #{$params_factura[':numero_factura']} (ID: {$factura_id}) creada desde Pedido ID {$pedido_id}. Pedido actualizado a estado Facturado. Stock descontado.", 'Facturacion', 
                ['factura_id' => $factura_id, 'pedido_id' => $pedido_id, 'numero_factura' => $params_factura[':numero_factura'], 'nuevo_estado_pedido_id' => $estado_facturado_id]);
            setGlobalMessage("Factura " . htmlspecialchars($datos_factura['numero_factura']) . " generada exitosamente. El pedido ha sido marcado como Facturado y el stock ha sido descontado.", "success");
            $redirectUrl = APP_URL . '/modulos/Facturacion/factura_detalle.php?id=' . $factura_id;
            break;

        case 'anular':
            // POST ya verificado arriba
            $factura_id_anular = filter_var($_POST['factura_id'] ?? null, FILTER_VALIDATE_INT);

            if (!$factura_id_anular) {
                setGlobalMessage("ID de factura no válido para anular.", "danger");
                break;
            }

            $stmt_check_estado_factura = $pdo->prepare("SELECT estado, pedido_id, numero_factura FROM facturas WHERE id = ?");
            $stmt_check_estado_factura->execute([$factura_id_anular]);
            $factura_actual = $stmt_check_estado_factura->fetch(PDO::FETCH_ASSOC);

            if (!$factura_actual) {
                setGlobalMessage("Factura no encontrada.", "danger");
                break;
            }
            if ($factura_actual['estado'] === 'anulada') {
                setGlobalMessage("Esta factura ya ha sido anulada.", "warning");
                $redirectUrl = APP_URL . '/modulos/Facturacion/factura_detalle.php?id=' . $factura_id_anular;
                break;
            }
            // Considerar lógica adicional: if ($factura_actual['estado'] === 'pagada') { ... no permitir anulación directa ... }

            $stock_repuesto_log_msg = '';
            $stock_repuesto_user_msg = '';

            if ($factura_actual['estado'] !== 'anulada') { // Solo reponer stock si no estaba ya anulada
                // --- INICIO: Reposición de Stock ---
                $stmt_items_factura_anular = $pdo->prepare("SELECT producto_id, cantidad FROM factura_items WHERE factura_id = ?");
                $stmt_items_factura_anular->execute([$factura_id_anular]);
                $items_para_reponer = $stmt_items_factura_anular->fetchAll(PDO::FETCH_ASSOC);

                $stmt_reponer_stock = $pdo->prepare("UPDATE productos SET stock = stock + ? WHERE id = ?");
                $items_repuestos_count = 0;
                foreach ($items_para_reponer as $item_reponer) {
                    $producto_id_reponer = (int)$item_reponer['producto_id'];
                    $cantidad_reponer = parseCurrency($item_reponer['cantidad']);

                    if ($cantidad_reponer <= 0) continue;

                    $stmt_reponer_stock->execute([$cantidad_reponer, $producto_id_reponer]);
                    logSystemEvent($pdo, 'INFO', 'STOCK_INCREASED_INVOICE_VOID', "Stock repuesto: {$cantidad_reponer} unidades del Producto ID {$producto_id_reponer} por anulación de Factura ID {$factura_id_anular}.", 'Facturacion', 
                        ['factura_id' => $factura_id_anular, 'producto_id' => $producto_id_reponer, 'cantidad_repuesta' => $cantidad_reponer, 'numero_factura' => $factura_actual['numero_factura']]
                    );
                    $items_repuestos_count++;
                }
                if ($items_repuestos_count > 0) {
                    $stock_repuesto_log_msg = "Stock de {$items_repuestos_count} tipo(s) de producto(s) repuesto.";
                    $stock_repuesto_user_msg = "El stock de los productos ha sido repuesto.";
                } else {
                    $stock_repuesto_log_msg = "No se repuso stock (no habían items en la factura o cantidades eran cero).";
                    $stock_repuesto_user_msg = "No fue necesario reponer stock de productos.";
                }
                // --- FIN: Reposición de Stock ---
            } else {
                 $stock_repuesto_log_msg = "Stock no repuesto porque la factura ya estaba anulada.";
                 $stock_repuesto_user_msg = "El stock no fue modificado (factura ya estaba anulada).";
            }

            $stmt_anular = $pdo->prepare("UPDATE facturas SET estado = 'anulada', usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?");
            $stmt_anular->execute([$_SESSION['user_id'], $factura_id_anular]);

            // Opcional: Revertir estado del pedido y quitar factura_id
            $log_msg_revert = "No se revirtió estado del pedido."; // Mensaje por defecto
            $user_msg_revert_pedido = "El estado del pedido no fue modificado.";

            if ($factura_actual['pedido_id'] && $estado_a_facturar_id) { // Asegurarse que tenemos el ID del estado 'A Facturar'
                // Solo revertir si el pedido estaba previamente facturado (o el estado que corresponda)
                $stmt_revert_pedido = $pdo->prepare("UPDATE pedidos SET estado_id = ?, factura_id = NULL, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ? AND estado_id = ?");
                $stmt_revert_pedido->execute([$estado_a_facturar_id, $_SESSION['user_id'], $factura_actual['pedido_id'], $estado_facturado_id]);
                
                if ($stmt_revert_pedido->rowCount() > 0) {
                    $log_msg_revert = "Pedido ID {$factura_actual['pedido_id']} revertido a estado 'A Facturar'.";
                    $user_msg_revert_pedido = "El pedido asociado ha sido revertido a 'A Facturar'.";
                } else {
                    $log_msg_revert = "Pedido ID {$factura_actual['pedido_id']} no se revirtió (quizás no estaba en estado 'Facturado' o ya se había revertido).";
                    // $user_msg_revert_pedido se mantiene como "El estado del pedido no fue modificado."
                }
            } else {
                $log_msg_revert = "No se intentó revertir estado del pedido (pedido_id no asociado a factura, o estado 'A Facturar' no encontrado en BD).";
            }
            
            logSystemEvent($pdo, 'INFO', 'INVOICE_VOID', "Factura #{$factura_actual['numero_factura']} (ID {$factura_id_anular}) anulada. " . $log_msg_revert . " " . $stock_repuesto_log_msg, 'Facturacion', 
                ['factura_id' => $factura_id_anular, 'pedido_id' => ($factura_actual['pedido_id'] ?? null), 'estado_anterior_factura' => ($factura_actual['estado'] ?? 'desconocido')]);
            setGlobalMessage("Factura " . htmlspecialchars($factura_actual['numero_factura']) . " anulada exitosamente. " . $user_msg_revert_pedido . " " . $stock_repuesto_user_msg, "info");
            $redirectUrl = APP_URL . '/modulos/Facturacion/factura_detalle.php?id=' . $factura_id_anular;
            break;

        default:
            logSystemEvent($pdo, 'WARNING', 'INVOICE_UNKNOWN_ACTION', "Acción desconocida '{$$accion}' intentada en facturación.", 'Facturacion', ['accion' => $accion, 'request_data' => $_REQUEST]);
            setGlobalMessage("Acción no reconocida en facturación.", "danger");
            break;
    }

    $pdo->commit();

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error PDO en factura_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_DB', "Error de BD durante acción '{$$accion}' en facturación. Error: " . $e->getMessage(), 'Facturacion', 
        ['accion' => $accion, 'post_data' => $_POST, 'factura_id' => ($_POST['factura_id'] ?? ($_POST['factura_id_anular'] ?? null)), 'pedido_id' => ($_POST['pedido_id'] ?? null)]);
    setGlobalMessage("Error de base de datos al procesar la acción de factura: " . $e->getMessage(), "danger");
    if ($accion === 'crear_desde_pedido' && isset($pedido_id)) {
        $_SESSION['form_data']['factura'] = $_POST['factura'] ?? [];
        header('Location: ' . APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $pedido_id);
        exit;
    }
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error general en factura_acciones.php: " . $e->getMessage());
    logSystemEvent($pdo, 'ERROR', strtoupper($accion) . '_EXCEPTION_GENERAL', "Error general durante acción '{$$accion}' en facturación. Error: " . $e->getMessage(), 'Facturacion', 
        ['accion' => $accion, 'post_data' => $_POST, 'factura_id' => ($_POST['factura_id'] ?? ($_POST['factura_id_anular'] ?? null)), 'pedido_id' => ($_POST['pedido_id'] ?? null)]);
    setGlobalMessage("Error general al procesar la acción de factura: " . $e->getMessage(), "danger");
    if ($accion === 'crear_desde_pedido' && isset($pedido_id)) {
        $_SESSION['form_data']['factura'] = $_POST['factura'] ?? [];
        header('Location: ' . APP_URL . '/modulos/Facturacion/factura_form.php?pedido_id=' . $pedido_id);
        exit;
    }
}

header('Location: ' . $redirectUrl);
exit;
?> 