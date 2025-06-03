<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado

$editMode = isset($_GET['id']);
$action_permission = $editMode ? 'edit' : 'create';
requirePermission('inventario_lugares', $action_permission);

$pageTitle = "Formulario de Lugar/Ubicación";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

$lugar = [
    'id' => '',
    'nombre' => '',
    'descripcion' => '',
    'activo' => 1 // Por defecto, activo al crear
];

if (isset($_GET['id'])) {
    $editMode = true;
    $lugar_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM lugares WHERE id = ?");
    $stmt->execute([$lugar_id]);
    $lugar_data = $stmt->fetch();
    if ($lugar_data) {
        $lugar = $lugar_data;
    } else {
        showAlert("Lugar no encontrado.", "danger");
        $editMode = false; 
    }
    $pageTitle = "Editar Lugar: " . htmlspecialchars($lugar['nombre']);
} else {
    $pageTitle = "Crear Nuevo Lugar/Ubicación";
}

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?></h1>
            <a href="lugares_index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php 
        displayGlobalMessages();

        if (isset($_SESSION['form_data'])) { 
            $lugar_form_data = $_SESSION['form_data'];
            if (isset($lugar_form_data['nombre'])) $lugar['nombre'] = $lugar_form_data['nombre'];
            if (isset($lugar_form_data['descripcion'])) $lugar['descripcion'] = $lugar_form_data['descripcion'];
            if (array_key_exists('activo', $lugar_form_data)) {
                $lugar['activo'] = !empty($lugar_form_data['activo']);
            } else if ($editMode) {
                $lugar['activo'] = 0;
            }
            unset($_SESSION['form_data']);
        }
        ?>

        <div class="card shadow mb-4">
            <div class="card-body">
                <form action="lugar_acciones.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="accion" value="<?= $editMode ? 'actualizar' : 'crear' ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="lugar_id" value="<?= htmlspecialchars($lugar['id']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

                    <div class="mb-3">
                        <label for="nombre" class="form-label">Nombre del Lugar <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombre" name="nombre" value="<?= htmlspecialchars($lugar['nombre']) ?>" required>
                        <div class="invalid-feedback">Por favor, ingrese el nombre del lugar.</div>
                    </div>

                    <div class="mb-3">
                        <label for="descripcion" class="form-label">Descripción</label>
                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?= htmlspecialchars($lugar['descripcion']) ?></textarea>
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" id="activo" name="activo" <?= ($lugar['activo'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activo">
                            Lugar Activo
                        </label>
                        <small class="form-text text-muted d-block">Desmarque esta casilla si el lugar no debe estar disponible para asignar a productos.</small>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <a href="lugares_index.php" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save-fill me-1"></i> <?= $editMode ? 'Actualizar Lugar' : 'Guardar Lugar' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>
<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 