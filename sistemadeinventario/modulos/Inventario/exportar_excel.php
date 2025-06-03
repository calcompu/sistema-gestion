<?php
require_once __DIR__ . '/../../config.php';
// requireLogin(); // Eliminado
requirePermission('inventario_productos', 'export');
require_once __DIR__ . '/../../includes/functions.php';

// Nombre del archivo para la descarga
$filename = "productos_" . date('YmdHis') . ".csv";

// Cabeceras para forzar la descarga del archivo CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Crear un puntero de archivo para escribir en la salida (output stream)
$output = fopen('php://output', 'w');

// Escribir la fila de cabeceras del CSV (UTF-8 BOM para mejor compatibilidad con Excel)
fputs($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
fputcsv($output, [
    'Codigo',
    'Nombre',
    'Descripcion',
    'Categoria',
    'Lugar',
    'Stock Actual',
    'Stock Minimo',
    'Precio Compra',
    'Precio Venta',
    'Fecha Creacion',
    'Ultima Actualizacion'
]);

// Filtros (opcional, podrías pasarlos por GET si quieres exportar una vista filtrada)
// Por simplicidad, aquí exportaremos todos los productos activos.
$whereClauses = ["p.activo = 1"];
$params = [];

// Aplicar filtros si se reciben por GET (ejemplo básico)
if (!empty($_GET['busqueda'])) {
    $busqueda = sanitizeInput($_GET['busqueda']);
    $whereClauses[] = "(p.nombre LIKE ? OR p.codigo LIKE ?)";
    $params[] = "%{$busqueda}%";
    $params[] = "%{$busqueda}%";
}
if (!empty($_GET['categoria_id'])) {
    $whereClauses[] = "p.categoria_id = ?";
    $params[] = (int)$_GET['categoria_id'];
}
// Añadir más filtros según sea necesario...

$whereSql = "WHERE " . implode(" AND ", $whereClauses);

// Consulta para obtener los productos
$sql = "SELECT p.codigo, p.nombre, p.descripcion, 
               c.nombre as categoria_nombre, l.nombre as lugar_nombre,
               p.stock, p.stock_minimo, p.precio_compra, p.precio_venta,
               p.fecha_creacion, p.fecha_actualizacion
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        LEFT JOIN lugares l ON p.lugar_id = l.id 
        {$whereSql}
        ORDER BY p.nombre ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

// Iterar sobre los resultados y escribirlos en el CSV
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Formatear precios y fechas si es necesario para el CSV
    $row['precio_compra'] = number_format((float)($row['precio_compra'] ?? 0), 2, '.', ''); // Sin separador de miles para CSV
    $row['precio_venta'] = number_format((float)($row['precio_venta'] ?? 0), 2, '.', '');  // Sin separador de miles para CSV
    $row['fecha_creacion'] = date('Y-m-d H:i:s', strtotime($row['fecha_creacion']));
    $row['fecha_actualizacion'] = date('Y-m-d H:i:s', strtotime($row['fecha_actualizacion']));
    
    fputcsv($output, [
        $row['codigo'],
        $row['nombre'],
        $row['descripcion'],
        $row['categoria_nombre'] ?? 'N/A',
        $row['lugar_nombre'] ?? 'N/A',
        $row['stock'],
        $row['stock_minimo'],
        $row['precio_compra'],
        $row['precio_venta'],
        $row['fecha_creacion'],
        $row['fecha_actualizacion']
    ]);
}

fclose($output);
exit;
?> 