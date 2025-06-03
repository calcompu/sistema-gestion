<?php
require_once __DIR__ . '/../../config.php'; // Asegura que config.php esté al inicio para sesiones y $pdo

// Determinar el modo (crear o editar) y verificar permisos
$editMode = isset($_GET['id']) && !empty($_GET['id']);
$action_permission = $editMode ? 'edit' : 'create';

// requirePermission('inventario_productos', $action_permission); // Descomenta y ajusta si tienes esta función
requireLogin(); // Como mínimo, el usuario debe estar logueado.

$pageTitle = $editMode ? "Editar Producto" : "Crear Nuevo Producto";

$producto = [
    'id' => null,
    'nombre' => '',
    'codigo' => '',
    'descripcion' => '',
    'categoria_id' => null,
    'lugar_id' => null,
    'stock' => 0,
    'stock_minimo' => 0, // Default a 0, ya que no todas las implementaciones lo usan estrictamente.
    'precio_compra' => '0.00',
    'precio_venta' => '0.00',
    'imagen' => '',
    'activo' => 1 // Asumir activo por defecto para nuevos productos
];
$imagen_actual_url = APP_URL . '/assets/img/default_product_placeholder.png'; // Placeholder por defecto

if ($editMode) {
    $producto_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM productos WHERE id = ?"); // No filtrar por activo aquí para permitir editar productos inactivos
    $stmt->execute([$producto_id]);
    $producto_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($producto_data) {
        $producto = array_merge($producto, $producto_data);
        if (!empty($producto['imagen'])) {
            $imagen_actual_url = APP_URL . '/assets/uploads/productos/' . htmlspecialchars($producto['imagen']);
        }
        $pageTitle = "Editar Producto: " . htmlspecialchars($producto['nombre']);
    } else {
        $_SESSION['global_message'] = ['type' => 'danger', 'text' => 'Producto no encontrado (ID: ' . $producto_id . ').'];
        header('Location: index.php');
        exit;
    }
} else {
    // Generar un código de producto único sugerido para nuevos productos
    try {
        $stmtMaxCod = $pdo->query("SELECT MAX(CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED)) as max_cod FROM productos WHERE codigo LIKE 'PROD-%'");
        $maxCodNum = $stmtMaxCod->fetchColumn();
        $siguienteNumero = ($maxCodNum === null ? 0 : (int)$maxCodNum) + 1;
        $producto['codigo'] = 'PROD-' . str_pad($siguienteNumero, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        $producto['codigo'] = 'PROD-'.time(); // Fallback simple
        error_log("Error al generar código de producto automático: " . $e->getMessage());
    }
}

// Obtener categorías y lugares para los select (CORREGIDO: SIN cláusula WHERE para estado)
try {
    $categorias = $pdo->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
    $lugares = $pdo->query("SELECT id, nombre FROM lugares ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
    $lugares = [];
    // Es importante mostrar un error si esto falla, ya que los selects estarán vacíos.
    $_SESSION['global_message'] = ['type' => 'danger', 'text' => 'Error al cargar listas de categorías o lugares: ' . $e->getMessage()];
}

// Incluir el header ahora que $pageTitle está definido
if (file_exists(__DIR__ . '/../../includes/header.php')) {
    require_once __DIR__ . '/../../includes/header.php';
}
?>

<div class="container-fluid py-4">
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="fw-bold text-primary">
                <i class="fas <?= $editMode ? 'fa-edit' : 'fa-plus-circle' ?> me-2"></i><?= htmlspecialchars($pageTitle) ?>
            </h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Volver al Listado
            </a>
        </div>
    </div>

    <?php 
    // Mostrar mensajes globales (incluyendo errores de carga de categorías/lugares)
    if (function_exists('displayGlobalMessages')) { // Asumiendo que tienes esta función en config.php o functions.php
        displayGlobalMessages(); 
    } elseif (isset($_SESSION['global_message']) && is_array($_SESSION['global_message'])) { // Fallback
        echo '<div class="alert alert-' . htmlspecialchars($_SESSION['global_message']['type']) . ' alert-dismissible fade show" role="alert">' . 
             htmlspecialchars($_SESSION['global_message']['text']) . 
             '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['global_message']);
    }

    // Repopular formulario si hubo un error de validación y se guardaron los datos
    if (isset($_SESSION['form_data']) && is_array($_SESSION['form_data'])) {
        $producto = array_merge($producto, $_SESSION['form_data']); // Sobrescribir con los datos enviados
        unset($_SESSION['form_data']);
    }
    if (isset($_SESSION['form_error'])) { // Mostrar error de validación del form
         echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . 
              htmlspecialchars($_SESSION['form_error']) . 
              '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['form_error']);
    }
    ?>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form action="producto_acciones.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <input type="hidden" name="accion" value="<?= $editMode ? 'actualizar' : 'crear' ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <?php if ($editMode && isset($producto['id'])): ?>
                    <input type="hidden" name="producto_id" value="<?= htmlspecialchars($producto['id']) ?>">
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="nombre" class="form-label">Nombre del Producto <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>" required>
                                <div class="invalid-feedback">Por favor, ingrese el nombre del producto.</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="codigo" name="codigo" value="<?= htmlspecialchars($producto['codigo'] ?? '') ?>" required>
                                <div class="invalid-feedback">Por favor, ingrese un código para el producto.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="categoria_id" class="form-label">Categoría <span class="text-danger">*</span></label>
                                <select class="form-select" id="categoria_id" name="categoria_id" required>
                                    <option value="">Seleccione una categoría...</option>
                                    <?php if (!empty($categorias)): ?>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?= $cat['id'] ?>" <?= (($producto['categoria_id'] ?? null) == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay categorías disponibles</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Por favor, seleccione una categoría.</div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="lugar_id" class="form-label">Lugar/Ubicación</label>
                                <select class="form-select" id="lugar_id" name="lugar_id">
                                    <option value="">Seleccione un lugar...</option>
                                     <?php if (!empty($lugares)): ?>
                                        <?php foreach ($lugares as $lug): ?>
                                            <option value="<?= $lug['id'] ?>" <?= (($producto['lugar_id'] ?? null) == $lug['id']) ? 'selected' : '' ?>><?= htmlspecialchars($lug['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No hay lugares disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="stock" class="form-label">Stock Actual <span class="text-danger">*</span></label>
                                <input type="number" class="form-control" id="stock" name="stock" value="<?= htmlspecialchars($producto['stock'] ?? '0') ?>" min="0" required>
                                <div class="invalid-feedback">Stock debe ser 0 o más.</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="stock_minimo" class="form-label">Stock Mínimo</label>
                                <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" value="<?= htmlspecialchars($producto['stock_minimo'] ?? '0') ?>" min="0">
                                <div class="invalid-feedback">Stock mínimo debe ser 0 o más.</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="precio_compra" class="form-label">Precio Compra</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control" id="precio_compra" name="precio_compra" value="<?= htmlspecialchars(number_format(floatval($producto['precio_compra'] ?? 0), 2, '.', '')) ?>" pattern="^\d*([.,]\d{1,2})?$">
                                </div>
                                <div class="invalid-feedback">Formato de precio inválido (ej: 123.45).</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="precio_venta" class="form-label">Precio Venta <span class="text-danger">*</span></label>
                                 <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="text" class="form-control" id="precio_venta" name="precio_venta" value="<?= htmlspecialchars(number_format(floatval($producto['precio_venta'] ?? 0), 2, '.', '')) ?>" pattern="^\d*([.,]\d{1,2})?$" required>
                                </div>
                                <div class="invalid-feedback">Ingrese un precio de venta válido.</div>
                            </div>
                        </div>
                        <?php if ($editMode): ?>
                        <div class="mb-3">
                            <label class="form-label d-block">Estado del Producto:</label>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= (isset($producto['activo']) && $producto['activo'] == 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo"> <?= (isset($producto['activo']) && $producto['activo'] == 1) ? 'Producto Activo' : 'Producto Inactivo' ?> (Controla visibilidad)</label>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                Imagen del Producto
                            </div>
                            <div class="card-body text-center">
                                <img src="<?= htmlspecialchars($imagen_actual_url) ?>" alt="Vista previa de imagen" id="imagePreviewDisplay" class="img-fluid rounded mb-2" style="max-height: 200px; width:auto; border: 1px solid #ddd; padding: 5px;">
                                <label for="imagen" class="btn btn-outline-secondary btn-sm d-block mb-2">Seleccionar Imagen</label>
                                <input class="form-control form-control-sm d-none" type="file" id="imagen" name="imagen" accept="image/png, image/jpeg, image/gif" onchange="previewImage(event)">
                                <small class="form-text text-muted d-block mt-1">Max 2MB. JPG, PNG, GIF.</small>
                                <?php if ($editMode && !empty($producto['imagen'])): ?>
                                    <input type="hidden" name="imagen_actual" value="<?= htmlspecialchars($producto['imagen']) ?>">
                                    <div class="form-check mt-2 text-start">
                                        <input class="form-check-input" type="checkbox" value="1" id="eliminar_imagen" name="eliminar_imagen">
                                        <label class="form-check-label" for="eliminar_imagen">
                                            Eliminar imagen actual al guardar
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <hr class="my-4">

                <div class="d-flex justify-content-end">
                    <a href="index.php" class="btn btn-secondary me-3"><i class="fas fa-times me-1"></i>Cancelar</a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i><?= $editMode ? 'Actualizar Producto' : 'Guardar Producto' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php 
$footer_path = __DIR__ . '/../../includes/footer.php';
if (file_exists($footer_path)) {
    require_once $footer_path;
}
?>
<script>
// Validación de Bootstrap y previsualización de imagen
(function () {
  'use strict'
  var forms = document.querySelectorAll('.needs-validation')
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }
        form.classList.add('was-validated')
      }, false)
    })
})();

function previewImage(event) {
    const reader = new FileReader();
    const output = document.getElementById('imagePreviewDisplay');
    reader.onload = function(){
        output.src = reader.result;
    };
    if(event.target.files[0]){
        reader.readAsDataURL(event.target.files[0]);
    } else {
        // Si se deselecciona el archivo, volver a la imagen original o placeholder
        output.src = '<?= htmlspecialchars($imagen_actual_url) ?>'; 
    }
}
</script>