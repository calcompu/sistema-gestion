<?php
// Este archivo es para funciones PHP reutilizables.
// config.php se incluye usualmente antes de cualquier archivo que use estas funciones,
// por lo que $pdo y otras constantes de config.php deberían estar disponibles si es necesario.

/**
 * Limpia una cadena para prevenir XSS.
 * @param string $data La cadena a limpiar.
 * @return string La cadena limpia.
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Redirige a otra página.
 * @param string $url La URL a la que redirigir.
 */
function redirect($url) {
    header("Location: " . APP_URL . "/" . $url);
    exit();
}

/**
 * Muestra un mensaje de alerta Bootstrap.
 * @param string $message El mensaje a mostrar.
 * @param string $type El tipo de alerta (success, danger, warning, info). Por defecto 'info'.
 */
function showAlert($message, $type = 'info') {
    echo "<div class=\"alert alert-{$type} alert-dismissible fade show\" role=\"alert\">";
    echo htmlspecialchars($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
    echo "</div>";
}

/**
 * Formatea un número como moneda.
 * @param float $number El número a formatear.
 * @param string $currencySymbol El símbolo de la moneda. Por defecto '$'.
 * @return string El número formateado como moneda.
 */
function formatCurrency($number, $currencySymbol = '$') {
    if (!is_numeric($number)) {
        return $currencySymbol . '0.00';
    }
    return $currencySymbol . number_format($number, 2, '.', ',');
}

/**
 * Genera un slug amigable para URL a partir de una cadena.
 * @param string $text La cadena de entrada.
 * @param string $divider El carácter usado como separador. Por defecto '-'.
 * @return string El slug generado.
 */
function generateSlug($text, $divider = '-') {
    // Reemplaza caracteres no alfanuméricos por el divisor
    $text = preg_replace('~[\\pL\\d]+~u', $divider, $text);
    // Translitera
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    // Elimina caracteres no deseados
    $text = preg_replace('~[^\\-\\w]+~', '', $text);
    // Elimina espacios en blanco al principio y al final
    $text = trim($text, $divider);
    // Elimina duplicados del divisor
    $text = preg_replace('~\\s+~', $divider, $text);
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    }
    return $text;
}

/**
 * Registra un mensaje de log.
 * @param string $message El mensaje a registrar.
 * @param string $level El nivel del log (INFO, WARNING, ERROR). Por defecto 'INFO'.
 * @param string $logFile El archivo de log. Por defecto 'app_log.txt' en el directorio raíz.
 */
function appLog($message, $level = 'INFO', $logFile = 'app_log.txt') {
    $logPath = __DIR__ . '/../' . $logFile; // Asume que functions.php está en /includes/
    $timestamp = date("Y-m-d H:i:s");
    $logEntry = "[{$timestamp}] [{$level}] - {$message}" . PHP_EOL;
    
    // Intentar escribir en el archivo de log
    // En un sistema real, considera permisos y rotación de logs
    @file_put_contents($logPath, $logEntry, FILE_APPEND);
}

/**
 * Convierte un string de moneda (ej. "1,250.75" o "1250.75") a un float.
 * @param string $currencyString La cadena de moneda.
 * @return float El valor numérico.
 */
function parseCurrency($currencyString) {
    // Eliminar símbolos de moneda y separadores de miles (excepto el punto decimal)
    $cleanedString = preg_replace('/[^\d.-]/', '', $currencyString);
    // En algunos locales, la coma es el decimal. Asumimos punto como decimal.
    // Si usas comas como decimales, ajusta esto o normaliza la entrada antes.
    return (float)$cleanedString;
}

/**
 * Maneja la subida de un archivo de imagen.
 *
 * @param array $fileData El array $_FILES['nombre_del_campo_file'].
 * @param string $uploadDir El directorio donde se guardará la imagen (debe terminar con /).
 * @param int $maxFileSize Tamaño máximo permitido en bytes.
 * @param array $allowedMimeTypes Array con los tipos MIME permitidos.
 * @return string|false El nombre del archivo guardado en éxito, o false en error (el error se guarda en $_SESSION['form_error']).
 */
function handleImageUpload($fileData, $uploadDir, $maxFileSize, $allowedMimeTypes) {
    if (!isset($fileData['error']) || is_array($fileData['error'])) {
        $_SESSION['form_error'] = 'Parámetros de archivo inválidos.';
        return false;
    }

    switch ($fileData['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            // No es un error si el archivo es opcional, el llamador debe manejar esto.
            // Devolvemos false para indicar que no se procesó nada, pero no necesariamente un error fatal.
            return null; // O false, dependiendo de cómo quieras manejar la opcionalidad
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $_SESSION['form_error'] = 'El archivo excede el tamaño máximo permitido.';
            return false;
        default:
            $_SESSION['form_error'] = 'Error desconocido al subir el archivo.';
            return false;
    }

    if ($fileData['size'] > $maxFileSize) {
        $_SESSION['form_error'] = 'El archivo excede el tamaño máximo permitido (' . ($maxFileSize / 1024 / 1024) . 'MB).';
        return false;
    }

    // Verificar el tipo MIME real del archivo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $fileMimeType = $finfo->file($fileData['tmp_name']);
    if (!in_array($fileMimeType, $allowedMimeTypes)) {
        $_SESSION['form_error'] = 'Tipo de archivo no permitido. Permitidos: ' . implode(', ', $allowedMimeTypes);
        return false;
    }

    if (!file_exists($uploadDir)) {
        if (!mkdir($uploadDir, 0775, true)) {
            $_SESSION['form_error'] = 'No se pudo crear el directorio de subida.';
            appLog('Fallo al crear directorio: ' . $uploadDir, 'ERROR');
            return false;
        }
    }
    
    // Generar un nombre de archivo único
    $fileExtension = pathinfo($fileData['name'], PATHINFO_EXTENSION);
    $safeFileName = preg_replace('/[^A-Za-z0-9_\-\.]/', '', basename($fileData['name'], ".{$fileExtension}"));
    if(empty($safeFileName)) $safeFileName = 'imagen_subida';
    $newFileName = $safeFileName . '_' . uniqid() . '.' . strtolower($fileExtension);
    $filePath = $uploadDir . $newFileName;

    if (!move_uploaded_file($fileData['tmp_name'], $filePath)) {
        $_SESSION['form_error'] = 'No se pudo guardar el archivo subido.';
        appLog('Fallo move_uploaded_file a: ' . $filePath . ' desde ' . $fileData['tmp_name'], 'ERROR');
        return false;
    }

    return $newFileName;
}

/**
 * Guarda un mensaje global en la sesión para mostrarlo en la siguiente carga de página.
 * @param string $message El mensaje a guardar.
 * @param string $type El tipo de mensaje (success, danger, warning, info).
 */
function setGlobalMessage($message, $type = 'info') {
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Asegurarse de que la sesión esté iniciada
    }
    $_SESSION['global_message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * Muestra y luego limpia cualquier mensaje global guardado en la sesión.
 * Utiliza la función showAlert para mostrar el mensaje.
 */
function displayGlobalMessages() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start(); // Asegurarse de que la sesión esté iniciada
    }
    if (isset($_SESSION['global_message'])) {
        showAlert($_SESSION['global_message']['text'], $_SESSION['global_message']['type']);
        unset($_SESSION['global_message']); // Limpiar el mensaje después de mostrarlo
    }

    // Adicionalmente, mostrar y limpiar errores de formulario específicos si existen
    if (isset($_SESSION['form_error'])) {
        showAlert($_SESSION['form_error'], 'danger');
        unset($_SESSION['form_error']);
    }
}

/**
 * Registra un evento del sistema en la base de datos.
 *
 * @param PDO $pdo La instancia de conexión PDO.
 * @param string $level Nivel del log (INFO, WARNING, ERROR, SECURITY, DEBUG).
 * @param string $action Acción específica realizada (ej. "login_success", "product_create").
 * @param string $message Descripción legible del evento.
 * @param string|null $module Módulo donde ocurrió el evento (ej. "Auth", "Inventario").
 * @param array|null $details Datos adicionales en formato de array asociativo para guardar como JSON.
 * @param int|null $userId ID del usuario que realizó la acción. Si es null, se intenta obtener de la sesión.
 * @return bool True si el log se guardó correctamente, False en caso contrario.
 */
function logSystemEvent($pdo, $level, $action, $message, $module = null, $details = null, $userId = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($userId === null && isset($_SESSION['user_id'])) {
            $userId = $_SESSION['user_id'];
        }

        $detailsJson = $details ? json_encode($details) : null;

        $sql = "INSERT INTO system_logs (user_id, ip_address, level, module, action, message, details) 
                VALUES (:user_id, :ip_address, :level, :module, :action, :message, :details)";
        
        $stmt = $pdo->prepare($sql);
        
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':ip_address', $ip_address, PDO::PARAM_STR);
        $stmt->bindParam(':level', $level, PDO::PARAM_STR);
        $stmt->bindParam(':module', $module, PDO::PARAM_STR);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':message', $message, PDO::PARAM_STR);
        $stmt->bindParam(':details', $detailsJson, PDO::PARAM_STR); // O PDO::PARAM_NULL si $detailsJson es null

        return $stmt->execute();

    } catch (PDOException $e) {
        // En caso de error al registrar el log, podríamos registrarlo en un archivo de emergencia.
        // Por ahora, solo lo mostraremos si estamos en modo desarrollo o lo registramos con appLog.
        error_log("Error al registrar log en BD: " . $e->getMessage());
        appLog("CRITICAL_DB_LOG_FAILURE: " . $e->getMessage(), "ERROR", "critical_db_errors.log");
        return false;
    }
}

/**
 * Formatea un tamaño en bytes a un formato legible (KB, MB, GB, etc.).
 * @param int $bytes El tamaño en bytes.
 * @param int $precision La cantidad de decimales. Por defecto 2.
 * @return string El tamaño formateado.
 */
function formatBytes($bytes, $precision = 2) {
    if ($bytes < 0) return "0 Bytes";
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

/**
 * Obtiene el nombre de usuario (username) a partir de su ID.
 * @param PDO $pdo La instancia de PDO.
 * @param int $userId El ID del usuario.
 * @return string El nombre de usuario o 'Usuario Desconocido' si no se encuentra.
 */
function obtenerNombreUsuario($pdo, $userId) {
    if (empty($userId) || !is_numeric($userId)) {
        return 'N/A'; // O 'Usuario Desconocido'
    }
    try {
        $stmt = $pdo->prepare("SELECT username FROM usuarios WHERE id = ?");
        $stmt->execute([(int)$userId]);
        $user = $stmt->fetch();
        return $user ? $user['username'] : 'Usuario Desconocido';
    } catch (PDOException $e) {
        // En caso de error de BD, no exponer detalles, solo loguear y devolver un genérico
        error_log("Error al obtener nombre de usuario ID {$userId}: " . $e->getMessage());
        return 'Error al obtener usuario';
    }
}

// Podrías agregar más funciones aquí según tus necesidades, como:
// - Funciones para paginación
// - Funciones para manejar subida de archivos
// - Funciones específicas para interactuar con la BD (aunque el ORM/PDO directo es común)

?> 