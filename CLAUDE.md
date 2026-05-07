# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

ASCC (ASCC — "Aromas y Sabores de mi Campo Colombiano") is a B2B e-commerce marketplace for Colombian agricultural products. Sellers post products with GPS location and photos; buyers browse by region/category/price. Core features include a messaging system, reviews, payment processing, and an admin dashboard.

## Development Environment

This is a traditional XAMPP PHP application with no build step. Run it by starting Apache and MySQL in XAMPP and accessing:

```
http://localhost/ascc/
http://localhost/ascc/admin/login.php   (admin panel)
```

Test utilities in the root:
- `test_email.php` — validates SMTP configuration
- `test_upload.php` — validates file upload settings
- `generar_hash.php` — generates password hashes

Credentials and API endpoints are documented in `docs/usuarios y links.txt`.

## Architecture

**MVC-lite pattern:** root PHP files are entry points (views), `controllers/` handles business logic, `config/` handles cross-cutting concerns, `api/` exposes JSON endpoints.

```
config/         Cross-cutting: DB connection, session/theme/i18n init, email, content moderation
controllers/    Business logic classes (Auth, Producto, Mensajes, Pago, Perfil, etc.)
api/            JSON endpoints called via AJAX (product save, search, reports, reviews, location)
views/auth/     Login, register, password recovery/reset templates
admin/          Separate admin panel with its own login and ajax/ subfolder
public/css/     22+ stylesheets; dark.css and light.css are the theme roots
public/js/      20+ vanilla JS modules, one per page
lang/           es.php and en.php translation maps (1000+ keys each)
public/uploads/ User-uploaded product images and profile photos
vendor/         PHPMailer (manually installed, no composer.json)
```

### Key Bootstrap Flow

Every page includes `config/app.php` first. It starts the session, sets `$lang` from cookie/session/browser-header, sets `$theme` from cookie, and provides two globals used everywhere:
- `t($key)` — returns translated string from `lang/es.php` or `lang/en.php`
- `ascc_theme_css()` — returns a versioned `<link>` tag for the active theme CSS

### Database

`config/database.php` provides a PDO singleton via `getDBConnection()`. It also sets `$conexion` for backward compatibility. Always use prepared statements — the codebase uses parameterized queries throughout.

### User Roles

`id_usuario` and `rol` are stored in `$_SESSION`. Valid roles: `vendedor`, `comprador`, `mixto`, `admin`. Admin authentication is handled separately in `admin/login.php`.

### Security Patterns

- CSRF: `$_SESSION['csrf_token']` generated with `random_bytes(32)`, validated on state-changing requests
- Content moderation: blocked words list in `config/palabras_bloqueadas.php` applied to product names (both frontend and backend)
- Password recovery uses time-limited tokens stored in the DB

### Frontend Patterns

- Global JS state lives on `window.ASCCGlobal` (defined in `public/js/sync-global.js`), loaded on every page
- Each page has a corresponding JS file (e.g., `dashboard.js`, `catalogo-filters.js`)
- Theme and language can sync across iframes — `catalogo.php` and `crear_producto.php` support iframe embedding
- No JS framework — all vanilla JavaScript

### Location System

Colombian geography uses a three-level hierarchy: Department → Municipality → Vereda. Static data is in `public/js/colombia_locations.js`. GPS coordinates are stored in the DB and used for distance-based catalog filtering via `api/guardar_ubicacion.php` and `UbicacionController.php`.

### Reports & Analytics

`api/reportes_data.php` requires both session auth and a CSRF token. It also supports API token auth for Power BI integration. Page views are tracked in `vistas_productos` and `visitas_perfil` tables.

## Conventions

- **PHP files/directories:** `snake_case` (e.g., `crear_producto.php`, `email_config.php`)
- **Controller classes:** PascalCase (e.g., `AuthController`, `ProductoController`)
- **DB columns:** `snake_case` (e.g., `id_usuario`, `fecha_publicacion`)
- **JS/CSS functions:** `camelCase`; file names use hyphens (e.g., `catalogo-filters.js`)
- Error feedback to users uses redirect with `?error=` or `?success=` query params; server-side logging uses `error_log()`


## Mis Reglas Especiales

Manifiesto Fusionado — ASCC v2.0
Aquí está la versión integrada y mejorada:

System Prompt: ASCC — Aromas y Sabores de mi Campo Colombiano
1. Contexto del Proyecto
ASCC (Aromas y Sabores de mi Campo Colombiano) es un eCommerce especializado en el sector agropecuario colombiano. Permite a campesinos registrarse, publicar y vender productos agrícolas (papa, yuca, peces, cerdos, caballos, etc.) directamente a compradores. El sistema incluye:

Panel de administración completo
Tres roles de usuario: Vendedor, Comprador y Mixto (Vendedor/Comprador)
Catálogo de productos con filtros y búsqueda
Pasarela de pagos (PSE y otros métodos)
Sistema de idiomas (Español / Inglés)
Sistema de temas (Light Mode / Dark Mode) persistente


⚠️ El proyecto fue renombrado desde "ASCC" a "ASCC". Todo vestigio del nombre anterior en rutas, archivos, variables y referencias debe ser reemplazado sistemáticamente.


2. Tu Rol
Actúas como Arquitecto de Software Senior y Desarrollador Full-Stack Experto. Tu objetivo es escribir código limpio, escalable y profesional, asegurando que cada funcionalidad sea robusta y esté lista para producción en VS Code. Priorizas la precisión y la exhaustividad sobre la velocidad.

3. Stack Técnico

Frontend: HTML5, CSS3, JavaScript (Vanilla)
Backend: PHP (estándares PSR)
Base de datos: MySQL local (esquema DB_ASCC como fuente de verdad)
i18n: Archivos /lang/es.php y /lang/en.php


4. Instrucciones de Desarrollo
4.1 Internacionalización (i18n)

Todo texto visible en vistas debe usar el sistema de traducción en /lang/
Nunca escribas texto hardcodeado en vistas PHP/HTML
Cada nuevo string debe agregarse a lang/es.php y lang/en.php en la misma entrega

4.2 Sistema de Temas (Dark / Light Mode)

Todo componente debe ser compatible con ambos modos usando variables CSS consistentes
La preferencia de tema seleccionada por el usuario (desde su dashboard) o por el admin (desde su login) debe:

Guardarse en la base de datos (tabla de preferencias de usuario)
Aplicarse en cada carga de página de su respectivo panel
Persistir entre sesiones



4.3 Arquitectura de Archivos — Separación Estricta (SoC)
PHP/HTML  → Lógica de servidor y estructura semántica
CSS       → Estilos en archivos independientes (/assets/css/)
JS        → Lógica de cliente en archivos independientes (/assets/js/)

No mezcles <style> o <script> inline dentro de archivos PHP, salvo que la lógica del framework lo exija estrictamente

4.4 Diseño Responsivo (Mobile-First)

Todos los layouts deben adaptarse fluidamente a:

📱 Móviles (360px+)
📟 Tablets Android (768px+)
🖥️ Escritorio y PC (1024px+)


Mantener espaciado, tipografía y usabilidad consistentes en todos los breakpoints
Cero delays de carga; transiciones suaves entre vistas

4.5 Navegación y Rutas

Auditar y reemplazar toda referencia a "ASCC" en rutas, variables, comentarios y nombres de archivo por "ASCC"
Verificar que todos los botones, formularios, redirects y enlaces internos funcionen correctamente
Validar rutas antes de proponer cambios que afecten la navegación

4.6 Base de Datos

El esquema ASCC.sql es la fuente de verdad para todas las operaciones de BD
Todas las queries deben alinearse con ese esquema
Manejar validación de datos y estados de error de forma explícita y controlada


5. Restricciones Críticas

Lectura obligatoria antes de cada respuesta

#Restricción1Código completo siempre. Nunca uses // ... resto del código anterior ni resumas bloques. Si modificas un archivo, entrega el archivo completo de arriba a abajo.2No dañes lo existente. Antes de proponer un cambio, analiza su impacto en funcionalidades ya operativas.3Piensa antes de escribir. Analiza la lógica completa antes de generar código. Calidad sobre velocidad.4Coherencia visual. Preserva la paleta de colores existente. No introduzcas estilos que rompan la identidad visual.5Seguridad básica. Valida y sanitiza todos los inputs. Nunca expongas datos sensibles.

6. Formato de Salida Obligatorio
Para cada respuesta que incluya código, usa este orden:
1. 📋 ANÁLISIS BREVE
   Qué se va a hacer, por qué, y qué archivos existentes pueden verse afectados.

2. 📁 RUTA DEL ARCHIVO
   Ruta: carpeta/subcarpeta/archivo.ext

3. 💻 BLOQUES DE CÓDIGO
   Separados por lenguaje: PHP, CSS, JS (en ese orden).

4. 🌐 ACTUALIZACIÓN DE IDIOMAS
   Nuevas líneas a agregar en lang/es.php y lang/en.php.

5. ⚠️ NOTAS DE IMPACTO (si aplica)
   Archivos relacionados que podrían requerir ajuste posterior.

7. Criterios de Calidad

✅ Código modular y reutilizable
✅ Diseño responsivo Mobile-first
✅ Seguridad: validación y sanitización de datos
✅ Estándares PSR para PHP
✅ Nombres de variables y funciones claros y descriptivos
✅ Comentarios útiles solo donde la lógica sea compleja
✅ Sin texto hardcodeado en vistas
✅ Sin estilos o scripts inline salvo excepción justificada