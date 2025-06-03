// Main JavaScript file for custom interactions

document.addEventListener('DOMContentLoaded', function() {
    // Ejemplo: Inicializar tooltips de Bootstrap si los usas
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Ejemplo: Confirmación genérica para botones de eliminar
    // Puedes hacer esto más específico si es necesario
    const deleteButtons = document.querySelectorAll('.btn-delete-confirm');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const message = this.dataset.confirmMessage || '¿Está seguro de que desea eliminar este elemento?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });

    // Ejemplo: Previsualización de imágenes para campos de subida de archivos
    const imagePreviewInputs = document.querySelectorAll('input[type="file"].image-preview');
    imagePreviewInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const previewElementId = this.dataset.previewTarget; // e.g., data-preview-target="#imagePreview"
            const previewElement = document.querySelector(previewElementId);

            if (file && previewElement) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewElement.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px; margin-top: 10px;" alt="Vista previa">`;
                }
                reader.readAsDataURL(file);
            } else if (previewElement) {
                previewElement.innerHTML = ''; // Limpiar si no hay archivo o no hay target
            }
        });
    });
    
    // Puedes agregar más funciones JS aquí:
    // - Funciones para AJAX
    // - Interacciones específicas de formularios
    // - Inicialización de librerías JS (select2, datatables, etc.)

    console.log('main.js cargado y DOM listo.');
});

/**
 * Función para mostrar notificaciones tipo "Toast" de Bootstrap (requiere que el HTML del toast esté en la página)
 * Ejemplo de HTML para un Toast (generalmente en footer.php o dinámicamente):
 * <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
 *   <div id="liveToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
 *     <div class="toast-header">
 *       <strong class="me-auto" id="toastTitle">Notificación</strong>
 *       <small id="toastTimestamp">Ahora</small>
 *       <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
 *     </div>
 *     <div class="toast-body" id="toastBody">
 *       Mensaje del toast.
 *     </div>
 *   </div>
 * </div>
 */
function showToast(title, body, type = 'info') { // type can be info, success, warning, danger
    const toastElement = document.getElementById('liveToast');
    if (!toastElement) return;

    const toastTitleElement = document.getElementById('toastTitle');
    const toastBodyElement = document.getElementById('toastBody');
    // const toastTimestampElement = document.getElementById('toastTimestamp'); // Podrías actualizar esto dinámicamente

    if (toastTitleElement) toastTitleElement.textContent = title;
    if (toastBodyElement) toastBodyElement.innerHTML = body; // Usar innerHTML si el body puede tener HTML

    // Quitar clases de tipo anteriores y añadir la nueva
    toastElement.classList.remove('bg-success', 'bg-danger', 'bg-warning', 'bg-info', 'text-white');
    let headerClass = '';
    switch (type) {
        case 'success':
            toastElement.classList.add('bg-success', 'text-white');
            headerClass = 'bg-success';
            break;
        case 'danger':
            toastElement.classList.add('bg-danger', 'text-white');
            headerClass = 'bg-danger';
            break;
        case 'warning':
            toastElement.classList.add('bg-warning', 'text-dark'); // text-dark para mejor contraste en warning
            headerClass = 'bg-warning';
            break;
        default:
            toastElement.classList.add('bg-info', 'text-white');
            headerClass = 'bg-info';
            break;
    }
    // Para el header del toast, si quieres que también cambie de color (puede ser opcional)
    const toastHeader = toastElement.querySelector('.toast-header');
    if(toastHeader) {
        toastHeader.className = 'toast-header '; // Reset class
        // toastHeader.classList.add(headerClass, 'text-white'); // O ajusta según el contraste
    }

    const toast = new bootstrap.Toast(toastElement);
    toast.show();
}

// Ejemplo de cómo podrías llamar a showToast desde PHP si pasas un mensaje por sesión:
// <?php if(isset($_SESSION['toast_message'])): ?>
//   showToast('<?= $_SESSION['toast_message']["title"] ?>', '<?= $_SESSION['toast_message']["body"] ?>', '<?= $_SESSION['toast_message']["type"] ?>');
//   <?php unset($_SESSION['toast_message']); ?>
// <?php endif; ?>
