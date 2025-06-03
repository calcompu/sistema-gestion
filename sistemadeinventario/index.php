         <?php
    require_once __DIR__ . '/config.php'; // Ajustado para usar __DIR__ por consistencia

    // Si el usuario ya está logueado, redirigir a menu_principal.php
    if (isset($_SESSION['user_id'])) {
        header('Location: menu_principal.php');
        exit;
    }

    $error_message = ''; // Renombrado de $error a $error_message para coincidir con el HTML
    $login_username = ''; // Para repoblar el campo de usuario en caso de error

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitizar el nombre de usuario que viene del POST
        $username_input = isset($_POST['username']) ? trim($_POST['username']) : ''; // Usar trim para quitar espacios
        $password_input = $_POST['password'] ?? ''; // Usar ?? para evitar notice si no está definido

        $login_username = htmlspecialchars($username_input); // Guardar para repoblar, sanitizado para HTML

        if (empty($username_input) || empty($password_input)) {
            $error_message = 'Por favor, ingrese su usuario y contraseña.';
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, usuario, password, rol, nombre_completo FROM usuarios WHERE usuario = ? AND activo = 1");
                $stmt->execute([$username_input]); 
                $user = $stmt->fetch(PDO::FETCH_ASSOC); 

                if ($user && password_verify($password_input, $user['password'])) {
                    session_regenerate_id(true); 

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['usuario']; 
                    $_SESSION['user_rol'] = $user['rol'];
                    $_SESSION['user_nombre_completo'] = $user['nombre_completo'] ?? $user['usuario']; 
                    
                    if (empty($_SESSION['csrf_token'])) { 
                       $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    }
                    
                    if (function_exists('logSystemEvent')) {
                        logSystemEvent($pdo, 'INFO', 'LOGIN_SUCCESS', "Usuario '{$user['usuario']}' inició sesión exitosamente.", 'Auth', ['user_id' => $user['id']]);
                    }

                    $redirectTo = 'menu_principal.php';
                    if (isset($_SESSION['redirect_url'])) {
                        $saved_url = $_SESSION['redirect_url'];
                        unset($_SESSION['redirect_url']);
                        if ((strpos($saved_url, '//') === false || strpos($saved_url, APP_URL) === 0) && strpos($saved_url, 'index.php') === false) {
                            $redirectTo = $saved_url;
                        }
                    }
                    header("Location: " . $redirectTo);
                    exit();

                } else {
                    if (function_exists('logSystemEvent')) {
                        logSystemEvent($pdo, 'WARNING', 'LOGIN_FAILURE', "Intento de inicio de sesión fallido para el usuario '{$username_input}'.", 'Auth', ['username_attempted' => $username_input]);
                    }
                    $error_message = 'Usuario o contraseña incorrectos, o la cuenta está inactiva.';
                }
            } catch (PDOException $e) {
                if (function_exists('logSystemEvent')) {
                    logSystemEvent($pdo, 'ERROR', 'LOGIN_EXCEPTION', "Excepción de BD ('{$username_input}'): " . $e->getMessage(), 'Auth');
                } else {
                    error_log("Error de PDO en login para '{$username_input}': " . $e->getMessage()); 
                }
                $error_message = "Error de conexión con la base de datos. Intente más tarde.";
            }
        }
    }
    
    $pageTitle = "Login - Sistema de Inventario";
    if (defined('SISTEMA_NOMBRE') && SISTEMA_NOMBRE) {
        $pageTitle .= " | " . SISTEMA_NOMBRE;
    }
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= htmlspecialchars($pageTitle) ?></title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <?php if (file_exists(__DIR__ . '/assets/css/style.css')): ?>
            <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/assets/css/style.css') ?>">
        <?php endif; ?>
        <style>
            body { display: flex; align-items: center; justify-content: center; min-height: 100vh; background-color: #f8f9fa; padding-top: 40px; padding-bottom: 40px; }
            .login-container { width: 100%; max-width: 400px; padding: 25px; background-color: #fff; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
            .login-header { text-align: center; margin-bottom: 25px; }
            .login-header img { max-width: 100px; height: auto; margin-bottom: 10px; }
        </style>
    </head>
    <body>
        <div class="login-container">
            <div class="login-header">
                <?php 
                $logo_src = (defined('APP_URL') ? rtrim(APP_URL, '/') : '.') . '/assets/img/logo.png';
                $logo_path_on_server = __DIR__ . '/assets/img/logo.png';
                if (file_exists($logo_path_on_server)): ?>
                    <img src="<?= htmlspecialchars($logo_src) ?>" alt="Logo del Sistema">
                <?php endif; ?>
                <h3><?= htmlspecialchars(defined('SISTEMA_NOMBRE') && SISTEMA_NOMBRE ? SISTEMA_NOMBRE : 'Sistema de Inventario') ?></h3>
                <p class="text-muted">Por favor, ingrese sus credenciales</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm" class="needs-validation" novalidate>
                <div class="form-floating mb-3">
                    <input type="text" class="form-control" id="username" name="username" placeholder="Usuario" value="<?= htmlspecialchars($login_username) ?>" required autofocus>
                    <label for="username"><i class="bi bi-person-fill me-2"></i>Usuario</label>
                    <div class="invalid-feedback">Por favor, ingrese su usuario.</div>
                </div>
                <div class="form-floating mb-3">
                    <input type="password" class="form-control" id="password" name="password" placeholder="Contraseña" required>
                    <label for="password"><i class="bi bi-lock-fill me-2"></i>Contraseña</label>
                    <div class="invalid-feedback">Por favor, ingrese su contraseña.</div>
                </div>
                <div class="d-grid">
                    <button class="btn btn-primary btn-lg" type="submit"><i class="bi bi-box-arrow-in-right me-2"></i>Ingresar</button>
                </div>
            </form>
            <div class="text-center mt-4 text-muted small">
                &copy; <?= date('Y') ?> <?= htmlspecialchars((defined('EMPRESA_NOMBRE') && EMPRESA_NOMBRE) ? EMPRESA_NOMBRE : (defined('SISTEMA_NOMBRE') && SISTEMA_NOMBRE ? SISTEMA_NOMBRE : 'Su Compañía')) ?>. Todos los derechos reservados.
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <?php if (file_exists(__DIR__ . '/assets/js/main.js')): ?>
            <script src="assets/js/main.js?v=<?= filemtime(__DIR__ . '/assets/js/main.js') ?>"></script>
        <?php else: ?>
        <script>
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
        <?php endif; ?>
    </body>
    </html>