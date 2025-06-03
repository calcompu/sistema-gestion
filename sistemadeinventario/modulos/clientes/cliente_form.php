<?php
require_once __DIR__ . '/../../config.php';
requireLogin();

$editMode = false;
if (isset($_GET['id'])) {
    $editMode = true;
    requirePermission('clientes', 'edit');
    $cliente_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$cliente_id]);
    $cliente_data = $stmt->fetch();

    if ($cliente_data) {
        $cliente = $cliente_data;
        $pageTitle = "Editar Cliente: " . htmlspecialchars($cliente['nombre'] . ' ' . $cliente['apellido']);
    } else {
        setGlobalMessage("Cliente no encontrado.", "danger");
        header('Location: ' . APP_URL . '/Modulos/Clientes/index.php');
        exit;
    }
} else {
    requirePermission('clientes', 'create');
    $pageTitle = "Crear Nuevo Cliente";
    $cliente = ['activo' => 1]; // Valor por defecto para el estado al crear
}

// Obtener tipos de documento para el select
$stmt_tipos_doc = $pdo->query("SELECT id, nombre, codigo FROM tipos_documento ORDER BY nombre ASC");
$tipos_documento_list = $stmt_tipos_doc->fetchAll(PDO::FETCH_ASSOC);

// Repopular datos del formulario si vienen de un error en acciones
if (isset($_SESSION['form_data']['cliente'])) {
    $cliente = array_merge($cliente, $_SESSION['form_data']['cliente']);
    if (isset($_SESSION['form_data']['cliente']['tipo_documento_id'])) {
        $cliente['tipo_documento_id'] = $_SESSION['form_data']['cliente']['tipo_documento_id'];
    }
    unset($_SESSION['form_data']['cliente']);
}

?>
<main class="main-content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= htmlspecialchars($pageTitle) ?></h1>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary"><?= $editMode ? 'Modificar Datos del Cliente' : 'Ingresar Datos del Nuevo Cliente' ?></h6>
            </div>
            <div class="card-body">
                <form action="cliente_acciones.php" method="POST" class="needs-validation" novalidate>
                    <input type="hidden" name="accion" value="<?= $editMode ? 'actualizar' : 'crear' ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="cliente_id" value="<?= htmlspecialchars($cliente['id']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="nombre" class="form-label">Nombres <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre" name="cliente[nombre]" value="<?= htmlspecialchars($cliente['nombre']) ?>" required>
                            <div class="invalid-feedback">Por favor, ingrese el nombre del cliente.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="apellido" class="form-label">Apellidos <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="apellido" name="cliente[apellido]" value="<?= htmlspecialchars($cliente['apellido']) ?>" required>
                            <div class="invalid-feedback">Por favor, ingrese el apellido del cliente.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="tipo_documento_id" class="form-label">Tipo de Documento <span class="text-danger">*</span></label>
                            <select class="form-select" id="tipo_documento_id" name="cliente[tipo_documento_id]" required>
                                <option value="">Seleccione un tipo...</option>
                                <?php foreach ($tipos_documento_list as $tipo_doc): ?>
                                    <option value="<?= htmlspecialchars($tipo_doc['id']) ?>" <?= (isset($cliente['tipo_documento_id']) && $cliente['tipo_documento_id'] == $tipo_doc['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo_doc['nombre'] . ' (' . $tipo_doc['codigo'] . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor, seleccione un tipo de documento.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="numero_documento" class="form-label">Número de Documento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="numero_documento" name="cliente[numero_documento]" value="<?= htmlspecialchars($cliente['numero_documento'] ?? '') ?>" required>
                            <div class="invalid-feedback">Por favor, ingrese el número de documento.</div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="cliente[email]" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                            <div class="invalid-feedback">Por favor, ingrese un email válido.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="telefono" name="cliente[telefono]" value="<?= htmlspecialchars($cliente['telefono'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="row">
                         <div class="col-md-6 mb-3">
                            <label for="activo" class="form-label">Estado <span class="text-danger">*</span></label>
                            <select class="form-select" id="activo" name="cliente[activo]" required>
                                <option value="1" <?= (isset($cliente['activo']) && $cliente['activo'] == 1) ? 'selected' : '' ?>>Activo</option>
                                <option value="0" <?= (isset($cliente['activo']) && $cliente['activo'] == 0 && $editMode) ? 'selected' : '' ?>>Inactivo</option>
                            </select>
                            <div class="invalid-feedback">Por favor, seleccione un estado.</div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="direccion" class="form-label">Dirección</label>
                        <textarea class="form-control" id="direccion" name="cliente[direccion]" rows="3"><?= htmlspecialchars($cliente['direccion'] ?? '') ?></textarea>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-end">
                        <a href="index.php" class="btn btn-secondary me-2">Cancelar</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save-fill me-1"></i> <?= $editMode ? 'Actualizar Cliente' : 'Guardar Cliente' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Script para validación Bootstrap (ya debería estar en main.js o footer, pero se puede incluir por si acaso)
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
})()
</script> 