<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Aromas y Sabores de mi Campo Colombiano (ASCC)</title>

    <link rel="icon" type="image/png" href="/ascc/frontend/users/public/img/logo.png">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/ascc-theme-CORRECTO.css">
    <link rel="stylesheet" href="/ascc/frontend/users/public/css/registro.css">
</head>

<body>

    <!-- BANNER SUPERIOR - CINTA ANIMADA -->
    <div class="banner-top">
        <div class="banner-marquee">
            <div class="marquee-content">
                <span class="marquee-item">🌾 Únete (ASCC)</span>
                <span class="marquee-item">💰 Vende sin intermediarios</span>
                <span class="marquee-item">📍 Alcance nacional</span>
                <span class="marquee-item">✅ Registro gratis</span>
                <span class="marquee-item">🚀 Más de 50K usuarios</span>
                <span class="marquee-item">🌾 Únete a Aromas y Sabores de mi Campo Colombiano (ASCC)</span>
                <span class="marquee-item">💰 Vende sin intermediarios</span>
                <span class="marquee-item">📍 Alcance nacional</span>
                <span class="marquee-item">✅ Registro gratis</span>
                <span class="marquee-item">🚀 Más de 50K usuarios</span>
            </div>
        </div>
    </div>

    <!-- LAYOUT PRINCIPAL - 3 COLUMNAS -->
    <div class="page-wrapper">

        <!-- BANNER IZQUIERDO - CARRUSEL -->
        <aside class="banner-left">
            <div class="ad-carousel">
                <h3 class="ad-carousel-title">🎯 Beneficios</h3>
                <div class="carousel-item">
                    <div class="carousel-icon">🚜</div>
                    <p class="carousel-text"><strong>Publica gratis</strong> tus productos agropecuarios y llega a miles
                        de compradores</p>
                    <button class="carousel-btn">Conocer más</button>
                </div>
            </div>
        </aside>

        <!-- FORMULARIO CENTRAL -->
        <main class="register-container">

            <!-- LOGO GRANDE Y DESTACADO -->
            <div class="logo-section">
                <div class="logo-wrapper">
                    <img src="/ascc/frontend/users/public/img/logo.png" alt="Aromas y Sabores de mi Campo Colombiano (ASCC) Logo">
                </div>
                <h1>Aromas y Sabores de mi Campo Colombiano (ASCC)</h1>
                <p>Marketplace Agropecuario de Colombia</p>
            </div>

            <h2 class="register-title">Crear Cuenta</h2>

            <!-- Alertas -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert alert-error">
                    <?php
                    if ($_GET['error'] === 'passwords_no_coinciden') {
                        echo "❌ Las contraseñas no coinciden";
                    } elseif ($_GET['error'] === 'password_corta') {
                        echo "❌ La contraseña debe tener al menos 6 caracteres";
                    } elseif ($_GET['error'] === 'email_existe') {
                        echo "❌ Este correo ya está registrado";
                    } elseif ($_GET['error'] === 'cedula_existe') {
                        echo "❌ Esta cédula ya está registrada";
                    } elseif ($_GET['error'] === 'rol_invalido') {
                        echo "❌ Debes seleccionar un tipo de cuenta";
                    } elseif ($_GET['error'] === 'email_no_enviado') {
                        echo "❌ No pudimos enviar el código de verificación. Verifica que el correo sea válido e intenta de nuevo.";
                    } elseif ($_GET['error'] === 'sesion_expirada') {
                        echo "⏱ Tu sesión expiró. Por favor regístrate nuevamente.";
                    } elseif ($_GET['error'] === 'token_expirado') {
                        echo "⏱ El código expiró. Por favor regístrate nuevamente.";
                    } elseif ($_GET['error'] === 'demasiados_intentos') {
                        echo "🚫 Demasiados intentos fallidos. Por favor regístrate nuevamente.";
                    } else {
                        echo "❌ Error en el registro. Por favor intenta nuevamente";
                    }
                    ?>
                </div>
            <?php endif; ?>

            <!-- Formulario -->
            <form action="/ascc/backend/users/controllers/AuthController.php" method="POST"
                onsubmit="return validarFormulario(event)">
                <input type="hidden" name="accion" value="registro">

                <div class="form-group">
                    <label for="nombre">👤 Nombre Completo</label>
                    <input type="text" name="nombre" id="nombre" placeholder="Juan Pérez García" required>
                    <div id="nombre-error" class="error-message"></div>
                    <div id="nombre-success" class="success-message"></div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cedula">🆔 Cédula</label>
                        <input type="text" name="cedula" id="cedula" placeholder="1234567890" required>
                        <div id="cedula-error" class="error-message"></div>
                        <div id="cedula-success" class="success-message"></div>
                    </div>

                    <div class="form-group">
                        <label for="telefono">📱 Teléfono</label>
                        <div class="phone-input-group">
                            <select name="codigo_pais" id="codigo-pais" required>
                                <option value="CO" selected>🇨🇴 +57</option>
                                <option value="US">🇺🇸 +1</option>
                                <option value="MX">🇲🇽 +52</option>
                                <option value="ES">🇪🇸 +34</option>
                                <option value="AR">🇦🇷 +54</option>
                                <option value="PE">🇵🇪 +51</option>
                                <option value="EC">🇪🇨 +593</option>
                                <option value="VE">🇻🇪 +58</option>
                            </select>
                            <input type="tel" name="telefono" id="telefono" placeholder="3001234567" required>
                        </div>
                        <div id="telefono-error" class="error-message"></div>
                        <div id="telefono-success" class="success-message"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">📧 Correo Electrónico</label>
                    <input type="email" name="email" id="email" placeholder="ejemplo@dominio.com" required>
                    <div id="email-error" class="error-message"></div>
                    <div id="email-success" class="success-message"></div>
                </div>

                <div class="form-group">
                    <label for="pass1">🔒 Contraseña</label>
                    <input type="password" name="password" id="pass1" placeholder="••••••••" required>
                    <div id="strength-indicator" class="password-strength"></div>
                    <div id="strength-text" class="password-strength-text"></div>
                    <div id="pass1-error" class="error-message"></div>
                    <div id="pass1-success" class="success-message"></div>
                </div>

                <div class="form-group">
                    <label for="pass2">🔒 Confirmar Contraseña</label>
                    <input type="password" name="password2" id="pass2" placeholder="••••••••" required>
                    <div id="pass2-error" class="error-message"></div>
                    <div id="pass2-success" class="success-message"></div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="show-pass" onclick="togglePasswords()">
                    <label for="show-pass">Mostrar contraseñas</label>
                </div>

                <!-- ══════════════════════════════════════════════
                     SELECTOR DE ROL — NUEVO BLOQUE
                     Campo oculto que recibe el valor seleccionado
                ══════════════════════════════════════════════ -->
                <div class="form-group">
                    <label>🧑‍🌾 ¿Cómo vas a usar (ASCC)?</label>
                    <input type="hidden" name="rol" id="rol-seleccionado" value="">

                    <div class="rol-selector">

                        <div class="rol-card" data-rol="vendedor" onclick="seleccionarRol('vendedor')">
                            <div class="rol-icon">🚜</div>
                            <div class="rol-nombre">Vendedor</div>
                            <div class="rol-desc">Quiero publicar y vender mis productos del campo</div>
                        </div>

                        <div class="rol-card" data-rol="comprador" onclick="seleccionarRol('comprador')">
                            <div class="rol-icon">🛒</div>
                            <div class="rol-nombre">Comprador</div>
                            <div class="rol-desc">Quiero buscar y comprar productos agropecuarios</div>
                        </div>

                        <div class="rol-card" data-rol="mixto" onclick="seleccionarRol('mixto')">
                            <div class="rol-icon">🤝</div>
                            <div class="rol-nombre">Comprador y Vendedor</div>
                            <div class="rol-desc">Quiero vender mis productos y también comprar de otros</div>
                        </div>

                    </div>
                    <div id="rol-error" class="error-message"></div>
                </div>
                <!-- ══════════════════════════════════════════════ -->

                <button type="submit" class="btn-primary">
                    Crear Cuenta 🚀
                </button>
            </form>

            <div class="divider">ó</div>

            <div class="login-link">
                ¿Ya tienes cuenta? <a href="/ascc/frontend/users/views/auth/login.php">Inicia sesión aquí</a>
            </div>

        </main>

        <!-- BANNER DERECHO - CARDS APILADOS -->
        <aside class="banner-right">
            <div class="stacked-cards">
                <div class="info-card">
                    <div class="info-card-icon">🌾</div>
                    <h3>Vende Directo</h3>
                    <p>Sin intermediarios. Más ganancias para ti</p>
                </div>

                <div class="info-card">
                    <div class="info-card-icon">📍</div>
                    <h3>Ubicación GPS</h3>
                    <p>Compradores te encuentran fácilmente</p>
                </div>

                <div class="info-card">
                    <div class="info-card-icon">💰</div>
                    <h3>Pagos Seguros</h3>
                    <p>Integración con Wompi y PSE</p>
                </div>

                <div class="promo-card">
                    <h3>✨ OFERTA ESPECIAL</h3>
                    <p>¡Primeros 3 meses GRATIS!</p>
                    <button class="promo-btn">Aprovechar</button>
                </div>
            </div>
        </aside>

    </div>

    <script src="/ascc/frontend/users/public/js/registro.js"></script>

</body>

</html>