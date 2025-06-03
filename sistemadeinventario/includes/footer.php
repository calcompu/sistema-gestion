    <!-- Aquí termina el contenido principal de la página que se abrió en header.php -->
    </main> <!-- Cierre de .main-content si se usa con sidebar -->

    <footer class="bg-light text-center text-lg-start mt-auto py-3">
        <div class="container">
            <p class="text-center text-muted mb-0">
                &copy; <?= date("Y") ?> <?= APP_NAME ?>. Todos los derechos reservados.
                <?php if (defined('APP_VERSION')): ?>
                    <span class="ms-2">Versión <?= APP_VERSION ?></span>
                <?php endif; ?>
            </p>
            <!-- Podrías agregar más enlaces o información aquí si es necesario -->
        </div>
    </footer>

    <?php 
    // Ajustar la ruta de main.js según la profundidad del archivo actual
    $pathPrefixJS = '';
    if (strpos($_SERVER['REQUEST_URI'], '/Modulos/') !== false) {
        $pathPrefixJS = '../../'; 
    } else {
        $pathPrefixJS = '';
    }
    ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $pathPrefixJS ?>assets/js/main.js"></script> <!-- Tu JS personalizado -->

    <!-- Script para la validación de formularios Bootstrap (si no está en main.js) -->
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

        // Toggle sidebar (ejemplo básico, podrías moverlo a main.js)
        const sidebarToggler = document.getElementById('sidebarToggler'); // Necesitarás un botón con este ID
        if (sidebarToggler) {
            sidebarToggler.addEventListener('click', function() {
                document.body.classList.toggle('sidebar-collapsed');
            });
        }
    </script>
</body>
</html>