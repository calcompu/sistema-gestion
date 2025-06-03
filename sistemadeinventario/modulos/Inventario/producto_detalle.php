<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado
requirePermission('inventario_productos', 'view_detail');

$pageTitle = "Detalle de Producto";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_GET['id'])) {
    showAlert("No se especificó un ID de producto.", "warning");
    echo "<main class=\"main-content\"><div class=\"container-fluid\"><a href=\"index.php\" class=\"btn btn-primary\"><i class=\"bi bi-arrow-left\"></i> Volver al Listado</a></div></main>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit();
}

$producto_id = (int)$_GET['id'];

$sql = "SELECT p.*, c.nombre as categoria_nombre, l.nombre as lugar_nombre,
               uc.username as usuario_creador, ua.username as usuario_actualizador
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        LEFT JOIN lugares l ON p.lugar_id = l.id 
        LEFT JOIN usuarios uc ON p.usuario_id_crea = uc.id
        LEFT JOIN usuarios ua ON p.usuario_id_actualiza = ua.id
        WHERE p.id = ? AND p.activo = 1"; // Solo productos activos
$stmt = $pdo->prepare($sql);
$stmt->execute([$producto_id]);
$producto = $stmt->fetch();

if (!$producto) {
    showAlert("Producto no encontrado o no está activo.", "danger");
    echo "<main class=\"main-content\"><div class=\"container-fluid\"><a href=\"index.php\" class=\"btn btn-primary\"><i class=\"bi bi-arrow-left\"></i> Volver al Listado</a></div></main>";
    require_once __DIR__ . '/../../includes/footer.php';
    exit();
}

$pageTitle = "Detalle: " . htmlspecialchars($producto['nombre']);
// Actualizar el título en el header ya cargado (esto es un truco, idealmente se pasa al header)
echo "<script>document.title = \"" . addslashes($pageTitle) . " - " . APP_NAME . "\";</script>";

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($producto['nombre']) ?></h1>
            <div>
                <?php if (hasPermission('inventario_productos', 'edit')): ?>
                <a href="producto_form.php?id=<?= $producto['id'] ?>" class="btn btn-warning me-2">
                    <i class="bi bi-pencil-fill me-1"></i> Editar
                </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Volver al Listado
                </a>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="row g-0">
                <div class="col-md-4 text-center p-3">
                    <?php 
                    $imagePath = APP_URL . '/assets/uploads/productos/' . ($producto['imagen'] ?? 'default.png');
                    // Fallback a una imagen placeholder si no existe o está vacía
                    $finalImagePath = $imagePath;
                    if (empty($producto['imagen'])) { // O podrías hacer un file_exists del lado del servidor
                         $finalImagePath = APP_URL . '/assets/img/default_product_placeholder.png';
                    }
                    ?>
                    <img src="<?= $finalImagePath ?>" 
                         onerror="this.onerror=null; this.src='<?= APP_URL ?>/assets/img/default_product_placeholder.png';" 
                         class="img-fluid rounded" 
                         alt="<?= htmlspecialchars($producto['nombre']) ?>" 
                         style="max-height: 300px; max-width: 100%; object-fit: contain;">
                </div>
                <div class="col-md-8">
                    <div class="card-body">
                        <h5 class="card-title">Código: <?= htmlspecialchars($producto['codigo']) ?></h5>
                        
                        <p class="card-text"><strong>Descripción:</strong><br><?= nl2br(htmlspecialchars($producto['descripcion'] ?? 'No especificada')) ?></p>
                        
                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>Categoría:</strong> <?= htmlspecialchars($producto['categoria_nombre'] ?? 'N/A') ?></p>
                                <p class="mb-1"><strong>Lugar/Ubicación:</strong> <?= htmlspecialchars($producto['lugar_nombre'] ?? 'N/A') ?></p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>Precio de Compra:</strong> <?= formatCurrency($producto['precio_compra']) ?></p>
                                <p class="mb-1"><strong>Precio de Venta:</strong> <?= formatCurrency($producto['precio_venta']) ?></p>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-sm-6">
                                <p class="mb-1"><strong>Stock Actual:</strong> 
                                    <span class="fw-bold fs-5"><?= htmlspecialchars($producto['stock']) ?></span> unidades
                                </p>
                                <p class="mb-1"><strong>Stock Mínimo:</strong> <?= htmlspecialchars($producto['stock_minimo']) ?> unidades</p>
                            </div>
                            <div class="col-sm-6">
                                <p class="mb-0"><strong>Estado del Stock:</strong>
                                    <?php 
                                    if ($producto['stock'] <= 0) {
                                        echo '<span class="badge bg-danger fs-6 ms-2">Sin Stock</span>';
                                    } elseif ($producto['stock'] <= $producto['stock_minimo']) {
                                        echo '<span class="badge bg-warning text-dark fs-6 ms-2">Stock Bajo</span>';
                                    } else {
                                        echo '<span class="badge bg-success fs-6 ms-2">Disponible</span>';
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                        
                        <hr>
                        <p class="card-text"><small class="text-muted">Fecha de Creación: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($producto['fecha_creacion']))) ?> por <?= htmlspecialchars($producto['usuario_creador'] ?? 'Sistema') ?></small></p>
                        <?php if (!empty($producto['fecha_actualizacion']) && $producto['fecha_actualizacion'] != $producto['fecha_creacion']): // Mostrar solo si hubo una actualización real ?>
                        <p class="card-text"><small class="text-muted">Última Actualización: <?= htmlspecialchars(date('d/m/Y H:i', strtotime($producto['fecha_actualizacion']))) ?> por <?= htmlspecialchars($producto['usuario_actualizador'] ?? 'N/A') ?></small></p>
                        <?php endif; ?>
                        
                    </div>
                </div>
            </div>
        </div>

        <!-- Aquí podrías agregar secciones adicionales como historial de movimientos de stock, etc. -->

    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 