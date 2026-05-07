<?php

/**
 * ═══════════════════════════════════════════════════════════
 * ASCC — Modal Actualizar Datos
 * Ruta: C:\xampp\htdocs\ascc\modal-perfil.php
 *
 * Partial incluido en dashboard.php justo antes de </body>
 * Requiere: $usuario (array con datos del usuario en sesión)
 * ═══════════════════════════════════════════════════════════
 */

defined('ASCC') or die('Acceso directo no permitido.');
?>

<div id="modalActualizarDatos" class="agro-modal-backdrop" role="dialog" aria-modal="true"
    aria-labelledby="modalActualizarDatosTitle">

    <div class="agro-modal">

        <!-- ── HEADER ── -->
        <div class="agro-modal__header">
            <h2 class="agro-modal__title" id="modalActualizarDatosTitle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                    stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="4" />
                    <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                </svg>
                <?= t('update_profile_title') ?>
            </h2>
            <button class="agro-modal__close" aria-label="<?= t('close') ?>">&#x2715;</button>
        </div>

        <!-- ── PESTAÑAS ── -->
        <div class="agro-modal__tabs" role="tablist">
            <button class="agro-modal__tab is-active" role="tab"><?= t('tab_profile') ?></button>
            <button class="agro-modal__tab" role="tab"><?= t('tab_contact') ?></button>
            <button class="agro-modal__tab" role="tab"><?= t('tab_security') ?></button>
            <button class="agro-modal__tab" role="tab"><?= t('tab_notifications') ?></button>
        </div>

        <!-- ── FORMULARIO ── -->
        <form id="formActualizarDatos" novalidate>

            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

            <div class="agro-modal__body">

                <!-- ════ PANEL 1: PERFIL ════ -->
                <div class="agro-tab-panel is-active" id="panelPerfil">

                    <p class="agro-section-label"><?= t('section_photo') ?></p>
                    <div class="agro-avatar-row">
                        <div class="agro-avatar-circle agro-dash-avatar">
                            <?php if (!empty($usuario['foto_perfil'])): ?>
                            <img src="/ascc/public/<?= htmlspecialchars($usuario['foto_perfil']) ?>"
                                alt="<?= t('avatar_alt') ?>">
                            <?php else: ?>
                            <?= strtoupper(
                                    substr($usuario['nombre']   ?? 'U', 0, 1) .
                                        substr($usuario['apellido'] ?? '',  0, 1)
                                ) ?>
                            <?php endif; ?>
                        </div>
                        <div class="agro-avatar-info">
                            <p><?= t('avatar_hint') ?></p>
                            <button type="button" class="agro-btn-upload">
                                <?= t('avatar_upload_btn') ?>
                            </button>
                            <input type="file" id="inputAvatarFile" accept="image/jpeg,image/png,image/webp"
                                style="display:none">
                        </div>
                    </div>

                    <p class="agro-section-label"><?= t('section_personal_info') ?></p>
                    <div class="agro-field-grid">

                        <div class="agro-field">
                            <label for="inputNombre"><?= t('field_nombre') ?></label>
                            <input type="text" id="inputNombre" name="nombre"
                                value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" autocomplete="given-name"
                                maxlength="60">
                            <span class="agro-field__error" id="inputNombreError"></span>
                        </div>

                        <div class="agro-field">
                            <label for="inputApellido"><?= t('field_apellido') ?></label>
                            <input type="text" id="inputApellido" name="apellido"
                                value="<?= htmlspecialchars($usuario['apellido'] ?? '') ?>" autocomplete="family-name"
                                maxlength="60">
                            <span class="agro-field__error" id="inputApellidoError"></span>
                        </div>

                        <div class="agro-field">
                            <label for="inputTipoDoc"><?= t('field_tipo_doc') ?></label>
                            <select id="inputTipoDoc" name="tipo_documento">
                                <option value="CC"
                                    <?= ($usuario['tipo_documento'] ?? '') === 'CC'  ? 'selected' : '' ?>>
                                    <?= t('doc_cc') ?></option>
                                <option value="NIT"
                                    <?= ($usuario['tipo_documento'] ?? '') === 'NIT' ? 'selected' : '' ?>>
                                    <?= t('doc_nit') ?></option>
                                <option value="PP"
                                    <?= ($usuario['tipo_documento'] ?? '') === 'PP'  ? 'selected' : '' ?>>
                                    <?= t('doc_pp') ?></option>
                                <option value="CE"
                                    <?= ($usuario['tipo_documento'] ?? '') === 'CE'  ? 'selected' : '' ?>>
                                    <?= t('doc_ce') ?></option>
                            </select>
                        </div>

                        <div class="agro-field">
                            <label for="inputNumDoc"><?= t('field_num_doc') ?></label>
                            <input type="text" id="inputNumDoc" name="numero_documento"
                                value="<?= htmlspecialchars($usuario['cedula'] ?? '') ?>" maxlength="20"
                                inputmode="numeric">
                        </div>

                    </div>

                    <p class="agro-section-label" style="margin-top:16px;"><?= t('section_bio') ?></p>
                    <div class="agro-field">
                        <textarea id="inputBio" name="bio" maxlength="300"
                            placeholder="<?= t('bio_placeholder') ?>"><?= htmlspecialchars($usuario['bio'] ?? '') ?></textarea>
                    </div>

                    <p class="agro-section-label" style="margin-top:16px;"><?= t('section_role') ?></p>
                    <div class="agro-field">
                        <select id="inputRol" name="rol">
                            <option value="mixto" <?= ($usuario['rol'] ?? '') === 'mixto'     ? 'selected' : '' ?>>
                                <?= t('role_mixed') ?></option>
                            <option value="vendedor" <?= ($usuario['rol'] ?? '') === 'vendedor'  ? 'selected' : '' ?>>
                                <?= t('role_seller') ?></option>
                            <option value="comprador" <?= ($usuario['rol'] ?? '') === 'comprador' ? 'selected' : '' ?>>
                                <?= t('role_buyer') ?></option>
                        </select>
                    </div>

                </div><!-- /panelPerfil -->

                <!-- ════ PANEL 2: CONTACTO ════ -->
                <div class="agro-tab-panel" id="panelContacto">

                    <p class="agro-section-label"><?= t('section_email') ?></p>
                    <div class="agro-field">
                        <label for="inputEmail"><?= t('field_email') ?></label>
                        <input type="email" id="inputEmail" name="email"
                            value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                            data-email-original="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                            autocomplete="email" maxlength="120">
                        <span class="agro-field__error" id="inputEmailError"></span>
                    </div>
                    <?php if (!empty($usuario['email_verificado'])): ?>
                    <span class="agro-badge-verified">&#10003; <?= t('verified') ?></span>
                    <?php endif; ?>

                    <!-- 2FA — verificación de nuevo correo -->
                    <div id="seccionOtpEmail" class="agro-otp-section" style="display:none;">
                        <p class="agro-otp-info"><?= t('otp_email_info') ?></p>
                        <button type="button" id="btnEnviarOtpEmail" class="agro-btn-otp">
                            <?= t('otp_send_btn') ?>
                        </button>
                        <div id="inputOtpEmailWrapper" class="agro-otp-input-wrapper" style="display:none;">
                            <div class="agro-field">
                                <label for="inputOtpEmail"><?= t('otp_code_label') ?></label>
                                <input type="text" id="inputOtpEmail" name="otp_email" maxlength="6"
                                    inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code"
                                    placeholder="000000">
                                <span class="agro-otp-timer" id="otpEmailTimer"></span>
                                <span class="agro-field__error" id="inputOtpEmailError"></span>
                            </div>
                        </div>
                    </div>

                    <p class="agro-section-label" style="margin-top:16px;"><?= t('section_phone') ?></p>
                    <div class="agro-field-grid">

                        <div class="agro-field">
                            <label for="inputIndicativo"><?= t('field_indicativo') ?></label>
                            <select id="inputIndicativo" name="indicativo">
                                <?php
                                $indicativos = [
                                    '+57'  => '🇨🇴 +57 Colombia',
                                    '+1'   => '🇺🇸 +1 EE. UU. / Canadá',
                                    '+52'  => '🇲🇽 +52 México',
                                    '+54'  => '🇦🇷 +54 Argentina',
                                    '+55'  => '🇧🇷 +55 Brasil',
                                    '+56'  => '🇨🇱 +56 Chile',
                                    '+51'  => '🇵🇪 +51 Perú',
                                    '+58'  => '🇻🇪 +58 Venezuela',
                                    '+593' => '🇪🇨 +593 Ecuador',
                                    '+591' => '🇧🇴 +591 Bolivia',
                                    '+595' => '🇵🇾 +595 Paraguay',
                                    '+598' => '🇺🇾 +598 Uruguay',
                                    '+507' => '🇵🇦 +507 Panamá',
                                    '+506' => '🇨🇷 +506 Costa Rica',
                                    '+503' => '🇸🇻 +503 El Salvador',
                                    '+502' => '🇬🇹 +502 Guatemala',
                                    '+504' => '🇭🇳 +504 Honduras',
                                    '+505' => '🇳🇮 +505 Nicaragua',
                                    '+1809'=> '🇩🇴 +1809 Rep. Dominicana',
                                    '+53'  => '🇨🇺 +53 Cuba',
                                    '+34'  => '🇪🇸 +34 España',
                                    '+44'  => '🇬🇧 +44 Reino Unido',
                                    '+49'  => '🇩🇪 +49 Alemania',
                                    '+33'  => '🇫🇷 +33 Francia',
                                    '+39'  => '🇮🇹 +39 Italia',
                                    '+351' => '🇵🇹 +351 Portugal',
                                ];
                                $indActual = $usuario['indicativo'] ?? '+57';
                                foreach ($indicativos as $val => $label):
                                ?>
                                <option value="<?= $val ?>" <?= $indActual === $val ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <?php
                        /* Mostrar solo la parte numérica, sin el prefijo "CO-" que guardaba el registro antiguo */
                        $tel_raw     = $usuario['telefono'] ?? '';
                        $tel_display = preg_match('/^[A-Z+\d]+-(.+)$/', $tel_raw, $m) ? $m[1] : $tel_raw;
                        ?>
                        <div class="agro-field">
                            <label for="inputTelefono"><?= t('field_telefono') ?></label>
                            <input type="tel" id="inputTelefono" name="telefono"
                                value="<?= htmlspecialchars($tel_display) ?>"
                                placeholder="<?= t('phone_placeholder') ?>" maxlength="15" inputmode="tel">
                        </div>

                    </div>

                    <p class="agro-section-label" style="margin-top:16px;"><?= t('section_location') ?></p>

                    <!-- Botón GPS -->
                    <div class="agro-gps-row">
                        <button type="button" id="btnDetectarUbicacion" class="agro-btn-gps">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" width="15" height="15">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M12 2v3M12 19v3M2 12h3M19 12h3"/>
                                <circle cx="12" cy="12" r="9" opacity=".25"/>
                            </svg>
                            <span id="gpsLabel"><?= t('gps_btn') ?></span>
                        </button>
                        <span class="agro-gps-hint"><?= t('gps_hint') ?></span>
                    </div>

                    <div class="agro-field-grid">

                        <div class="agro-field">
                            <label for="inputDepartamento"><?= t('field_departamento') ?></label>
                            <select id="inputDepartamento" name="departamento">
                                <?php
                                $departamentos = [
                                    'Amazonas',
                                    'Antioquia',
                                    'Arauca',
                                    'Atlántico',
                                    'Bogotá D.C.',
                                    'Bolívar',
                                    'Boyacá',
                                    'Caldas',
                                    'Caquetá',
                                    'Casanare',
                                    'Cauca',
                                    'Cesar',
                                    'Chocó',
                                    'Córdoba',
                                    'Cundinamarca',
                                    'Guainía',
                                    'Guaviare',
                                    'Huila',
                                    'La Guajira',
                                    'Magdalena',
                                    'Meta',
                                    'Nariño',
                                    'Norte de Santander',
                                    'Putumayo',
                                    'Quindío',
                                    'Risaralda',
                                    'San Andrés y Providencia',
                                    'Santander',
                                    'Sucre',
                                    'Tolima',
                                    'Valle del Cauca',
                                    'Vaupés',
                                    'Vichada'
                                ];
                                foreach ($departamentos as $dep):
                                    $sel = ($usuario['departamento'] ?? '') === $dep ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($dep) ?>" <?= $sel ?>>
                                    <?= htmlspecialchars($dep) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="agro-field">
                            <label for="inputMunicipio"><?= t('field_municipio') ?></label>
                            <input type="text" id="inputMunicipio" name="municipio"
                                value="<?= htmlspecialchars($usuario['municipio'] ?? '') ?>"
                                placeholder="<?= t('municipio_placeholder') ?>" maxlength="80">
                        </div>

                        <div class="agro-field agro-field--span2">
                            <label for="inputVereda"><?= t('field_vereda') ?></label>
                            <input type="text" id="inputVereda" name="vereda"
                                value="<?= htmlspecialchars($usuario['vereda'] ?? '') ?>"
                                placeholder="<?= t('vereda_placeholder') ?>" maxlength="100">
                        </div>

                    </div>

                </div><!-- /panelContacto -->

                <!-- ════ PANEL 3: SEGURIDAD ════ -->
                <div class="agro-tab-panel" id="panelSeguridad">

                    <p class="agro-section-label"><?= t('section_password') ?></p>
                    <div class="agro-field-grid agro-field-grid--full">

                        <div class="agro-field">
                            <label for="inputCurrentPassword"><?= t('field_current_pass') ?></label>
                            <div class="agro-pass-wrap">
                                <input type="password" id="inputCurrentPassword" name="current_password"
                                    placeholder="••••••••" autocomplete="current-password">
                                <button type="button" class="agro-pass-toggle" aria-label="<?= t('show_password') ?>">
                                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                        </div>

                        <div class="agro-field">
                            <label for="inputNewPassword"><?= t('field_new_pass') ?></label>
                            <div class="agro-pass-wrap">
                                <input type="password" id="inputNewPassword" name="new_password"
                                    placeholder="••••••••" autocomplete="new-password">
                                <button type="button" class="agro-pass-toggle" aria-label="<?= t('show_password') ?>">
                                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <div class="agro-password-strength">
                                <div class="agro-password-strength__bar">
                                    <div class="agro-password-strength__segment"></div>
                                    <div class="agro-password-strength__segment"></div>
                                    <div class="agro-password-strength__segment"></div>
                                    <div class="agro-password-strength__segment"></div>
                                </div>
                                <span class="agro-password-strength__label"></span>
                            </div>
                            <span class="agro-field__error" id="inputNewPasswordError"></span>
                        </div>

                        <div class="agro-field">
                            <label for="inputConfirmPassword"><?= t('field_confirm_pass') ?></label>
                            <div class="agro-pass-wrap">
                                <input type="password" id="inputConfirmPassword" name="confirm_password"
                                    placeholder="••••••••" autocomplete="new-password">
                                <button type="button" class="agro-pass-toggle" aria-label="<?= t('show_password') ?>">
                                    <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16" aria-hidden="true" style="display:none"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                                </button>
                            </div>
                            <span class="agro-field__hint"><?= t('pass_hint') ?></span>
                            <span class="agro-field__error" id="inputConfirmPasswordError"></span>
                        </div>

                    </div>

                    <!-- 2FA — verificación para cambio de contraseña -->
                    <div id="seccionOtpPassword" class="agro-otp-section" style="display:none;">
                        <p class="agro-otp-info"><?= t('otp_password_info') ?></p>
                        <button type="button" id="btnEnviarOtpPassword" class="agro-btn-otp">
                            <?= t('otp_send_btn') ?>
                        </button>
                        <div id="inputOtpPasswordWrapper" class="agro-otp-input-wrapper" style="display:none;">
                            <div class="agro-field">
                                <label for="inputOtpPassword"><?= t('otp_code_label') ?></label>
                                <input type="text" id="inputOtpPassword" name="otp_password" maxlength="6"
                                    inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code"
                                    placeholder="000000">
                                <span class="agro-otp-timer" id="otpPasswordTimer"></span>
                                <span class="agro-field__error" id="inputOtpPasswordError"></span>
                            </div>
                        </div>
                    </div>

                </div><!-- /panelSeguridad -->

                <!-- ════ PANEL 4: NOTIFICACIONES ════ -->
                <div class="agro-tab-panel" id="panelNotificaciones">

                    <p class="agro-section-label"><?= t('section_notifications') ?></p>

                    <?php
                    $notifItems = [
                        ['key' => 'mensajes',    'title' => 'notif_messages',   'desc' => 'notif_messages_desc'],
                        ['key' => 'ventas',      'title' => 'notif_sales',      'desc' => 'notif_sales_desc'],
                        ['key' => 'visitas',     'title' => 'notif_visits',     'desc' => 'notif_visits_desc'],
                        ['key' => 'promociones', 'title' => 'notif_promotions', 'desc' => 'notif_promotions_desc'],
                    ];
                    foreach ($notifItems as $item):
                        $isOn = (bool)($usuario['notif_' . $item['key']] ?? 1);
                    ?>
                    <div class="agro-toggle-row">
                        <div class="agro-toggle-row__info">
                            <p><?= t($item['title']) ?></p>
                            <span><?= t($item['desc']) ?></span>
                        </div>
                        <button type="button" class="agro-toggle <?= $isOn ? 'is-on' : '' ?>"
                            data-notif-key="<?= $item['key'] ?>" aria-pressed="<?= $isOn ? 'true' : 'false' ?>">
                        </button>
                    </div>
                    <?php endforeach; ?>

                </div><!-- /panelNotificaciones -->

            </div><!-- /.agro-modal__body -->

            <!-- ── FOOTER ── -->
            <div class="agro-modal__footer">
                <button type="button" class="agro-btn-cancel"><?= t('cancel') ?></button>
                <button type="button" class="agro-btn-save">
                    <span class="agro-btn-save__spinner"></span>
                    <span class="agro-btn-save__text"><?= t('save_changes') ?></span>
                </button>
            </div>

        </form>

    </div><!-- /.agro-modal -->
</div><!-- /.agro-modal-backdrop -->

<!-- Traducciones para modal-perfil.js -->
<script>
var agroLang = {
    avatar_type_error:      '<?= t('avatar_type_error') ?>',
    avatar_size_error:      '<?= t('avatar_size_error') ?>',
    validation_required:    '<?= t('validation_required') ?>',
    validation_email:       '<?= t('validation_email') ?>',
    validation_pass_length: '<?= t('validation_pass_length') ?>',
    validation_pass_match:  '<?= t('validation_pass_match') ?>',
    profile_updated:        '<?= t('profile_updated') ?>',
    profile_error:          '<?= t('profile_error') ?>',
    network_error:          '<?= t('network_error') ?>',
    pass_weak:              '<?= t('pass_weak') ?>',
    pass_fair:              '<?= t('pass_fair') ?>',
    pass_good:              '<?= t('pass_good') ?>',
    pass_strong:            '<?= t('pass_strong') ?>',
    otp_send_btn:           '<?= t('otp_send_btn') ?>',
    otp_resend:             '<?= t('otp_resend') ?>',
    otp_sending:            '<?= t('otp_sending') ?>',
    otp_required:           '<?= t('otp_required') ?>',
    gps_btn:                '<?= t('gps_btn') ?>',
    gps_detecting:          '<?= t('gps_detecting') ?>',
    gps_success:            '<?= t('gps_success') ?>',
    gps_not_colombia:       '<?= t('gps_not_colombia') ?>',
    gps_error:              '<?= t('gps_error') ?>',
    gps_denied:             '<?= t('gps_denied') ?>',
    gps_not_supported:      '<?= t('gps_not_supported') ?>',
    show_password:          '<?= t('show_password') ?>',
    hide_password:          '<?= t('hide_password') ?>',
};
</script>