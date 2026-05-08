/**
 * ASCC — reviews.js
 * Ruta: public/js/reviews.js
 *
 * Correcciones aplicadas:
 * - Guarda HTML original del formulario para restaurarlo al cambiar pestaña
 * - Botón editar inline para el autor de cada reseña
 * - Botón eliminar solo para el autor
 * - eliminarResena y guardarEdicion usan estado.tipo (no seccion.dataset.tipo)
 */

(function () {
    'use strict';

    const i18n = window.RV_I18N || {};

    const HINT_ESTRELLAS = {
        1: i18n.reviews_hint_1 || 'Muy malo',
        2: i18n.reviews_hint_2 || 'Malo',
        3: i18n.reviews_hint_3 || 'Regular',
        4: i18n.reviews_hint_4 || 'Bueno',
        5: i18n.reviews_hint_5 || 'Excelente',
    };

    // ── Init ─────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const seccion = document.getElementById('ascc-reviews');
        if (!seccion) return;

        const estado = {
            tipo:      seccion.dataset.tipo,
            id:        parseInt(seccion.dataset.id, 10),
            usuarioId: parseInt(seccion.dataset.usuarioId, 10),
            esAdmin:   seccion.dataset.esAdmin === '1',
            modoTabs:  seccion.dataset.modo === 'tabs',
            pagina:    1,
            cargando:  false,
            hayMas:    true,
        };

        const ui = {
            resumen:     document.getElementById('rv-resumen'),
            lista:       document.getElementById('rv-lista'),
            formWrapper: document.getElementById('rv-form-wrapper'),
            btnMas:      document.getElementById('rv-btn-mas'),
            btnEnviar:   document.getElementById('rv-btn-enviar'),
            inputTitulo: document.getElementById('rv-titulo'),
            textarea:    document.getElementById('rv-comentario'),
            charCount:   document.getElementById('rv-char-count'),
            mensaje:     document.getElementById('rv-mensaje'),
            starHint:    document.getElementById('rv-star-hint'),
            panel:       document.getElementById('rv-panel-principal'),
        };

        // ── CRÍTICO: guardar HTML original del formulario ─────
        // Esto permite restaurarlo al cambiar de pestaña sin F5
        ui._formHtmlOriginal = ui.formWrapper
            ? ui.formWrapper.outerHTML
            : null;

        // Carga inicial
        cargarResumen(estado, ui);
        cargarResenas(estado, ui, true);
        bindEventos(estado, ui);

        if (estado.modoTabs) {
            bindTabs(estado, ui, seccion);
        }
    });

    // ════════════════════════════════════════════════════════
    // PESTAÑAS
    // ════════════════════════════════════════════════════════

    function bindTabs(estado, ui, seccion) {
        const tabs = seccion.querySelectorAll('.rv-tab');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                if (tab.classList.contains('rv-tab--activo')) return;

                tabs.forEach(function (t) {
                    t.classList.remove('rv-tab--activo');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('rv-tab--activo');
                tab.setAttribute('aria-selected', 'true');

                estado.tipo   = tab.dataset.tipo;
                estado.id     = parseInt(tab.dataset.id, 10);
                estado.pagina = 1;
                estado.hayMas = true;

                // Restaurar formulario original antes de cargar nueva pestaña
                restaurarFormulario(ui);

                // Animación de transición del panel
                if (ui.panel) {
                    ui.panel.classList.remove('rv-panel-transition');
                    void ui.panel.offsetWidth;
                    ui.panel.classList.add('rv-panel-transition');
                }

                cargarResumen(estado, ui);
                cargarResenas(estado, ui, true);
            });
        });
    }

    // ════════════════════════════════════════════════════════
    // CARGA DE DATOS
    // ════════════════════════════════════════════════════════

    function cargarResumen(estado, ui) {
        if (ui.resumen) {
            ui.resumen.innerHTML =
                '<div class="rv-skeleton rv-skeleton--resumen"></div>';
        }

        fetch(
            '/ascc/api/reviews.php?action=summary' +
            '&tipo=' + encodeURIComponent(estado.tipo) +
            '&id='   + estado.id,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            renderResumen(data, ui.resumen);

            // Actualizar referencias al formWrapper por si fue restaurado
            ui.formWrapper = document.getElementById('rv-form-wrapper');
            ui.btnEnviar   = document.getElementById('rv-btn-enviar');
            ui.inputTitulo = document.getElementById('rv-titulo');
            ui.textarea    = document.getElementById('rv-comentario');
            ui.charCount   = document.getElementById('rv-char-count');
            ui.mensaje     = document.getElementById('rv-mensaje');
            ui.starHint    = document.getElementById('rv-star-hint');

            // Rebindear eventos del formulario restaurado
            bindEventosFormulario(estado, ui);

            gestionarFormulario(data.ya_reseno, ui.formWrapper);
        })
        .catch(function (err) {
            console.error('[ASCC Reviews] resumen:', err);
            if (ui.resumen) {
                ui.resumen.innerHTML =
                    '<p style="color:var(--rv-error);padding:.5rem 0">' +
                    (i18n.reviews_error_cargar || 'Error al cargar.') + '</p>';
            }
        });
    }

    function cargarResenas(estado, ui, esInicial) {
        if (estado.cargando) return;
        estado.cargando = true;

        if (esInicial && ui.lista) {
            ui.lista.innerHTML =
                '<div class="rv-skeleton rv-skeleton--card"></div>' +
                '<div class="rv-skeleton rv-skeleton--card"></div>' +
                '<div class="rv-skeleton rv-skeleton--card"></div>';
        }

        fetch(
            '/ascc/api/reviews.php?action=list' +
            '&tipo=' + encodeURIComponent(estado.tipo) +
            '&id='   + estado.id +
            '&page=' + estado.pagina,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) throw new Error(data.message);
            renderResenas(data.resenas, ui.lista, esInicial);
            estado.hayMas = data.hay_mas;
            actualizarBtnMas(ui.btnMas, data.hay_mas);
        })
        .catch(function (err) {
            console.error('[ASCC Reviews] lista:', err);
            if (ui.lista && esInicial) ui.lista.innerHTML = '';
        })
        .finally(function () {
            estado.cargando = false;
        });
    }

    // ════════════════════════════════════════════════════════
    // RENDER: RESUMEN
    // ════════════════════════════════════════════════════════

    function renderResumen(data, contenedor) {
        if (!contenedor) return;

        var promedio     = data.promedio;
        var total        = data.total;
        var distribucion = data.distribucion;

        if (total === 0) {
            contenedor.innerHTML =
                '<div class="rv-vacio">' +
                '<span class="rv-vacio-icono" aria-hidden="true">⭐</span>' +
                '<p>' + (i18n.reviews_sin_resenas || 'Aún no hay reseñas.') + '</p>' +
                '</div>';
            return;
        }

        var barras = [5, 4, 3, 2, 1].map(function (n) {
            var cnt = distribucion[n] || 0;
            var pct = total > 0 ? Math.round((cnt / total) * 100) : 0;
            return '<div class="rv-dist-fila">' +
                '<span class="rv-dist-label">' + n +
                '<span class="rv-dist-label-star" aria-hidden="true">★</span></span>' +
                '<div class="rv-dist-barra-wrap" role="progressbar"' +
                ' aria-valuenow="' + pct + '" aria-valuemin="0" aria-valuemax="100">' +
                '<div class="rv-dist-barra-fill" style="width:' + pct + '%"></div>' +
                '</div>' +
                '<span class="rv-dist-count">' + cnt + '</span>' +
                '</div>';
        }).join('');

        var textoTotal = total === 1
            ? '1 ' + (i18n.reviews_una    || 'reseña')
            : total + ' ' + (i18n.reviews_varias || 'reseñas');

        contenedor.innerHTML =
            '<div class="rv-score">' +
            '<span class="rv-score-numero" aria-label="' +
            promedio.toFixed(1) + ' ' + (i18n.reviews_de_5 || 'de 5') + '">' +
            promedio.toFixed(1) + '</span>' +
            '<div class="rv-score-estrellas" aria-hidden="true">' +
            estrellasProm(promedio) + '</div>' +
            '<span class="rv-score-total">' + textoTotal + '</span>' +
            '</div>' +
            '<div class="rv-distribucion" aria-label="' +
            (i18n.reviews_distribucion || 'Distribución') + '">' +
            barras + '</div>';
    }

    // ════════════════════════════════════════════════════════
    // RENDER: TARJETAS
    // ════════════════════════════════════════════════════════

    function renderResenas(resenas, contenedor, reemplazar) {
        if (reemplazar) contenedor.innerHTML = '';

        if (resenas.length === 0 && reemplazar) {
            contenedor.innerHTML =
                '<div class="rv-vacio">' +
                '<span class="rv-vacio-icono" aria-hidden="true">💬</span>' +
                '<p>' + (i18n.reviews_vacio || 'Sé el primero en dejar una reseña.') + '</p>' +
                '</div>';
            return;
        }

        var seccion      = document.getElementById('ascc-reviews');
        var usuarioActId = parseInt(seccion.dataset.usuarioId, 10);
        var esAdmin      = seccion.dataset.esAdmin === '1';
        var frag         = document.createDocumentFragment();

        resenas.forEach(function (r) {
            var card        = document.createElement('article');
            card.className  = 'rv-card';
            card.dataset.id = r.id_resena;

            var esAutor = parseInt(r.autor_id, 10) === usuarioActId;

            var urlFoto = '';
            if (r.autor_foto) {
                urlFoto = r.autor_foto.startsWith('http') ? r.autor_foto : '/ascc/public/' + r.autor_foto;
            }
            var avatarHtml = r.autor_foto
                ? '<img src="' + esc(urlFoto) + '"' +
                  ' alt="' + esc(r.autor_nombre) + '" class="rv-avatar" loading="lazy">'
                : '<div class="rv-avatar-letra" aria-hidden="true">' +
                  esc(r.autor_nombre.charAt(0).toUpperCase()) + '</div>';

            var fecha      = formatFecha(r.fecha_resena);
            var tituloHtml = r.titulo
                ? '<h4 class="rv-card-titulo">' + esc(r.titulo) + '</h4>'
                : '';

            // Botones de acción: solo para el propio autor o admin
            var accionesHtml = '';
            if (esAutor || esAdmin) {
                accionesHtml =
                    '<div class="rv-card-acciones">' +
                    // Editar — solo el propio autor
                    (esAutor
                        ? '<button class="rv-btn-editar"' +
                          ' data-resena-id="' + r.id_resena + '"' +
                          ' data-calificacion="' + r.calificacion + '"' +
                          ' data-titulo="'      + esc(r.titulo    || '') + '"' +
                          ' data-comentario="'  + esc(r.comentario)      + '"' +
                          ' aria-label="' + (i18n.reviews_editar || 'Editar') + '"' +
                          ' title="'      + (i18n.reviews_editar || 'Editar') + '">✏️</button>'
                        : '') +
                    // Eliminar — autor o admin
                    '<button class="rv-btn-eliminar"' +
                    ' data-resena-id="' + r.id_resena + '"' +
                    ' aria-label="' + (i18n.reviews_eliminar || 'Eliminar') + '"' +
                    ' title="'      + (i18n.reviews_eliminar || 'Eliminar') + '">🗑️</button>' +
                    '</div>';
            }

            card.innerHTML =
                '<div class="rv-card-head">' +
                avatarHtml +
                '<div class="rv-card-meta">' +
                '<div class="rv-card-nombre">' + esc(r.autor_nombre) + '</div>' +
                '<div class="rv-card-estrellas" aria-label="' +
                r.calificacion + ' ' + (i18n.reviews_estrellas || 'estrellas') + '">' +
                estrellasCard(parseInt(r.calificacion, 10)) + '</div>' +
                '</div>' +
                '<span class="rv-card-fecha">' + fecha + '</span>' +
                accionesHtml +
                '</div>' +
                tituloHtml +
                '<p class="rv-card-comentario" id="rv-comentario-texto-' + r.id_resena + '">' +
                esc(r.comentario) + '</p>';

            frag.appendChild(card);
        });

        contenedor.appendChild(frag);
    }

    // ════════════════════════════════════════════════════════
    // EVENTOS PRINCIPALES
    // ════════════════════════════════════════════════════════

    function bindEventos(estado, ui) {
        // Cargar más
        if (ui.btnMas) {
            ui.btnMas.addEventListener('click', function () {
                estado.pagina++;
                cargarResenas(estado, ui, false);
            });
        }

        bindEventosFormulario(estado, ui);

        // Delegación para eliminar y editar en la lista
        var lista = document.getElementById('rv-lista');
        if (lista) {
            lista.addEventListener('click', function (e) {
                var btnEliminar = e.target.closest('.rv-btn-eliminar');
                var btnEditar   = e.target.closest('.rv-btn-editar');

                if (btnEliminar) {
                    eliminarResena(
                        parseInt(btnEliminar.dataset.resenaId, 10),
                        estado,
                        ui
                    );
                }

                if (btnEditar) {
                    abrirEditorInline(btnEditar, estado, ui);
                }
            });
        }
    }

    // Eventos del formulario — separados para poder re-bindear
    // después de que el formulario sea restaurado al cambiar pestaña
    function bindEventosFormulario(estado, ui) {
        // Contador de caracteres
        var textarea  = document.getElementById('rv-comentario');
        var charCount = document.getElementById('rv-char-count');
        if (textarea && charCount) {
            textarea.addEventListener('input', function () {
                charCount.textContent = textarea.value.length + ' / 1000';
            });
        }

        // Hints de estrellas
        document.querySelectorAll('.rv-star-radio').forEach(function (radio) {
            radio.addEventListener('change', function () {
                var hint = document.getElementById('rv-star-hint');
                if (hint) hint.textContent = HINT_ESTRELLAS[radio.value] || '';
            });
        });

        // Botón enviar — clonar para eliminar listeners anteriores
        var btnEnviar = document.getElementById('rv-btn-enviar');
        if (btnEnviar) {
            var nuevo = btnEnviar.cloneNode(true);
            btnEnviar.parentNode.replaceChild(nuevo, btnEnviar);
            nuevo.addEventListener('click', function () {
                enviarResena(estado, ui);
            });
            ui.btnEnviar = nuevo;
        }
    }

    // ════════════════════════════════════════════════════════
    // FORMULARIO — ENVÍO
    // ════════════════════════════════════════════════════════

    function enviarResena(estado, ui) {
        // Actualizar referencias por si el DOM fue modificado
        ui.mensaje     = document.getElementById('rv-mensaje');
        ui.textarea    = document.getElementById('rv-comentario');
        ui.inputTitulo = document.getElementById('rv-titulo');

        mostrarMensaje(ui.mensaje, '', '');

        var radio        = document.querySelector('.rv-star-radio:checked');
        var calificacion = radio ? parseInt(radio.value, 10) : 0;

        if (!calificacion) {
            mostrarMensaje(ui.mensaje,
                i18n.reviews_err_sin_estrella || 'Selecciona una calificación.',
                'error');
            return;
        }

        var comentario = ui.textarea ? ui.textarea.value.trim() : '';
        if (!comentario) {
            mostrarMensaje(ui.mensaje,
                i18n.reviews_err_sin_comentario || 'El comentario es obligatorio.',
                'error');
            return;
        }

        var form = new FormData();
        form.append('action',       'create');
        form.append('tipo',         estado.tipo);
        form.append('id',           estado.id);
        form.append('calificacion', calificacion);
        form.append('titulo',       ui.inputTitulo ? ui.inputTitulo.value.trim() : '');
        form.append('comentario',   comentario);

        var btnEnviar = document.getElementById('rv-btn-enviar');
        setBtnCargando(btnEnviar, true);

        fetch('/ascc/api/reviews.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    form,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                mostrarMensaje(ui.mensaje,
                    i18n.reviews_exito || '¡Reseña publicada!',
                    'ok');
                // Marcar formulario como completado
                gestionarFormulario(true, document.getElementById('rv-form-wrapper'));
                // Recargar resumen y lista para mostrar la nueva reseña
                estado.pagina = 1;
                cargarResumen(estado, ui);
                cargarResenas(estado, ui, true);
            } else {
                var clave = 'reviews_err_' + (data.message || 'generico');
                mostrarMensaje(ui.mensaje,
                    i18n[clave] || i18n.reviews_err_generico || 'Error al publicar.',
                    'error');
                setBtnCargando(document.getElementById('rv-btn-enviar'), false);
            }
        })
        .catch(function () {
            mostrarMensaje(ui.mensaje,
                i18n.reviews_err_red || 'Error de conexión.',
                'error');
            setBtnCargando(document.getElementById('rv-btn-enviar'), false);
        });
    }

    // ════════════════════════════════════════════════════════
    // EDITOR INLINE
    // ════════════════════════════════════════════════════════

    function abrirEditorInline(btn, estado, ui) {
        var idResena     = parseInt(btn.dataset.resenaId, 10);
        var calActual    = parseInt(btn.dataset.calificacion, 10);
        var tituloActual = btn.dataset.titulo     || '';
        var comentActual = btn.dataset.comentario || '';

        var card = document.querySelector('.rv-card[data-id="' + idResena + '"]');
        if (!card) return;

        // Evitar abrir 2 editores a la vez
        if (card.querySelector('.rv-editor-inline')) return;

        // Ocultar texto actual mientras se edita
        var textoEl  = card.querySelector('.rv-card-comentario');
        var tituloEl = card.querySelector('.rv-card-titulo');
        if (textoEl)  textoEl.style.display  = 'none';
        if (tituloEl) tituloEl.style.display = 'none';

        // Construir estrellas del editor
        var estrellas = '';
        for (var s = 5; s >= 1; s--) {
            var checked = s === calActual ? 'checked' : '';
            estrellas +=
                '<input type="radio" name="rv_edit_cal_' + idResena + '"' +
                ' id="rv-edit-star-' + idResena + '-' + s + '"' +
                ' value="' + s + '" class="rv-star-radio" ' + checked + '>' +
                '<label for="rv-edit-star-' + idResena + '-' + s + '"' +
                ' class="rv-star-label" title="' + s + ' estrellas"' +
                ' aria-hidden="true">★</label>';
        }

        var editor       = document.createElement('div');
        editor.className = 'rv-editor-inline';
        editor.innerHTML =
            '<div class="rv-editor-stars">' +
            '<div class="rv-star-selector" style="direction:rtl;display:flex;gap:2px">' +
            estrellas + '</div>' +
            '</div>' +
            '<input type="text" class="rv-input rv-editor-titulo"' +
            ' placeholder="' + (i18n.reviews_titulo_placeholder || 'Título (opcional)') + '"' +
            ' maxlength="150" value="' + esc(tituloActual) + '"' +
            ' style="margin:.75rem 0 .5rem;display:block;width:100%;box-sizing:border-box">' +
            '<textarea class="rv-textarea rv-editor-comentario"' +
            ' rows="3" maxlength="1000"' +
            ' style="width:100%;box-sizing:border-box">' +
            esc(comentActual) + '</textarea>' +
            '<div class="rv-editor-mensaje" style="display:none"></div>' +
            '<div class="rv-editor-botones">' +
            '<button type="button" class="rv-btn-enviar rv-btn-guardar-edicion"' +
            ' data-resena-id="' + idResena + '"' +
            ' style="margin-top:.75rem;margin-right:.5rem">' +
            (i18n.reviews_guardar || 'Guardar cambios') + '</button>' +
            '<button type="button" class="rv-btn-mas rv-btn-cancelar-edicion"' +
            ' style="margin-top:.75rem;padding:.5rem 1rem">' +
            (i18n.reviews_cancelar_edicion || 'Cancelar') + '</button>' +
            '</div>';

        card.appendChild(editor);

        // Botón guardar — pasa estado para que use estado.tipo al enviar
        editor.querySelector('.rv-btn-guardar-edicion')
            .addEventListener('click', function () {
                guardarEdicion(idResena, editor, card, textoEl, tituloEl, estado, ui);
            });

        // Botón cancelar
        editor.querySelector('.rv-btn-cancelar-edicion')
            .addEventListener('click', function () {
                cerrarEditorInline(editor, card, textoEl, tituloEl);
            });
    }

    function guardarEdicion(idResena, editor, card, textoEl, tituloEl, estado, ui) {
        var radioChecked = editor.querySelector('.rv-star-radio:checked');
        var calificacion = radioChecked ? parseInt(radioChecked.value, 10) : 0;
        var titulo       = editor.querySelector('.rv-editor-titulo').value.trim();
        var comentario   = editor.querySelector('.rv-editor-comentario').value.trim();
        var mensajeEl    = editor.querySelector('.rv-editor-mensaje');

        if (!calificacion) {
            mostrarMensajeInline(mensajeEl,
                i18n.reviews_err_sin_estrella || 'Selecciona una calificación.',
                'error');
            return;
        }

        if (!comentario) {
            mostrarMensajeInline(mensajeEl,
                i18n.reviews_err_sin_comentario || 'El comentario es obligatorio.',
                'error');
            return;
        }

        var btnGuardar     = editor.querySelector('.rv-btn-guardar-edicion');
        btnGuardar.disabled    = true;
        btnGuardar.textContent = (i18n.loading || 'Guardando...');

        var form = new FormData();
        form.append('action',       'update');
        form.append('tipo',         estado.tipo);   // ← usa estado.tipo no seccion.dataset.tipo
        form.append('id_resena',    idResena);
        form.append('calificacion', calificacion);
        form.append('titulo',       titulo);
        form.append('comentario',   comentario);

        fetch('/ascc/api/reviews.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    form,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Actualizar la tarjeta visualmente sin recargar
                cerrarEditorInline(editor, card, textoEl, tituloEl);

                // Actualizar texto del comentario
                if (textoEl) textoEl.textContent = comentario;

                // Actualizar título
                if (titulo) {
                    if (tituloEl) {
                        tituloEl.textContent = titulo;
                    } else {
                        // Crear el título si no existía
                        var nuevoTitulo         = document.createElement('h4');
                        nuevoTitulo.className   = 'rv-card-titulo';
                        nuevoTitulo.textContent = titulo;
                        card.querySelector('.rv-card-head').after(nuevoTitulo);
                    }
                } else if (tituloEl) {
                    tituloEl.remove();
                }

                // Actualizar estrellas en la cabecera
                var estrellasEl = card.querySelector('.rv-card-estrellas');
                if (estrellasEl) estrellasEl.innerHTML = estrellasCard(calificacion);

                // Actualizar data-attributes del botón editar para próxima edición
                var btnEditar = card.querySelector('.rv-btn-editar');
                if (btnEditar) {
                    btnEditar.dataset.calificacion = calificacion;
                    btnEditar.dataset.titulo       = titulo;
                    btnEditar.dataset.comentario   = comentario;
                }

                // Refrescar resumen (el promedio puede haber cambiado)
                cargarResumen(estado, ui);

            } else {
                btnGuardar.disabled    = false;
                btnGuardar.textContent = (i18n.reviews_guardar || 'Guardar cambios');
                mostrarMensajeInline(mensajeEl,
                    i18n.reviews_err_generico || 'Error al guardar.',
                    'error');
            }
        })
        .catch(function () {
            btnGuardar.disabled    = false;
            btnGuardar.textContent = (i18n.reviews_guardar || 'Guardar cambios');
            mostrarMensajeInline(mensajeEl,
                i18n.reviews_err_red || 'Error de conexión.',
                'error');
        });
    }

    function cerrarEditorInline(editor, card, textoEl, tituloEl) {
        editor.remove();
        if (textoEl)  textoEl.style.display  = '';
        if (tituloEl) tituloEl.style.display = '';
    }

    // ════════════════════════════════════════════════════════
    // ELIMINAR
    // ════════════════════════════════════════════════════════

    function eliminarResena(idResena, estado, ui) {
        if (!confirm(i18n.reviews_confirmar_eliminar || '¿Eliminar esta reseña?')) return;

        var form = new FormData();
        form.append('action',    'delete');
        form.append('tipo',      estado.tipo);   // ← CORRECCIÓN: estado.tipo no seccion.dataset.tipo
        form.append('id_resena', idResena);

        // Encontrar la tarjeta ANTES del fetch para animarla de inmediato
        var card = document.querySelector('.rv-card[data-id="' + idResena + '"]');

        // Animar salida instantáneamente — feedback visual inmediato
        if (card) {
            card.style.transition    = 'opacity .3s ease, transform .3s ease';
            card.style.opacity       = '0';
            card.style.transform     = 'scale(.97)';
            card.style.pointerEvents = 'none';
        }

        fetch('/ascc/api/reviews.php', {
            method:  'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body:    form,
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                // Esperar animación y LUEGO actualizar DOM
                setTimeout(function () {
                    if (card && card.parentNode) card.remove();

                    // Restaurar formulario para que pueda reseñar de nuevo
                    restaurarFormulario(ui);

                    // Recargar resumen y lista
                    estado.pagina = 1;
                    cargarResumen(estado, ui);
                    cargarResenas(estado, ui, true);
                }, 350);

            } else {
                // Servidor rechazó — revertir animación
                if (card) {
                    card.style.opacity       = '1';
                    card.style.transform     = '';
                    card.style.pointerEvents = '';
                }
            }
        })
        .catch(function (err) {
            console.error('[ASCC Reviews] delete:', err);
            // Error de red — revertir animación
            if (card) {
                card.style.opacity       = '1';
                card.style.transform     = '';
                card.style.pointerEvents = '';
            }
        });
    }

    // ════════════════════════════════════════════════════════
    // HELPERS UI
    // ════════════════════════════════════════════════════════

    function gestionarFormulario(yaReseno, formWrapper) {
        if (!formWrapper) return;
        if (yaReseno) {
            formWrapper.innerHTML =
                '<div class="rv-ya-resenado" role="status">' +
                '<span aria-hidden="true">✅</span> ' +
                (i18n.reviews_ya_resenado || '¡Ya dejaste tu reseña!') +
                '</div>';
        }
    }

    function restaurarFormulario(ui) {
        // Restaura el HTML original del formulario guardado al inicio
        // Soluciona el bug de "ya reseñado" al cambiar de pestaña sin F5
        if (!ui._formHtmlOriginal) return;

        var wrapper = document.getElementById('rv-form-wrapper');
        if (!wrapper) return;

        // Reemplazar contenido actual con el formulario original limpio
        var temp         = document.createElement('div');
        temp.innerHTML   = ui._formHtmlOriginal;
        var nuevoWrapper = temp.firstElementChild;

        wrapper.parentNode.replaceChild(nuevoWrapper, wrapper);

        // Actualizar referencias
        ui.formWrapper = document.getElementById('rv-form-wrapper');
        ui.btnEnviar   = document.getElementById('rv-btn-enviar');
        ui.inputTitulo = document.getElementById('rv-titulo');
        ui.textarea    = document.getElementById('rv-comentario');
        ui.charCount   = document.getElementById('rv-char-count');
        ui.mensaje     = document.getElementById('rv-mensaje');
        ui.starHint    = document.getElementById('rv-star-hint');

        // Limpiar valores del formulario restaurado
        if (ui.textarea)    ui.textarea.value        = '';
        if (ui.inputTitulo) ui.inputTitulo.value     = '';
        if (ui.charCount)   ui.charCount.textContent = '0 / 1000';
        if (ui.starHint)    ui.starHint.textContent  = '';

        // Desmarcar estrellas
        document.querySelectorAll('.rv-star-radio').forEach(function (r) {
            r.checked = false;
        });
    }

    function mostrarMensaje(el, texto, tipo) {
        if (!el) return;
        el.className   = 'rv-mensaje';
        el.textContent = texto;
        if (tipo === 'ok')    el.classList.add('rv-mensaje--ok');
        if (tipo === 'error') el.classList.add('rv-mensaje--error');
    }

    function mostrarMensajeInline(el, texto, tipo) {
        if (!el) return;
        el.style.display      = 'block';
        el.style.padding      = '.5rem .75rem';
        el.style.borderRadius = 'var(--rv-radius-sm, 8px)';
        el.style.fontSize     = '.85rem';
        el.style.marginTop    = '.5rem';
        el.textContent        = texto;
        if (tipo === 'error') {
            el.style.background = 'rgba(220,38,38,.08)';
            el.style.border     = '1px solid var(--rv-error, #dc2626)';
            el.style.color      = 'var(--rv-error, #dc2626)';
        }
    }

    function setBtnCargando(btn, cargando) {
        if (!btn) return;
        btn.disabled = cargando;
        btn.classList.toggle('cargando', cargando);
    }

    function actualizarBtnMas(btn, mostrar) {
        if (!btn) return;
        btn.style.display = mostrar ? 'block' : 'none';
    }

    // ════════════════════════════════════════════════════════
    // HELPERS RENDER
    // ════════════════════════════════════════════════════════

    function estrellasProm(promedio) {
        var html = '';
        for (var i = 1; i <= 5; i++) {
            if (promedio >= i)
                html += '<span class="rv-score-star rv-score-star--llena" aria-hidden="true">★</span>';
            else if (promedio >= i - 0.5)
                html += '<span class="rv-score-star rv-score-star--media" aria-hidden="true">★</span>';
            else
                html += '<span class="rv-score-star" aria-hidden="true">★</span>';
        }
        return html;
    }

    function estrellasCard(n) {
        var html = '';
        for (var i = 1; i <= 5; i++) {
            var cls = i <= n ? ' rv-card-star--llena' : '';
            html += '<span class="rv-card-star' + cls + '" aria-hidden="true">★</span>';
        }
        return html;
    }

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g,  '&amp;')
            .replace(/</g,  '&lt;')
            .replace(/>/g,  '&gt;')
            .replace(/"/g,  '&quot;')
            .replace(/'/g,  '&#39;');
    }

    function formatFecha(str) {
        try {
            return new Date(str).toLocaleDateString(
                document.documentElement.lang === 'en' ? 'en-US' : 'es-CO',
                { year: 'numeric', month: 'short', day: 'numeric' }
            );
        } catch (e) { return str; }
    }

}());