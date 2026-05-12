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
                    } elseif ($_GET['error'] === 'politicas_requeridas') {
                        echo "❌ Debes aceptar las Políticas de Tratamiento de Datos Personales para registrarte.";
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

                <!-- ══════════════════════════════════════════════
                     CHECKBOX DE POLÍTICAS DE PRIVACIDAD
                ══════════════════════════════════════════════ -->
                <div class="form-group policies-group">
                    <label class="checkbox-label" for="acepta_politicas">
                        <input type="checkbox" name="acepta_politicas" id="acepta_politicas" required>
                        <span class="checkbox-text">
                            Autorizo de manera previa, expresa e informada a Aromas y Sabores de mi Campo Colombiano (ASCC) para que realice el tratamiento de mis datos personales de acuerdo con su <a href="#" onclick="abrirModalPoliticas(event)">[Ver Política de Tratamiento de Datos Personales]</a>. Entiendo que mis datos serán utilizados para la gestión de pedidos, facturación, envíos y atención al cliente. Como titular, conozco que puedo ejercer mis derechos de acceso, rectificación y supresión a través del correo: aromasysaboresdemicampocolombiano@gmail.com.
                        </span>
                    </label>
                    <div id="politicas-error" class="error-message"></div>
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

    <!-- MODAL DE POLÍTICAS DE PRIVACIDAD -->
    <div id="modalPoliticas" class="modal-overlay" style="display:none;">
        <div class="modal-content">
            <span class="close-modal" onclick="cerrarModalPoliticas()">&times;</span>
            <h2>POLÍTICA DE TRATAMIENTO DE DATOS PERSONALES - ASCC</h2>
            <div class="modal-body" style="max-height: 60vh; overflow-y: auto; text-align: left; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 20px; font-size: 14px; line-height: 1.6; color: #333;">
                <h3 style="color: var(--primary-color); margin-top: 0;">1. IDENTIFICACIÓN DEL RESPONSABLE</h3>
                <p><strong>Razón Social:</strong> Aromas y Sabores de mi Campo Colombiano (ASCC)<br>
                <strong>NIT:</strong> 1104069735-0<br>
                <strong>Domicilio:</strong> Mosquera, Cundinamarca.<br>
                <strong>Correo electrónico:</strong> aromasysaboresdemicampocolombiano@gmail.com</p>

                <h3 style="color: var(--primary-color);">2. FINALIDAD DEL TRATAMIENTO</h3>
                <p>Los datos personales que ASCC recolecta serán tratados con las siguientes finalidades:</p>
                <ul style="padding-left: 20px;">
                    <li>Procesar compras, gestionar el envío de productos y emitir facturas legales.</li>
                    <li>Establecer comunicación directa para informar sobre el estado de los pedidos o novedades del servicio.</li>
                    <li>Atender peticiones, quejas, reclamos y sugerencias (PQRS).</li>
                    <li>Cumplir con las obligaciones legales y tributarias ante las autoridades colombianas.</li>
                    <li>(Opcional) Enviar información comercial y promocional, siempre que el usuario no manifieste su oposición.</li>
                </ul>

                <h3 style="color: var(--primary-color);">3. DERECHOS DE LOS TITULARES</h3>
                <p>De acuerdo con la Ley 1581 de 2012, usted como titular tiene derecho a:</p>
                <ul style="padding-left: 20px;">
                    <li>Conocer, actualizar y rectificar sus datos personales.</li>
                    <li>Solicitar prueba de la autorización otorgada.</li>
                    <li>Ser informado respecto del uso que se le ha dado a sus datos.</li>
                    <li>Presentar quejas ante la Superintendencia de Industria y Comercio (SIC).</li>
                    <li>Revocar la autorización o solicitar la supresión del dato cuando no se respeten los principios, derechos y garantías constitucionales y legales.</li>
                </ul>

                <h3 style="color: var(--primary-color);">4. PROCEDIMIENTO PARA EL EJERCICIO DE DERECHOS</h3>
                <p>Usted puede dirigir cualquier solicitud relacionada con sus datos al correo electrónico: aromasysaboresdemicampocolombiano@gmail.com. Su solicitud será atendida en un término máximo de quince (15) días hábiles, según lo estipulado por la ley.</p>

                <h3 style="color: var(--primary-color);">5. SEGURIDAD DE LA INFORMACIÓN</h3>
                <p>ASCC cuenta con medidas técnicas y administrativas para garantizar la seguridad de sus datos y evitar su adulteración, pérdida, consulta o acceso no autorizado.</p>

                <h3 style="color: var(--primary-color);">6. VIGENCIA</h3>
                <p>La presente política rige a partir de su publicación y los datos permanecerán en nuestra base de datos durante el tiempo necesario para cumplir con la finalidad del tratamiento y las obligaciones legales contables.</p>
            </div>
            <div class="modal-footer" style="text-align: center;">
                <button type="button" class="btn-primary" onclick="cerrarModalYMarcar()">He leído y acepto</button>
            </div>
        </div>
    </div>

    <script src="/ascc/frontend/users/public/js/registro.js"></script>

</body>

</html>