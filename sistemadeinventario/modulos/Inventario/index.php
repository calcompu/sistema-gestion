<?php
session_start();
require_once '../../config.php';
require_once '../../includes/functions.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../../index.php');
    exit;
}

// Conectar a la base de datos
$pdo = conectarDB();

// Obtener parámetros de búsqueda y filtrado
$buscar = isset($_GET['buscar']) ? $_GET['buscar'] : '';
$categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$lugar = isset($_GET['lugar']) ? $_GET['lugar'] : '';

// Construir consulta SQL con filtros
$sql = "SELECT p.*, c.nombre as categoria_nombre, l.nombre as lugar_nombre 
        FROM productos p 
        LEFT JOIN categorias c ON p.categoria_id = c.id 
        LEFT JOIN lugares l ON p.lugar_id = l.id 
        WHERE p.activo = 1";

$params = [];

if (!empty($buscar)) {
    $sql .= " AND (p.nombre LIKE :buscar OR p.codigo LIKE :buscar OR p.descripcion LIKE :buscar)";
    $params[':buscar'] = "%$buscar%";
}

if (!empty($categoria)) {
    $sql .= " AND p.categoria_id = :categoria";
    $params[':categoria'] = $categoria;
}

if (!empty($lugar)) {
    $sql .= " AND p.lugar_id = :lugar";
    $params[':lugar'] = $lugar;
}

$sql .= " ORDER BY p.nombre ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $productos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular totales
    $total_productos = count($productos);
    // Corrección: Para valor_total, necesitas el precio_compra o precio_venta, y multiplicarlo por el stock si es valor de inventario.
    // O si es solo la suma de precios unitarios, usa 'precio_venta' o 'precio_compra'.
    // Aquí asumiré la suma de precios de venta unitarios para el ejemplo.
    $valor_total_inventario = 0;
    foreach ($productos as $p_val) {
        // Si quieres el valor total del stock (precio_compra * stock)
        // $valor_total_inventario += (isset($p_val['precio_compra']) ? $p_val['precio_compra'] : 0) * (isset($p_val['stock']) ? $p_val['stock'] : 0);
        // Si es la suma de precios de venta unitarios (como en el ejemplo original)
        $valor_total_inventario += (isset($p_val['precio_venta']) ? $p_val['precio_venta'] : (isset($p_val['precio']) ? $p_val['precio'] : 0) ); // Usar precio_venta o precio
    }
    // Si tu columna de precio se llama 'precio' en la BD para la tarjeta de resumen:
    // $valor_total = array_sum(array_column($productos, 'precio')); // Así estaba antes
    
} catch (PDOException $e) {
    $error = "Error al obtener productos: " . $e->getMessage();
    $productos = [];
    $total_productos = 0;
    $valor_total_inventario = 0; // Inicializar
}

// Obtener categorías para el filtro
try {
    $stmt_categorias = $pdo->query("SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre");
    $categorias = $stmt_categorias->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categorias = [];
}

// Obtener lugares para el filtro
try {
    $stmt_lugares = $pdo->query("SELECT * FROM lugares WHERE activo = 1 ORDER BY nombre");
    $lugares = $stmt_lugares->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $lugares = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Inventario - <?= defined('SISTEMA_NOMBRE') ? SISTEMA_NOMBRE : 'Sistema' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="../../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <div class="container-fluid py-4">
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="../../menu_principal.php" class="text-decoration-none">
                        <i class="fas fa-home"></i> Inicio
                    </a>
                </li>
                <li class="breadcrumb-item active" aria-current="page">
                    <i class="fas fa-boxes"></i> Inventario
                </li>
            </ol>
        </nav>

        <div class="row mb-4">
            <div class="col-md-8">
                <h2 class="fw-bold text-primary">
                    <i class="fas fa-boxes me-2"></i>Gestión de Inventario
                </h2>
                <p class="text-muted">Administra todos los productos de tu inventario</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="producto_form.php" class="btn btn-success btn-lg">
                    <i class="fas fa-plus me-2"></i>Agregar Producto
                </a>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Total Productos</h5>
                                <h2 class="mb-0"><?= number_format($total_productos) ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-0">Valor Total Inventario</h5>
                                <h2 class="mb-0">$<?= number_format($valor_total_inventario, 2) ?></h2>
                            </div>
                            <div class="fs-1">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label for="buscar" class="form-label">Buscar producto</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text" class="form-control" id="buscar" name="buscar" 
                                   value="<?= htmlspecialchars($buscar) ?>" 
                                   placeholder="Nombre, código o descripción...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="categoria" class="form-label">Categoría</label>
                        <select class="form-select" id="categoria" name="categoria">
                            <option value="">Todas las categorías</option>
                            <?php foreach ($categorias as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $categoria == $cat['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="lugar" class="form-label">Lugar</label>
                        <select class="form-select" id="lugar" name="lugar">
                            <option value="">Todos los lugares</option>
                            <?php foreach ($lugares as $lug): ?>
                                <option value="<?= $lug['id'] ?>" <?= $lugar == $lug['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lug['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary me-2">
                            <i class="fas fa-filter"></i> Filtrar
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h6 class="mb-0">Herramientas de gestión</h6>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-success me-2" onclick="exportarExcel()">
                            <i class="fas fa-file-excel me-1"></i>Exportar Excel
                        </button>
                        <a href="categorias.php" class="btn btn-info me-2">
                            <i class="fas fa-tags me-1"></i>Categorías
                        </a>
                        <a href="lugares.php" class="btn btn-warning">
                            <i class="fas fa-map-marker-alt me-1"></i>Lugares
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="fas fa-list me-2"></i>Lista de Productos
                    <span class="badge bg-primary ms-2"><?= $total_productos ?> productos</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger m-3">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    </div>
                <?php elseif (empty($productos)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No se encontraron productos</h5>
                        <p class="text-muted">Agrega tu primer producto o ajusta los filtros de búsqueda</p>
                        <a href="producto_form.php" class="btn btn-success">
                            <i class="fas fa-plus me-2"></i>Agregar Producto
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 80px;">#</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Categoría</th>
                                    <th>Lugar</th>
                                    <th class="text-center" style="width: 100px;">Stock</th>
                                    <th class="text-end" style="width: 120px;">Precio Venta</th> <!-- Cambiado de Precio a Precio Venta -->
                                    <th class="text-center" style="width: 200px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $index => $producto): ?>
                                    <tr>
                                        <td class="fw-bold text-primary"><?= htmlspecialchars($producto['id']) // Asegurar htmlspecialchars aquí también ?></td>
                                        <td>
                                            <code class="bg-light px-2 py-1 rounded">
                                                <?= htmlspecialchars($producto['codigo']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= htmlspecialchars($producto['nombre']) ?></div>
                                            <?php if (!empty($producto['descripcion'])): // Chequear si no está vacío ?>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($producto['descripcion'], 0, 50)) ?>
                                                    <?= strlen($producto['descripcion']) > 50 ? '...' : '' ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info">
                                                <?= htmlspecialchars($producto['categoria_nombre'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark">
                                                <?= htmlspecialchars($producto['lugar_nombre'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php 
                                            $stock = isset($producto['stock']) ? (int)$producto['stock'] : 0;
                                            // Si quieres usar stock_minimo de la BD para la alerta:
                                            // $stockMinimoDefinido = isset($producto['stock_minimo']) ? (int)$producto['stock_minimo'] : 0; // Asume 0 si no está definido
                                            // $alertClass = '';
                                            // $iconHtml = '';
                                            // if ($stock <= 0) {
                                            //     $alertClass = 'text-danger fw-bold';
                                            //     $iconHtml = '<i class="fas fa-times-circle text-danger ms-1" title="Sin stock"></i>';
                                            // } elseif ($stockMinimoDefinido > 0 && $stock <= $stockMinimoDefinido) {
                                            //     $alertClass = 'text-warning fw-bold';
                                            //     $iconHtml = '<i class="fas fa-exclamation-triangle text-warning ms-1" title="Stock bajo"></i>';
                                            // } else {
                                            //      $alertClass = 'text-success'; // O sin clase especial si está bien
                                            // }
                                            // Lógica original con umbrales fijos:
                                            $alertClass = $stock <= 5 ? 'text-danger fw-bold' : ($stock <= 10 ? 'text-warning fw-bold' : '');
                                            ?>
                                            <span class="<?= $alertClass ?>">
                                                <?= number_format($stock) ?>
                                            </span>
                                            <?php if ($stock <= 5 && $stock > 0): // Muestra icono de warning si está bajo pero no agotado ?>
                                                <i class="fas fa-exclamation-triangle text-warning ms-1" title="Stock bajo"></i>
                                            <?php elseif ($stock <= 0): // Muestra icono de peligro si no hay stock ?>
                                                 <i class="fas fa-times-circle text-danger ms-1" title="Sin stock"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end fw-bold">
                                            <?php 
                                            // Usar precio_venta si existe, sino precio (por compatibilidad si la columna se llamaba solo precio)
                                            $precio_mostrar = isset($producto['precio_venta']) ? $producto['precio_venta'] : (isset($producto['precio']) ? $producto['precio'] : 0);
                                            ?>
                                            $<?= number_format($precio_mostrar, 2) ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group" role="group">
                                                <a href="producto_detalle.php?id=<?= $producto['id'] ?>" 
                                                   class="btn btn-sm btn-outline-info" title="Ver detalles">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="producto_form.php?id=<?= $producto['id'] ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <button onclick="inactivarProducto(<?= $producto['id'] ?>)" 
                                                        class="btn btn-sm btn-outline-warning" title="Inactivar">
                                                    <i class="fas fa-archive"></i>
                                                </button>
                                                <button onclick="eliminarProducto(<?= $producto['id'] ?>)" 
                                                        class="btn btn-sm btn-outline-danger" title="Eliminar">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include '../../includes/footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function exportarExcel() {
            // Construir los parámetros GET actuales para pasarlos a exportar_excel.php
            const queryParams = new URLSearchParams(window.location.search);
            window.location.href = 'exportar_excel.php?' + queryParams.toString();
        }

        function inactivarProducto(id) {
            Swal.fire({
                title: '¿Inactivar producto?',
                text: 'El producto será marcado como inactivo pero no se eliminará de la base de datos.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f0ad4e',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, inactivar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-warning me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `producto_acciones.php?accion=inactivar&id=${id}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
                }
            });
        }

        function eliminarProducto(id) {
            Swal.fire({
                title: '¿Eliminar producto permanentemente?',
                text: 'Esta acción no se puede deshacer y el producto se borrará de la base de datos.',
                icon: 'error',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                customClass: {
                    confirmButton: 'btn btn-danger me-2',
                    cancelButton: 'btn btn-secondary'
                },
                buttonsStyling: false
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `producto_acciones.php?accion=eliminar&id=${id}&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`;
                }
            });
        }

        // Búsqueda mejorada y envío de filtros
        const filterForm = document.querySelector('form.row.g-3');
        const buscarInput = document.getElementById('buscar');
        const categoriaSelect = document.getElementById('categoria');
        const lugarSelect = document.getElementById('lugar');
        let searchTimeout;

        function submitFilterForm() {
            filterForm.submit();
        }

        if (buscarInput) {
            buscarInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(submitFilterForm, 600); // Un poco más de delay
            });
        }
        if (categoriaSelect) {
            categoriaSelect.addEventListener('change', submitFilterForm);
        }
        if (lugarSelect) {
            lugarSelect.addEventListener('change', submitFilterForm);
        }
    </script>
</body>
</html>