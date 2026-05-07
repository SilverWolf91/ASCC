<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Contraseña - ASCC</title>

    <link rel="icon" type="image/png" href="/ascc/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/public/css/ascc-theme-CORRECTO.css">
    <link rel="stylesheet" href="/ascc/public/css/recuperar.css">
</head>

<body>

    <!-- BANNER SUPERIOR - CINTA ANIMADA -->
    <div class="banner-top">
        <div class="banner-marquee">
            <div class="marquee-content">
                <span class="marquee-item">🔑 Recupera tu cuenta ASCC</span>
                <span class="marquee-item">🛡️ Proceso 100% seguro</span>
                <span class="marquee-item">📧 Enlace válido 24 horas</span>
                <span class="marquee-item">✅ Recuperación rápida</span>
                <span class="marquee-item">🌾 Vuelve a vender en ASCC</span>
                <span class="marquee-item">🔑 Recupera tu cuenta ASCC</span>
                <span class="marquee-item">🛡️ Proceso 100% seguro</span>
                <span class="marquee-item">📧 Enlace válido 24 horas</span>
                <span class="marquee-item">✅ Recuperación rápida</span>
                <span class="marquee-item">🌾 Vuelve a vender en ASCC</span>
            </div>
        </div>
    </div>

    <!-- LAYOUT PRINCIPAL - 3 COLUMNAS -->
    <div class="page-wrapper">

        <!-- BANNER IZQUIERDO -->
        <aside class="banner-left">
            <div class="ad-carousel">
                <h3 class="ad-carousel-title">🔒 Seguridad</h3>
                <div class="carousel-icon">🛡️</div>
                <p class="carousel-text"><strong>Lorem ipsum dolor sit amet</strong>, consectetur adipiscing elit.
                    Praesent vel augue ac eros facilisis ultrices.</p>
                <button class="carousel-btn">Más Información</button>
            </div>
        </aside>

        <!-- FORMULARIO CENTRAL -->
        <main class="recovery-container">

            <!-- LOGO GRANDE Y DESTACADO -->
            <div class="logo-section">
                <div class="logo-wrapper">
                    <img src="/ascc/public/img/logo.png" alt="ASCC Logo">
                </div>
                <h1>ASCC</h1>
                <p>Marketplace Agropecuario de Colombia</p>
            </div>

            <h2 class="recovery-title">Recuperar Contraseña</h2>
            <p class="recovery-subtitle">
                Ingresa tu correo electrónico y te enviaremos un enlace para restablecer tu contraseña
            </p>

            <!-- Alertas -->
            <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ ¡Correo enviado! Revisa tu bandeja de entrada y sigue las instrucciones para restablecer tu contraseña
            </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error">
                <?php
                    if ($_GET['error'] === 'email_no_existe') {
                        echo "❌ No existe una cuenta asociada a este correo electrónico";
                    } elseif ($_GET['error'] === 'tabla_no_existe') {
                        echo "❌ Error de configuración: La tabla de recuperación no existe. Por favor contacta al administrador.";
                    } elseif ($_GET['error'] === 'bd_error') {
                        echo "❌ Error de base de datos. Por favor revisa los logs o contacta al administrador.";
                    } elseif ($_GET['error'] === 'email_vacio') {
                        echo "❌ Debes ingresar un correo electrónico";
                    } else {
                        echo "❌ Error al procesar la solicitud. Intenta nuevamente";
                    }
                    ?>
            </div>
            <?php endif; ?>

            <div class="alert alert-info">
                ℹ️ <strong>Nota:</strong> El enlace de recuperación será válido por 24 horas
            </div>

            <!-- Formulario -->
            <form action="/ascc/controllers/RecuperarController.php" method="POST">
                <input type="hidden" name="accion" value="solicitar_recuperacion">

                <div class="form-group">
                    <label for="email">📧 Correo Electrónico</label>
                    <input type="email" name="email" id="email" placeholder="correo@ejemplo.com" required autofocus>
                </div>

                <button type="submit" class="btn-primary">
                    🔑 Enviar Enlace de Recuperación
                </button>
            </form>

            <div class="divider">ó</div>

            <div class="login-link">
                ¿Recordaste tu contraseña? <a href="/ascc/views/auth/login.php">Inicia sesión aquí</a>
            </div>

            <div class="info-steps">
                <h3>📋 ¿Cómo funciona la recuperación?</h3>
                <ol>
                    <li>Ingresa tu correo electrónico registrado</li>
                    <li>Recibirás un email con un enlace seguro</li>
                    <li>Haz clic en el enlace (válido por 24 horas)</li>
                    <li>Crea tu nueva contraseña</li>
                    <li>¡Listo! Podrás acceder nuevamente</li>
                </ol>
            </div>

        </main>

        <!-- BANNER DERECHO -->
        <aside class="banner-right">
            <div class="info-card">
                <div class="info-card-icon">🔐</div>
                <h3>Proceso Seguro</h3>
                <p>Tu información está protegida con encriptación de alto nivel</p>
            </div>
        </aside>

    </div>

</body>

</html>