<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pedido <?= htmlspecialchars($pedido['numero_pedido'] ?? 'N/A') ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; line-height: 1.4; } /* Reducir tamaño base */
        .comprobante-wrapper { max-width: 800px; margin: 0 auto; }
        .seccion-comprobante { padding: 15px; border: 1px solid #ccc; margin-bottom: 20px; }
        .header { text-align: center; margin-bottom: 15px; }
        .header h2 { margin: 0 0 5px 0; font-size: 1.4em; }
        .header h3 { margin: 0; font-size: 1.2em; }
        .info-cliente, .info-pedido { margin-bottom: 15px; padding: 10px; background: #f9f9f9; border: 1px solid #eee; }
        .info-cliente h4, .info-pedido h4 { margin-top: 0; margin-bottom: 10px; font-size: 1.1em; border-bottom: 1px solid #ddd; padding-bottom: 5px;}
        .table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        .table th { background: #f0f0f0; text-align: left; padding: 6px; border: 1px solid #ddd; }
        .table td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .total-section td { font-weight: bold; }
        .total-section .emphasis { font-size: 1.1em; }
        .footer { margin-top: 25px; text-align: center; font-size: 0.85em; color: #555; }
        .notas { margin-top:15px; padding:10px; border:1px solid #eee; background: #fdfdfd;}
        .notas h4 { margin-top: 0; margin-bottom: 5px; font-size: 1.1em;}
        .titulo-copia {
            text-align: center;
            font-size: 1.3em; /* Ajustado */
            font-weight: bold;
            margin-bottom: 10px; /* Ajustado */
            border: 2px dashed #555; /* Ajustado */
            padding: 8px; /* Ajustado */
            background-color: #f0f0f0;
        }
         .row-details { display: flex; justify-content: space-between; margin-bottom: 15px; }
         .col-half { width: 48%; }

        @media print {
            body { font-size: 10pt; margin:0; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; } /* Ajustar tamaño para impresión */
            .comprobante-wrapper { max-width: 100%; margin:0; border: none; box-shadow: none; }
            .seccion-comprobante { border: 1px solid #ccc; margin: 0 auto 20mm auto; padding: 10mm; width: 180mm; /* Ancho A4 menos márgenes */ height: 257mm; /* Alto A4 menos márgenes y footer*/ box-sizing: border-box; page-break-after: always; }
            .seccion-comprobante:last-child { page-break-after: auto; }
            .no-print { display: none; }
            .info-cliente, .info-pedido, .notas { background: #f9f9f9 !important; } /* Forzar fondo en impresión */
            .table th { background: #f0f0f0 !important; } /* Forzar fondo en impresión */
            .titulo-copia { background-color: #f0f0f0 !important; }
        }
    </style>
</head>
<body>
    <div class="comprobante-wrapper">
        <?php for ($i = 0; $i < 2; $i++): // 0 para Original, 1 para Copia ?>
        <div class="seccion-comprobante <?= ($i == 0) ? 'original' : 'copia-deposito' ?>">
            <div class="titulo-copia">
                <?= ($i == 0) ? 'ORIGINAL' : 'COPIA PARA DEPÓSITO' ?>
            </div>

            <div class="header">
                <h2>COMPROBANTE DE PEDIDO</h2>
                <h3>N° <?= htmlspecialchars($pedido['numero_pedido'] ?? 'N/A') ?></h3>
            </div>
            
            <div class="row-details info-pedido">
                <div class="col-half">
                    <p><strong>Fecha Pedido:</strong> <?= isset($pedido['fecha']) ? htmlspecialchars(date('d/m/Y H:i', strtotime($pedido['fecha']))) : 'N/A' ?></p>
                    <p><strong>Estado:</strong> <?= isset($pedido['estado']) ? htmlspecialchars(ucfirst($pedido['estado'])) : 'N/A' ?></p>
                </div>
                <div class="col-half text-right">
                    <p><strong>Preparado por:</strong> <?= isset($pedido['usuario_creador']) ? htmlspecialchars($pedido['usuario_creador']) : (isset($pedido['usuario_id']) ? htmlspecialchars(obtenerNombreUsuario($pdo, $pedido['usuario_id'])) : 'N/A') ?></p>
                </div>
            </div>
            
            <div class="info-cliente">
                <h4>Datos del Cliente</h4>
                <p><strong>Nombre:</strong> <?= htmlspecialchars($pedido['cliente_nombre'] ?? 'Consumidor Final') ?></p>
                <p>
                    <strong>Documento:</strong> 
                    <?= htmlspecialchars($pedido['cliente_tipo_documento_codigo'] ?? '') ?>
                    <?= htmlspecialchars($pedido['cliente_numero_documento'] ?? 'N/A') ?>
                </p>
                <p><strong>Email:</strong> <?= htmlspecialchars($pedido['cliente_email'] ?? 'N/A') ?></p>
                <p><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['cliente_telefono'] ?? 'N/A') ?></p>
                <p><strong>Dirección:</strong> <?= nl2br(htmlspecialchars($pedido['cliente_direccion'] ?? 'N/A')) ?></p>
            </div>
            
            <table class="table">
                <thead>
                    <tr>
                        <th>Producto (Código)</th>
                        <th class="text-center">Cantidad</th>
                        <th class="text-right">Precio Unit.</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (isset($detalles) && is_array($detalles)): foreach ($detalles as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['producto_nombre'] ?? 'N/A') ?> (<?= htmlspecialchars($item['producto_codigo'] ?? 'N/A') ?>)</td>
                        <td class="text-center"><?= htmlspecialchars($item['cantidad'] ?? 0) ?></td>
                        <td class="text-right"><?= htmlspecialchars(formatCurrency($item['precio_unitario'] ?? 0)) ?></td>
                        <td class="text-right"><?= htmlspecialchars(formatCurrency($item['subtotal'] ?? 0)) ?></td>
                    </tr>
                    <?php endforeach; else: ?>
                    <tr><td colspan="4" class="text-center">No hay productos en este pedido.</td></tr>
                    <?php endif; ?>
                </tbody>
                <tfoot class="total-section">
                    <tr>
                        <td colspan="2"></td>
                        <td class="text-right">Subtotal:</td>
                        <td class="text-right"><?= htmlspecialchars(formatCurrency($pedido['subtotal'] ?? 0)) ?></td>
                    </tr>
                    <?php if (isset($pedido['impuestos']) && (float)($pedido['impuestos'] ?? 0) > 0): ?>
                    <tr>
                        <td colspan="2"></td>
                        <td class="text-right">Impuestos:</td>
                        <td class="text-right"><?= htmlspecialchars(formatCurrency($pedido['impuestos'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="emphasis">
                        <td colspan="2"></td>
                        <td class="text-right">Total General:</td>
                        <td class="text-right"><?= htmlspecialchars(formatCurrency($pedido['total'] ?? 0)) ?></td>
                    </tr>
                </tfoot>
            </table>
            
            <?php if (!empty($pedido['notas'])): ?>
            <div class="notas">
                <h4>Notas Especiales</h4>
                <p><?= nl2br(htmlspecialchars($pedido['notas'])) ?></p>
            </div>
            <?php endif; ?>
            
            <div class="footer">
                <p>Comprobante generado el <?= htmlspecialchars(date('d/m/Y H:i')) ?> por <?= htmlspecialchars(APP_NAME) ?>.</p>
                <p>Usuario: <?= htmlspecialchars($_SESSION['username'] ?? 'Sistema') ?></p>
            </div>
        </div> 
        <?php endfor; // Fin del bucle Original/Copia ?>
    </div>
    <div class="no-print" style="text-align:center; padding: 20px;">
        <button onclick="window.print();">Imprimir Comprobante</button>
        <button onclick="window.close();">Cerrar Ventana</button>
    </div>
</body>
</html>