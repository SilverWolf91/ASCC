<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - Aromas y Sabores de mi Campo Colombiano (ASCC). </title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/ascc-theme-CORRECTO.css">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/login.css">
</head>

<body>

    <!-- BANNER SUPERIOR - CINTA ANIMADA -->
    <div class="banner-top">
        <div class="banner-marquee">
            <div class="marquee-content">
                <span class="marquee-item">🌾 Bienvenido a ASCC</span>
                <span class="marquee-item">🚜 Conectamos productores de Colombia</span>
                <span class="marquee-item">💰 Compra y vende directo</span>
                <span class="marquee-item">📍 Alcance nacional</span>
                <span class="marquee-item">✅ Plataforma segura</span>
                <span class="marquee-item">🌾 Bienvenido a ASCC</span>
                <span class="marquee-item">🚜 Conectamos productores de Colombia</span>
                <span class="marquee-item">💰 Compra y vende directo</span>
                <span class="marquee-item">📍 Alcance nacional</span>
                <span class="marquee-item">✅ Plataforma segura</span>
            </div>
        </div>
    </div>

    <!-- LAYOUT PRINCIPAL - 3 COLUMNAS -->
    <div class="page-wrapper">

        <!-- BANNER IZQUIERDO - CARRUSEL -->
        <aside class="banner-left">
            <div class="ad-carousel">
                <h3 class="ad-carousel-title">📢 Destacado</h3>
                <div class="carousel-item">
                    <div class="carousel-icon">🌽</div>
                    <p class="carousel-text"><strong>Lorem ipsum dolor sit amet</strong>, consectetur adipiscing elit.
                        Praesent vel augue ac eros facilisis ultrices.</p>
                    <button class="carousel-btn">Más Información</button>
                </div>
            </div>

            <div class="ad-carousel">
                <div class="carousel-icon">🐔</div>
                <p class="carousel-text"><strong>Pellentesque habitant morbi</strong> tristique senectus et netus et
                    malesuada fames ac turpis.</p>
                <button class="carousel-btn">Ver Detalles</button>
            </div>
        </aside>

        <!-- FORMULARIO CENTRAL -->
        <main class="login-container">

            <!-- LOGO GRANDE Y DESTACADO -->
            <div class="logo-section">
                <div class="logo-wrapper">
                    <img src="/ascc/frontend/users/public/img/logo.png" alt="Aromas y Sabores de mi Campo Colombiano (ASCC) Logo">
                </div>
                <h1>
                    <h1>Aromas y Sabores de mi Campo Colombiano (ASCC)</h1>
                </h1>
                <p>Marketplace Agropecuario de Colombia</p>
            </div>

            <h2 class="login-title">Iniciar Sesión</h2>

            <!-- Alertas -->
            <?php if (isset($_GET['password_changed'])): ?>
                <div class="alert alert-success">
                    ✅ Contraseña cambiada exitosamente. Ya puedes iniciar sesión
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <?php if ($_GET['error'] === 'cuenta_bloqueada'): ?>
                    <div class="alert alert-error">
                        🚫 Tu cuenta ha sido suspendida. Contacta al administrador.
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        ❌ Credenciales inválidas. Verifica tu correo y contraseña
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="/ascc/backend/users/controllers/AuthController.php" method="POST">
                <input type="hidden" name="accion" value="login">

                <div class="form-group">
                    <label for="email">📧 Correo Electrónico</label>
                    <input type="email" name="email" id="email" placeholder="correo@ejemplo.com" required>
                </div>

                <div class="form-group">
                    <label for="pass">🔒 Contraseña</label>
                    <input type="password" name="password" id="pass" placeholder="••••••••" required>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="show-pass" onclick="togglePassword('pass')">
                    <label for="show-pass">Mostrar contraseña</label>
                </div>

                <button type="submit" class="btn-primary">
                    Entrar 🚀
                </button>
            </form>

            <div class="forgot-password">
                <a href="/ascc/frontend/users/views/auth/recuperar.php">¿Olvidaste tu contraseña? 🔑</a>
            </div>

            <div class="divider">ó</div>

            <div class="register-link">
                ¿No tienes cuenta? <a href="/ascc/frontend/users/views/auth/registro.php">Regístrate aquí</a>
            </div>

        </main>

        <!-- BANNER DERECHO - CARDS APILADOS -->
        <aside class="banner-right">
            <div class="stacked-cards">
                <div class="info-card">
                    <div class="info-card-icon">🥑</div>
                    <h3>Productos Frescos</h3>
                    <p>Directamente del campo a tu mesa</p>
                </div>

                <div class="info-card">
                    <div class="info-card-icon">🌿</div>
                    <h3>Precios Justos</h3>
                    <p>Sin intermediarios, mejores precios</p>
                </div>

                <div class="info-card">
                    <div class="info-card-icon">🚜</div>
                    <h3>Entregas Rápidas</h3>
                    <p>Logística eficiente en todo el país</p>
                </div>

                <div class="promo-card">
                    <h3>🎯 ¡PROMOCIÓN!</h3>
                    <p>Anuncia tu negocio aquí</p>
                    <button class="promo-btn">Contactar</button>
                </div>
            </div>
        </aside>

    </div>

    <!-- BANNER INFERIOR -->
    <div class="banner-bottom">
        <div class="banner-bottom-content">
            <h3>🎯 ¡Anuncia tu Negocio Aquí!</h3>
            <p><strong>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</strong> Integer posuere erat a ante
                venenatis dapibus posuere velit aliquet. Cras mattis consectetur purus sit amet fermentum.</p>
            <p style="margin-top: 15px; font-size: 0.95rem;">
                📞 Contacto: publicidad@ascc.com | 📱 WhatsApp: +57 300 123 4567
            </p>
        </div>
    </div>

    <script src="/ascc/frontend/users/public/js/login.js"></script>

</body>

</html>