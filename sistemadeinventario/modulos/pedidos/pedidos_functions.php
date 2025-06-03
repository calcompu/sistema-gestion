<?php
// Generar código de pedido automático
function generarCodigoPedido($pdo) {
    $year = date('Y');
    $prefijo = 'PED-' . $year . '-';
    // Usar numero_pedido en lugar de codigo_pedido
    $stmt = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(numero_pedido, LENGTH(?) + 1) AS UNSIGNED)) as ultimo 
                         FROM pedidos 
                         WHERE numero_pedido LIKE ?");
    $stmt->execute([$prefijo, $prefijo . '%']);
    $ultimo = $stmt->fetchColumn() ?? 0;
    return $prefijo . str_pad($ultimo + 1, 6, '0', STR_PAD_LEFT);
}

// Generar comprobante de pedido (HTML)
function generarComprobantePedido($pedido_id) {
    global $pdo;

    $pedido_id_int = filter_var($pedido_id, FILTER_VALIDATE_INT);
    if ($pedido_id_int === false) {
        error_log("Error en generarComprobantePedido: pedido_id no es un entero válido: " . $pedido_id);
        return "Error: ID de pedido no válido."; 
    }
    
    // Obtener datos del pedido, incluyendo nombre del estado y cliente. Usar numero_pedido.
    $stmtPedido = $pdo->prepare("SELECT p.id, p.numero_pedido, p.fecha_pedido as fecha, p.observaciones as notas, 
                                      p.subtotal, p.impuestos, p.total, 
                                      c.nombre as cliente_nombre_raw, c.apellido as cliente_apellido_raw, -- Nombres raw para concatenar
                                      c.email as cliente_email, c.telefono as cliente_telefono, 
                                      c.numero_documento AS cliente_numero_documento, td.codigo AS cliente_tipo_documento_codigo, -- Documento del cliente
                                      c.direccion AS cliente_direccion, -- Dirección del cliente
                                      u.username as usuario_creador, es.nombre as estado,
                                      p.usuario_id_crea -- Para obtenerNombreUsuario si es necesario
                               FROM pedidos p 
                               LEFT JOIN clientes c ON p.cliente_id = c.id 
                               LEFT JOIN tipos_documento td ON c.tipo_documento_id = td.id -- Join para tipo de documento del cliente
                               LEFT JOIN usuarios u ON p.usuario_id_crea = u.id
                               LEFT JOIN estados_pedido es ON p.estado_id = es.id
                               WHERE p.id = ?");
    $stmtPedido->execute([$pedido_id_int]);
    $pedido = $stmtPedido->fetch(PDO::FETCH_ASSOC);

    if (!$pedido) {
        error_log("Error en generarComprobantePedido: No se encontró el pedido con ID: " . $pedido_id_int);
        return "Error: Pedido no encontrado.";
    }
    
    // Combinar nombre y apellido del cliente
    $pedido['cliente_nombre'] = trim(($pedido['cliente_nombre_raw'] ?? '') . ' ' . ($pedido['cliente_apellido_raw'] ?? ''));
    if (empty($pedido['cliente_nombre'])) {
        $pedido['cliente_nombre'] = 'Consumidor Final';
    }

    // Para la plantilla pedido.php, vamos a asegurar que $pedido['usuario_id'] exista para obtenerNombreUsuario.
    $pedido['usuario_id'] = $pedido['usuario_id_crea'] ?? null; 

    // Obtener detalles del pedido (usar pedido_items)
    $stmtDetalles = $pdo->prepare("SELECT pi.*, pr.nombre as producto_nombre, pr.codigo as producto_codigo 
                                 FROM pedido_items pi 
                                 JOIN productos pr ON pi.producto_id = pr.id 
                                 WHERE pi.pedido_id = ?");
    $stmtDetalles->execute([$pedido_id_int]);
    $detalles = $stmtDetalles->fetchAll(PDO::FETCH_ASSOC);
    
    ob_start();
    include __DIR__.'/templates/pedido.php'; // La plantilla ya fue actualizada
    return ob_get_clean();
}

// Actualizar estado_id del pedido
function actualizarEstadoPedido($pdo, $pedido_id, $nuevo_estado_id) {
    // Validar que $nuevo_estado_id exista en la tabla estados_pedido (opcional, pero bueno)
    $stmt_check_estado = $pdo->prepare("SELECT COUNT(*) FROM estados_pedido WHERE id = ?");
    $stmt_check_estado->execute([$nuevo_estado_id]);
    if ($stmt_check_estado->fetchColumn() == 0) {
        error_log("Intento de actualizar a un estado_id no existente: {$nuevo_estado_id} para pedido {$pedido_id}");
        return false; // Estado no válido
    }
    
    // Actualizar estado_id, usuario_id_actualiza y fecha_actualizacion
    $stmt = $pdo->prepare("UPDATE pedidos SET estado_id = ?, usuario_id_actualiza = ?, fecha_actualizacion = NOW() WHERE id = ?");
    return $stmt->execute([$nuevo_estado_id, $_SESSION['user_id'] ?? null, $pedido_id]);
}

// Función para completar pedido (actualiza estado_id y stock)
function completarPedido($pdo, $pedido_id) {
    try {
        $pdo->beginTransaction();
        
        // 1. Obtener el ID del estado 'completado'
        $stmt_estado_comp = $pdo->prepare("SELECT id FROM estados_pedido WHERE LOWER(nombre) = 'completado'");
        $stmt_estado_comp->execute();
        $estado_completado_id = $stmt_estado_comp->fetchColumn();

        if (!$estado_completado_id) {
            throw new Exception("Estado 'Completado' no encontrado en la base de datos.");
        }

        // 2. Actualizar estado_id usando la función refactorizada
        if (!actualizarEstadoPedido($pdo, $pedido_id, $estado_completado_id)) {
            throw new Exception("No se pudo actualizar el estado del pedido a completado.");
        }
        
        // 3. Descontar stock (usar pedido_items)
        $detalles_stmt = $pdo->prepare("SELECT producto_id, cantidad FROM pedido_items WHERE pedido_id = ?");
        $detalles_stmt->execute([$pedido_id]);
        $detalles = $detalles_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // (Opcional) Preparar para registrar movimientos de inventario
        // $stmt_mov_inv = $pdo->prepare("INSERT INTO movimientos_inventario (producto_id, tipo, cantidad, fecha, usuario_id, observaciones, pedido_id, referencia) VALUES (?, 'salida', ?, NOW(), ?, ?, ?, ?)");
        // $numero_pedido_ref = $pdo->query("SELECT numero_pedido FROM pedidos WHERE id = {$pedido_id}")->fetchColumn();

        foreach ($detalles as $detalle) {
            $stmt_update_stock = $pdo->prepare("UPDATE productos SET stock = stock - ? WHERE id = ? AND stock >= ?");
            $stmt_update_stock->execute([$detalle['cantidad'], $detalle['producto_id'], $detalle['cantidad']]);
            
            if ($stmt_update_stock->rowCount() === 0) {
                throw new Exception("Stock insuficiente para el producto ID: ".$detalle['producto_id']." o el producto no existe.");
            }
            // (Opcional) Registrar movimiento
            // $obs_mov = "Salida por Pedido Completado #{$numero_pedido_ref}";
            // $stmt_mov_inv->execute([$detalle['producto_id'], $detalle['cantidad'], $_SESSION['user_id'] ?? null, $obs_mov, $pedido_id, 'PEDIDO_COMPLETADO']);
        }
        
        $pdo->commit();
        logSystemEvent($pdo, 'INFO', 'ORDER_COMPLETED', "Pedido ID {$pedido_id} marcado como completado y stock actualizado.", 'Pedidos', ['pedido_id' => $pedido_id]);
        return true;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logSystemEvent($pdo, 'ERROR','PEDIDO_COMPLETION_FAILED',"Error al completar pedido ID {$pedido_id}: " . $e->getMessage(),'Pedidos',['pedido_id' => $pedido_id, 'exception' => $e->getTraceAsString()]);
        return false;
    }
}
?> 