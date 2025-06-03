<?php 
error_reporting(E_ALL);
ini_set('display_errors', 1);

$step = $_POST['step'] ?? '1';
$db_host = $_POST['db_host'] ?? 'localhost';
$db_name = $_POST['db_name'] ?? '';
$db_user = $_POST['db_user'] ?? '';
$db_pass = $_POST['db_pass'] ?? '';

$config_file_path = __DIR__ . '/config.php';
$installation_successful = false;
$errors = [];
$messages = [];

// --- Lista de archivos y directorios del proyecto (para referencia del usuario) ---
$project_files_structure = "
.htaccess
config.php (Se intentará generar una base)
index.php
logout.php
menu_principal.php
assets/
  css/
    style.css
  img/
  js/
    main.js
  uploads/
includes/
  footer.php
  functions.php
  header.php
modulos/
  Admin/
    backup_acciones.php
    backup_index.php
    index.php
    logs_index.php
    rol_acciones.php
    rol_form.php
    roles_index.php
    usuario_acciones.php
    usuario_form.php
    usuarios_index.php
  Clientes/
    cliente_acciones.php
    cliente_form.php
    index.php
  Facturacion/
    factura_acciones.php
    factura_detalle.php
    factura_form.php
    imprimir.php
    index.php
  Inventario/
    categoria_acciones.php
    categoria_form.php
    categorias_index.php
    exportar_excel.php
    index.php
    lugar_acciones.php
    lugar_form.php
    lugares_index.php
    producto_acciones.php
    producto_detalle.php
    producto_form.php
  Pedidos/
    index.php
    pedido_acciones.php
    pedido_detalle.php
    pedido_form.php
    pedidos_functions.php
    templates/
      pedido.php
";
// ------------------------------------------------------------------------------

if ($step === '2' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($db_host) || empty($db_name) || empty($db_user)) { // db_pass puede estar vacío para algunos usuarios de MySQL
        $errors[] = "Por favor, complete todos los campos de la base de datos (la contraseña puede ser opcional).";
        $step = '1';
    } else {
        try {
            $pdo = new PDO("mysql:host={$db_host}", $db_user, $db_pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $messages[] = "Conexión al servidor MySQL exitosa.";

            // Intentar crear la base de datos si no existe
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $messages[] = "Base de datos '{$db_name}' asegurada/creada.";

            // Reconectar a la base de datos específica
            $pdo->exec("USE `{$db_name}`;");
            $messages[] = "Conectado a la base de datos '{$db_name}'.";

            // --- Creación de Tablas ---
            $sql_statements = [
                // Tabla estados_pedido
                "CREATE TABLE IF NOT EXISTS `estados_pedido` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(50) NOT NULL UNIQUE,
                    `descripcion` TEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla roles
                "CREATE TABLE IF NOT EXISTS `roles` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(50) NOT NULL UNIQUE,
                    `descripcion` TEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla usuarios
                "CREATE TABLE IF NOT EXISTS `usuarios` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `username` VARCHAR(50) NOT NULL UNIQUE,
                    `password` VARCHAR(255) NOT NULL,
                    `nombre` VARCHAR(100) NULL,
                    `apellido` VARCHAR(100) NULL,
                    `email` VARCHAR(100) NOT NULL UNIQUE,
                    `rol_id` INT NULL,
                    `activo` BOOLEAN NOT NULL DEFAULT TRUE,
                    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla permisos_roles (ejemplo básico, ajustar según necesidad)
                "CREATE TABLE IF NOT EXISTS `permisos_roles` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `rol_id` INT NOT NULL,
                    `modulo` VARCHAR(50) NOT NULL,
                    `accion` VARCHAR(50) NOT NULL,
                    FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
                    UNIQUE KEY `rol_modulo_accion` (`rol_id`, `modulo`, `accion`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla clientes
                "CREATE TABLE IF NOT EXISTS `clientes` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(100) NOT NULL,
                    `apellido` VARCHAR(100) NULL,
                    `documento` VARCHAR(20) NULL UNIQUE, 
                    `direccion` TEXT NULL,
                    `telefono` VARCHAR(20) NULL,
                    `email` VARCHAR(100) NULL,
                    `activo` BOOLEAN NOT NULL DEFAULT TRUE,
                    `fecha_registro` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `usuario_id_crea` INT NULL,
                    FOREIGN KEY (`usuario_id_crea`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla categorias
                "CREATE TABLE IF NOT EXISTS `categorias` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(100) NOT NULL UNIQUE,
                    `descripcion` TEXT NULL,
                    `activa` BOOLEAN DEFAULT TRUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla lugares
                "CREATE TABLE IF NOT EXISTS `lugares` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `nombre` VARCHAR(100) NOT NULL UNIQUE,
                    `descripcion` TEXT NULL,
                    `activo` BOOLEAN DEFAULT TRUE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla productos
                "CREATE TABLE IF NOT EXISTS `productos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `codigo` VARCHAR(50) NULL UNIQUE,
                    `nombre` VARCHAR(255) NOT NULL,
                    `descripcion` TEXT NULL,
                    `stock` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                    `precio_compra` DECIMAL(10,2) NULL,
                    `precio_venta` DECIMAL(10,2) NOT NULL,
                    `categoria_id` INT NULL,
                    `lugar_id` INT NULL, 
                    `activo` BOOLEAN NOT NULL DEFAULT TRUE,
                    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    `usuario_id_crea` INT NULL,
                    FOREIGN KEY (`categoria_id`) REFERENCES `categorias`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`lugar_id`) REFERENCES `lugares`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`usuario_id_crea`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla facturas
                "CREATE TABLE IF NOT EXISTS `facturas` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `pedido_id` INT NULL, 
                    `cliente_id` INT NOT NULL,
                    `numero_factura` VARCHAR(50) NOT NULL UNIQUE,
                    `fecha_emision` DATE NOT NULL,
                    `fecha_vencimiento` DATE NULL,
                    `estado` VARCHAR(20) NOT NULL DEFAULT 'pendiente_pago', 
                    `subtotal` DECIMAL(12,2) NOT NULL,
                    `impuestos` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `total` DECIMAL(12,2) NOT NULL,
                    `observaciones` TEXT NULL,
                    `usuario_id_crea` INT NULL,
                    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `usuario_id_actualiza` INT NULL,
                    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
                    FOREIGN KEY (`usuario_id_crea`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`usuario_id_actualiza`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
                    -- No FK a pedidos para evitar borrado en cascada si un pedido se elimina, manejar por lógica de app
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla pedidos (después de facturas y estados_pedido)
                "CREATE TABLE IF NOT EXISTS `pedidos` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `numero_pedido` VARCHAR(50) NOT NULL UNIQUE,
                    `cliente_id` INT NOT NULL,
                    `fecha_pedido` DATETIME NOT NULL,
                    `estado_id` INT NOT NULL,
                    `subtotal` DECIMAL(12,2) NOT NULL,
                    `impuestos` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `total` DECIMAL(12,2) NOT NULL,
                    `observaciones` TEXT NULL,
                    `factura_id` INT NULL, 
                    `usuario_id_crea` INT NULL,
                    `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `usuario_id_actualiza` INT NULL,
                    `fecha_actualizacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`),
                    FOREIGN KEY (`estado_id`) REFERENCES `estados_pedido`(`id`),
                    FOREIGN KEY (`factura_id`) REFERENCES `facturas`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`usuario_id_crea`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL,
                    FOREIGN KEY (`usuario_id_actualiza`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                
                // Añadir FK de facturas.pedido_id después de que pedidos exista
                "ALTER TABLE `facturas` ADD CONSTRAINT `fk_factura_pedido` FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE SET NULL;",

                // Tabla pedido_items
                "CREATE TABLE IF NOT EXISTS `pedido_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `pedido_id` INT NOT NULL,
                    `producto_id` INT NOT NULL,
                    `descripcion_producto_snapshot` VARCHAR(255) NULL, 
                    `cantidad_solicitada` DECIMAL(10,2) NOT NULL,
                    `cantidad` DECIMAL(10,2) NOT NULL, 
                    `precio_unitario` DECIMAL(10,2) NOT NULL,
                    `subtotal` DECIMAL(12,2) NOT NULL,
                    FOREIGN KEY (`pedido_id`) REFERENCES `pedidos`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`) 
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla factura_items
                "CREATE TABLE IF NOT EXISTS `factura_items` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `factura_id` INT NOT NULL,
                    `producto_id` INT NOT NULL,
                    `descripcion_producto` VARCHAR(255) NOT NULL,
                    `cantidad` DECIMAL(10,2) NOT NULL,
                    `precio_unitario` DECIMAL(10,2) NOT NULL,
                    `subtotal_item` DECIMAL(12,2) NOT NULL,
                    FOREIGN KEY (`factura_id`) REFERENCES `facturas`(`id`) ON DELETE CASCADE,
                    FOREIGN KEY (`producto_id`) REFERENCES `productos`(`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",

                // Tabla system_logs
                "CREATE TABLE IF NOT EXISTS `system_logs` (
                    `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
                    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    `level` VARCHAR(20) NOT NULL, 
                    `event_type` VARCHAR(50) NULL,
                    `message` TEXT NOT NULL,
                    `module` VARCHAR(50) NULL,
                    `user_id` INT NULL,
                    `ip_address` VARCHAR(45) NULL,
                    `context` JSON NULL, 
                    INDEX `idx_level` (`level`),
                    INDEX `idx_event_type` (`event_type`),
                    INDEX `idx_module` (`module`),
                    INDEX `idx_user_id` (`user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
            ];

            foreach ($sql_statements as $sql) {
                $pdo->exec($sql);
                $messages[] = "Ejecutado: " . substr($sql, 0, 100) . "..."; // Muestra inicio de la sentencia
            }
            $messages[] = "Tablas creadas/aseguradas exitosamente.";

            // --- Inserción de Datos Iniciales ---
            // Estados de Pedido
            $pdo->exec("INSERT IGNORE INTO `estados_pedido` (nombre, descripcion) VALUES ('En Preparación', 'Pedido recibido, pendiente de preparación en almacén.')");
            $pdo->exec("INSERT IGNORE INTO `estados_pedido` (nombre, descripcion) VALUES ('A Facturar', 'Pedido preparado, cantidades verificadas, listo para confirmación del cliente y facturación.')");
            $pdo->exec("INSERT IGNORE INTO `estados_pedido` (nombre, descripcion) VALUES ('Facturado', 'Pedido facturado, stock descontado.')");
            $pdo->exec("INSERT IGNORE INTO `estados_pedido` (nombre, descripcion) VALUES ('Completado', 'Pedido entregado y finalizado (estado legado, usar con precaución).')"); 
            $pdo->exec("INSERT IGNORE INTO `estados_pedido` (nombre, descripcion) VALUES ('Cancelado', 'Pedido cancelado por el cliente o administrativamente.')");
            $messages[] = "Estados de pedido iniciales insertados/asegurados.";

            // Rol de Administrador por defecto
            $pdo->exec("INSERT IGNORE INTO `roles` (id, nombre, descripcion) VALUES (1, 'Administrador', 'Acceso total al sistema.')");
            $pdo->exec("INSERT IGNORE INTO `roles` (id, nombre, descripcion) VALUES (2, 'Vendedor', 'Acceso a ventas y gestión de pedidos.')");
            $messages[] = "Roles iniciales insertados/asegurados.";

            // Usuario Administrador por defecto (cambiar credenciales después de instalar)
            $admin_user = 'admin';
            $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
            $admin_email = 'admin@example.com';
            $stmt = $pdo->prepare("INSERT IGNORE INTO `usuarios` (username, password, nombre, apellido, email, rol_id, activo) VALUES (?, ?, 'Admin', 'User', ?, 1, TRUE)");
            $stmt->execute([$admin_user, $admin_pass, $admin_email]);
            $messages[] = "Usuario administrador por defecto creado: <strong>{$admin_user}</strong> / contraseña: <strong>admin123</strong>. ¡CÁMBIELA INMEDIATAMENTE!";

            // Generar un config.php básico si no existe o está vacío
            if (!file_exists($config_file_path) || filesize($config_file_path) === 0) {
                $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? \"https://\" : \"http://\";
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                $script_dir = str_replace('/install.php', '', $_SERVER['SCRIPT_NAME']);
                $app_url = rtrim($protocol . $host . $script_dir, '/');

                $config_content = <<<EOT
<?php
// --- Configuración de la Base de Datos ---\ndefine('DB_HOST', '{$db_host}');
define('DB_NAME', '{$db_name}');
define('DB_USER', '{$db_user}');
define('DB_PASS', '{$db_pass}');
define('DB_CHARSET', 'utf8mb4');

// --- Configuración General de la Aplicación ---\n// Cambiar esto a la URL base de tu aplicación en el servidor
define('APP_URL', '{$app_url}');
define('APP_NAME', 'Sistema de Inventario');

// --- Sesiones ---\nini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
// session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Lax']); // Descomentar para HTTPS estricto
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// --- Zonas Horarias y Errores ---\ndate_default_timezone_set('America/Asuncion'); // Ajusta tu zona horaria
error_reporting(E_ALL); // Cambiar a 0 en producción
ini_set('display_errors', '1'); // Cambiar a '0' en producción
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/php_error.log');

// --- Conexión PDO (ejemplo básico) ---\ntry {
    \$pdo = new PDO("mysql:host=\" . DB_HOST . \";dbname=\" . DB_NAME . \";charset=\" . DB_CHARSET, DB_USER, DB_PASS);
    \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException \$e) {
    die("Error de conexión a la base de datos: " . \$e->getMessage()); // Mensaje genérico para producción
}

// --- Funciones de ayuda y seguridad CSRF ---\nif (empty(\$_SESSION['csrf_token'])) {
    \$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/includes/functions.php'; // Asegúrate que exista esta ruta

// Fin de config.php
EOT;

                if (file_put_contents($config_file_path, $config_content)) {
                    $messages[] = "Archivo <code>config.php</code> generado exitosamente con los datos proporcionados.";
                } else {
                    $errors[] = "No se pudo escribir el archivo <code>config.php</code>. Por favor, cree este archivo manualmente en la raíz del proyecto con el siguiente contenido:
<pre>" . htmlspecialchars($config_content) . "</pre>";
                }
            } else {
                $messages[] = "El archivo <code>config.php</code> ya existe. No se sobrescribió. Verifique su configuración.";
            }

            $installation_successful = true;

        } catch (PDOException $e) {
            $errors[] = "Error de Base de Datos: " . $e->getMessage();
            $step = '1'; // Volver al paso 1 si hay error
        } catch (Exception $e) {
            $errors[] = "Error General: " . $e->getMessage();
            $step = '1';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación del Sistema de Inventario</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; margin: 20px; background-color: #f4f4f4; color: #333; }
        .container { max-width: 800px; margin: auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { color: #333; }
        .error { background-color: #ffdddd; border-left: 6px solid #f44336; margin-bottom: 15px; padding: 10px 12px; color: #721c24;}
        .success { background-color: #ddffdd; border-left: 6px solid #4CAF50; margin-bottom: 15px; padding: 10px 12px; color: #155724; }
        .info { background-color: #e7f3fe; border-left: 6px solid #2196F3; margin-bottom: 15px; padding: 10px 12px; color: #0c5460; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="text"], input[type="password"] { width: calc(100% - 22px); padding: 10px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 4px; }
        button { background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background-color: #45a049; }
        pre { background-color: #eee; padding: 10px; border-radius: 4px; white-space: pre-wrap; word-wrap: break-word; }
        .footer-note { margin-top: 20px; padding-top:10px; border-top:1px solid #ddd; font-size:0.9em; color:#777; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Instalación del Sistema de Inventario</h1>

        <?php if (!empty($errors)): ?>
            <div class="error">
                <strong>Errores encontrados:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($messages) && $step !== '1'): // No mostrar mensajes de éxito si volvemos al paso 1 por error ?>
            <div class="success">
                 <strong>Progreso de la Instalación:</strong>
                <ul>
                    <?php foreach ($messages as $message): ?>
                        <li><?= $message ?></li> <?php // Permitir HTML para mensajes como el de config.php ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($installation_successful): ?>
            <div class="success">
                <h2>¡Instalación Completada!</h2>
                <p>El sistema de inventario ha sido instalado y configurado correctamente.</p>
                <p><strong>IMPORTANTE:</strong> Por razones de seguridad, por favor, <strong>elimine este archivo (<code>install.php</code>) de su servidor ahora.</strong></p>
                <p><a href="index.php" style="font-size:1.2em; font-weight:bold;">Acceder al Sistema</a></p>
            </div>
            <div class="info">
                <h3>Usuario Administrador por Defecto:</h3>
                <p><strong>Usuario:</strong> admin</p>
                <p><strong>Contraseña:</strong> admin123</p>
                <p>Por favor, cambie esta contraseña inmediatamente después de iniciar sesión.</p>
            </div>

        <?php elseif ($step === '1'): ?>
            <p>Este script le guiará a través de la configuración de la base de datos para el sistema de inventario.</p>
            <p>Antes de continuar, asegúrese de haber subido todos los archivos del proyecto a su servidor. La estructura de archivos esperada es (referencial):</p>
            <pre><?= htmlspecialchars(trim($project_files_structure)) ?></pre>
            
            <h2>Paso 1: Configuración de la Base de Datos</h2>
            <form action="install.php" method="POST">
                <input type="hidden" name="step" value="2">
                <div>
                    <label for="db_host">Host de la Base de Datos:</label>
                    <input type="text" id="db_host" name="db_host" value="<?= htmlspecialchars($db_host) ?>" required>
                </div>
                <div>
                    <label for="db_name">Nombre de la Base de Datos:</label>
                    <input type="text" id="db_name" name="db_name" value="<?= htmlspecialchars($db_name) ?>" required>
                </div>
                <div>
                    <label for="db_user">Usuario de la Base de Datos:</label>
                    <input type="text" id="db_user" name="db_user" value="<?= htmlspecialchars($db_user) ?>" required>
                </div>
                <div>
                    <label for="db_pass">Contraseña de la Base de Datos:</label>
                    <input type="password" id="db_pass" name="db_pass" value="<?= htmlspecialchars($db_pass) ?>">
                </div>
                <button type="submit">Instalar Sistema</button>
            </form>
        <?php endif; ?>

        <div class="footer-note">
            <p>Recuerde que este script <code>install.php</code> es una herramienta de configuración inicial. Una vez completada la instalación, debe ser eliminado del servidor para evitar riesgos de seguridad.</p>
        </div>
    </div>
</body>
</html> 