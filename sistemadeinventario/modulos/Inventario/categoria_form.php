<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado

$editMode = isset($_GET['id']);
$action_permission = $editMode ? 'edit' : 'create';
requirePermission('inventario_categorias', $action_permission);

$pageTitle = "Formulario de Categoría";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

$categoria = [
    'id' => '',
    'nombre' => '',
    'descripcion' => '',
    'activa' => 1 // Por defecto, activa al crear
];

if (isset($_GET['id'])) {
    $editMode = true;
    $categoria_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM categorias WHERE id = ?");
    $stmt->execute([$categoria_id]);
    $categoria_data = $stmt->fetch();
    if ($categoria_data) {
        $categoria = $categoria_data;
    } else {
        showAlert("Categoría no encontrada.", "danger");
        $editMode = false; 
    }
    $pageTitle = "Editar Categoría: " . htmlspecialchars($categoria['nombre']);
} else {
    $pageTitle = "Crear Nueva Categoría";
}

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?></h1>
            <a href="categorias_index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php 
        if (isset($_SESSION['form_error'])) {
            showAlert(htmlspecialchars($_SESSION['form_error']), 'danger');
            unset($_SESSION['form_error']);
        }
        // Repopular datos si vienen de un error en acciones
        if (isset($_SESSION['form_data'])) { 
            $categoria = array_merge($categoria, $_SESSION['form_data']);
            unset($_SESSION['form_data']);
        }
        ?>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="categoria_acciones.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="accion" value="<?= $editMode ? 'actualizar' : 'crear' ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="categoria_id" value="<?= htmlspecialchars($categoria['id']) ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre de la Categoría <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($categoria['nombre']) ?>" required>
                        <div class="invalid-feedback">Por favor, ingrese el nombre de la categoría.</div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($categoria['descripcion']) ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="activa" name="activa" <?= ($categoria['activa'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activa">
                            Categoría Activa
                        </label>
                        <small class="form-text text-muted d-block">Desmarque esta casilla si la categoría no debe estar disponible para nuevos productos.</small>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <a href="categorias_index.php" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save-fill me-1"></i> <?= $editMode ? 'Actualizar Categoría' : 'Guardar Categoría' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 