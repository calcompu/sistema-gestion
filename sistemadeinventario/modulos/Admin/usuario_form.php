<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // requirePermission ya llama a requireLogin()
// Ya no es necesario determinar la acción aquí si 'manage' cubre todo
// $action_permission = (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) ? 'edit' : 'create';
requirePermission('admin_usuarios', 'manage'); // Usar permiso 'manage'

$pageTitle = "Formulario de Usuario";
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/functions.php';

// Definición de roles disponibles (podría venir de config.php o BD)
$roles_sistema = [
    'admin' => 'Administrador',
    'editor' => 'Editor',
    'usuario' => 'Usuario'
    // Añadir más roles según sea necesario
];

$modo_edicion = false;
$usuario_data = [
    'id' => null,
    'username' => '',
    'nombre_completo' => '',
    'email' => '',
    'rol' => 'usuario', // Rol por defecto para nuevos usuarios
    'activo' => 1 // Por defecto activo
];

if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $modo_edicion = true;
    $usuario_id_editar = (int)$_GET['id'];
    $pageTitle = "Editar Usuario";

    $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmt->execute([$usuario_id_editar]);
    $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario_existente) {
        $usuario_data = $usuario_existente;
    } else {
        setGlobalMessage("Usuario no encontrado para editar.", "warning");
        header('Location: usuarios_index.php');
        exit;
    }
} else {
    $pageTitle = "Añadir Nuevo Usuario";
}

// Repoblar datos del formulario si existen en sesión (por errores de validación)
if (isset($_SESSION['form_data']['usuario'])) {
    $usuario_data = array_merge($usuario_data, $_SESSION['form_data']['usuario']);
    unset($_SESSION['form_data']['usuario']);
}

?>

<main class="main-content">
    <div class="container-fluid">
        <div class="d-sm-flex align-items-center justify-content-between mb-4">
            <h1 class="h3 mb-0 text-gray-800"><?= $pageTitle ?></h1>
            <a href="usuarios_index.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver al Listado
            </a>
        </div>

        <?php displayGlobalMessages(); ?>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <?= $modo_edicion ? 'Modificar datos del usuario' : 'Ingresar datos del nuevo usuario' ?>
                </h6>
            </div>
            <div class="card-body">
                <form action="usuario_acciones.php" method="POST" id="usuarioForm" class="needs-validation" novalidate>
                    <input type="hidden" name="accion" value="<?= $modo_edicion ? 'actualizar' : 'crear' ?>">
                    <?php if ($modo_edicion): ?>
                        <input type="hidden" name="usuario_id" value="<?= htmlspecialchars($usuario_data['id']) ?>">
                    <?php endif; ?>
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Nombre de Usuario <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($usuario_data['username']) ?>" required maxlength="50">
                            <div class="invalid-feedback">Por favor, ingrese un nombre de usuario.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="nombre_completo" class="form-label">Nombre Completo</label>
                            <input type="text" class="form-control" id="nombre_completo" name="nombre_completo" value="<?= htmlspecialchars($usuario_data['nombre_completo']) ?>" maxlength="100">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Correo Electrónico <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($usuario_data['email']) ?>" required maxlength="100">
                            <div class="invalid-feedback">Por favor, ingrese un correo electrónico válido.</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                            <select class="form-select" id="rol" name="rol" required>
                                <?php foreach ($roles_sistema as $key_rol => $valor_rol): ?>
                                    <option value="<?= htmlspecialchars($key_rol) ?>" <?= ($usuario_data['rol'] == $key_rol) ? 'selected' : '' ?>><?= htmlspecialchars($valor_rol) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback">Por favor, seleccione un rol para el usuario.</div>
                        </div>
                    </div>

                    <hr>
                    <h6 class="text-muted mb-3">Gestión de Contraseña</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Contraseña</label>
                            <input type="password" class="form-control" id="password" name="password" <?= !$modo_edicion ? 'required' : '' ?> minlength="6" autocomplete="new-password">
                            <?php if ($modo_edicion): ?>
                                <small class="form-text text-muted">Dejar en blanco para no cambiar la contraseña actual.</small>
                            <?php else: ?>
                                <div class="invalid-feedback">La contraseña es requerida y debe tener al menos 6 caracteres.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="password_confirm" class="form-label">Confirmar Contraseña</label>
                            <input type="password" class="form-control" id="password_confirm" name="password_confirm" <?= !$modo_edicion ? 'required' : '' ?> minlength="6">
                            <div class="invalid-feedback">Por favor, confirme la contraseña.</div>
                            <div class="valid-feedback">Las contraseñas coinciden.</div>
                        </div>
                    </div>

                    <div class="row mb-3">
                         <div class="col-md-6">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1" <?= ($usuario_data['activo'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="activo">Usuario Activo</label>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="bi bi-save me-1"></i> <?= $modo_edicion ? 'Actualizar Usuario' : 'Guardar Usuario' ?>
                        </button>
                        <a href="usuarios_index.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle me-1"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<script>
// Example starter JavaScript for disabling form submissions if there are invalid fields
(function () {
  'use strict'

  // Fetch all the forms we want to apply custom Bootstrap validation styles to
  var forms = document.querySelectorAll('.needs-validation')

  // Loop over them and prevent submission
  Array.prototype.slice.call(forms)
    .forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (!form.checkValidity()) {
          event.preventDefault()
          event.stopPropagation()
        }

        // Validar que las contraseñas coincidan (si se está creando o se ingresó una nueva)
        var password = form.querySelector('#password');
        var passwordConfirm = form.querySelector('#password_confirm');
        
        if (password && passwordConfirm) { // Asegurarse que los campos existen
            if (password.value !== '' || (form.querySelector('input[name="accion"]').value === 'crear')) {
                 if (password.value !== passwordConfirm.value) {
                    passwordConfirm.setCustomValidity('Las contraseñas no coinciden.');
                    event.preventDefault();
                    event.stopPropagation();
                } else {
                    passwordConfirm.setCustomValidity('');
                }
            } else {
                 passwordConfirm.setCustomValidity(''); // Si no se ingresa contraseña nueva en edición, no validar
            }

            // Forzar la muestra del feedback de validación si es necesario
            if (!passwordConfirm.checkValidity()) {
                passwordConfirm.classList.add('is-invalid');
            } else if (password.value !== '' && passwordConfirm.value !== '') {
                 passwordConfirm.classList.remove('is-invalid');
                 passwordConfirm.classList.add('is-valid');
            }
        }

        form.classList.add('was-validated')
      }, false)
    })
})()
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 