<?php
// --- CONFIGURACIONES INICIALES ESENCIALES ---

// DEFINIR CONSTANTES PRIMERO PARA QUE FUNCTIONS.PHP PUEDA CARGAR
if (!defined('SISTEMA_CARGADO')) {
    define('SISTEMA_CARGADO', true);
}
if (!defined('CONFIG_LOADED')) {
    define('CONFIG_LOADED', true); 
}

// Configuración de la zona horaria
date_default_timezone_set('America/Lima'); // Ajusta a tu zona horaria preferida

// --- CONFIGURACIÓN Y ARRANQUE DE SESIÓN ---
define('SESSION_TIMEOUT', 1800); // Tiempo de vida de la sesión en segundos (30 minutos)

if (session_status() == PHP_SESSION_NONE) {
    session_name("InventarioGestionSession"); // Nombre más específico y único para tu app

    $cookie_params = [
        'lifetime' => 0, 
        'path' => '/',    
        'domain' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null, 
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', 
        'httponly' => true, 
        'samesite' => 'Lax' 
    ];
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookie_params);
    } else {
        session_set_cookie_params(
            $cookie_params['lifetime'],
            $cookie_params['path'],
            $cookie_params['domain'],
            $cookie_params['secure'],
            $cookie_params['httponly']
        );
    }

    session_start();
}

if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
    session_unset();     
    session_destroy();   
    // Considera redirigir a login si esto ocurre en una página protegida, 
    // pero esa lógica es mejor en requireLogin() en functions.php
}
$_SESSION['LAST_ACTIVITY'] = time(); 


// --- CONFIGURACIÓN DE ERRORES (PARA DESARROLLO) ---
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- DEFINICIONES DE CONSTANTES DE LA APLICACIÓN ---

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'sistemasia_inventpro');
define('DB_USER', 'sistemasia_inventpro');
define('DB_PASS', '9QxMIvtb^(-D'); // TU CONTRASEÑA REAL
define('DB_CHARSET', 'utf8mb4');

// Configuración de la aplicación
define('APP_URL', 'https://sistemas-ia.com.ar/sistemagestion'); 
define('APP_NAME', 'Sistema de Gestión'); 
define('SISTEMA_NOMBRE', 'InventPro');    
define('EMPRESA_NOMBRE', 'Sistemas IA');  


$backupDir = __DIR__ . DIRECTORY_SEPARATOR . 'backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true); 
}
define('BACKUP_DIR', $backupDir . DIRECTORY_SEPARATOR);


define('USER_ROLES', [
    'admin' => 'Administrador',
    'editor' => 'Editor',
    'usuario' => 'Usuario'
]);

// --- CONEXIÓN A LA BASE DE DATOS (PDO) ---
// Es buena práctica que $pdo se cree después de incluir functions.php,
// y que la función conectarDB() esté en functions.php.
// Por ahora, para minimizar cambios drásticos, lo dejamos aquí,
// pero producto_form.php intentará usar conectarDB() de functions.php.
// Si conectarDB() en functions.php también crea una conexión, tendrás dos.
// Lo ideal es que functions.php tenga conectarDB() y esta se llame UNA VEZ.

$options_pdo = [ // Renombrado para evitar conflicto si conectarDB() también usa $options
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, 
];
$dsn_pdo = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET; // Renombrado

try {
    $pdo = new PDO($dsn_pdo, DB_USER, DB_PASS, $options_pdo);
} catch (PDOException $e) {
    error_log("Error CRÍTICO de conexión a la base de datos desde config.php: " . $e->getMessage() . " (DSN: {$dsn_pdo})");
    die("Error de conexión. No se pudo establecer comunicación con la base de datos. Por favor, contacte al administrador. (Config)");
}

// --- TOKEN CSRF ---
if (empty($_SESSION['csrf_token'])) {
    try {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (Exception $e) { 
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true) . microtime(true)); 
    }
}

// Las funciones de autenticación, permisos, mensajes globales y la inclusión de functions.php
// han sido ELIMINADAS de aquí. Se manejarán en functions.php y los scripts individuales.

?>