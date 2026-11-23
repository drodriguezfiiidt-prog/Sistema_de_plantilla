# GestioBioV3 · versión PHP

Port completo del proyecto Django **GestioBioV3** a **PHP 8 sin frameworks ni
Composer**: mismo catálogo, mismas reglas tarifarias, mismo documento
institucional de Biopago C.A., misma vista previa en vivo, mismo copiado al
portapapeles y misma exportación a Word (`.docx`).

El proyecto Django original permanece intacto en la carpeta superior
(`email_generator/`, `gestiobio/`, `templates/`, `static/`). Esta carpeta es una
implementación independiente que no comparte base de datos ni código con él.

---

## 1. Requisitos

- **PHP 8.1 o superior** (probado con PHP 8.5.13 NTS x64).
- Extensiones habilitadas en `php.ini`:
  - `pdo_sqlite` y `sqlite3` (base de datos)
  - `zip` (exportación `.docx`)
  - `mbstring` (textos con acentos)
  - `openssl` y `fileinfo` (recomendadas)
- Navegador moderno. No requiere Composer ni Node.

Ejemplo de `php.ini` mínimo (Windows):

```ini
extension_dir = "ext"
extension=pdo_sqlite
extension=sqlite3
extension=zip
extension=mbstring
extension=openssl
extension=fileinfo
```

Puede usarse el PHP del sistema, XAMPP/Laragon, o una copia portátil dentro de
`gestiobio-php\php\php.exe`.

---

## 2. Arranque rápido (Windows)

1. Doble clic en **`iniciar_web.bat`**.
   - Detecta PHP (`gestiobio-php\php`, `C:\Users\DR\php85`, XAMPP, PATH).
   - Crea la base SQLite y carga el catálogo si no existe.
   - Escucha en <http://127.0.0.1:8001/> y abre el navegador.
2. Para apagar el servidor: **`detener_web.bat`** (libera el puerto 8001).

Arranque manual equivalente:

```bat
cd gestiobio-php
php bin/init-db.php
php -S 127.0.0.1:8001 -t public router.php
```

Se eligió el puerto **8001** para no chocar con el Django de desarrollo (8000).

---

## 3. Usuarios de demostración

| Usuario   | Contraseña       | Acceso                                   |
|-----------|------------------|------------------------------------------|
| `admin`   | `GestioBio2026*` | Staff: generador, historial y **gestión** |
| `soporte` | `Soporte2026*`   | Generador, historial y vista previa       |

El panel `/gestion/` exige usuario **staff**. Sin sesión redirige a
`/login/?next=...`.

Cambiar una contraseña:

```bat
php bin\set-password.php admin "NuevaClave2026*"
```

---

## 4. Estructura

```
gestiobio-php/
├── public/                 DocumentRoot (index.php, assets, .htaccess)
│   ├── index.php           Front controller
│   └── assets/css|js       Copia de los estilos y JS del proyecto Django
├── app/
│   ├── Config.php          Configuración con notación de puntos
│   ├── Database.php        PDO SQLite + migración + datos iniciales
│   ├── Router.php          Rutas tipo Django: /historial/{id}/
│   ├── Controllers/        Generador, vista previa, docx, API, historial, gestión, login
│   ├── Models/             EquipmentFamily, ItemCatalog, DiagnosticTicket, User
│   ├── Services/           Formatting, Naming, Pricing, EmailBuilder, DocxExport, Pipeline
│   └── Support/            Auth, Csrf, Flash, View, Request, Clock, Seeder
├── resources/views/        Plantillas PHP (layout, generador, historial, gestión)
├── database/schema.sql     Esquema SQLite
├── storage/                Base de datos (gestiobio.sqlite) — no versionar
├── bin/                    init-db.php, set-password.php
├── tests/                  smoke-test.php (servicios) y http-check.ps1 (HTTP)
├── router.php              Router para el servidor embebido
├── routes.php              Tabla de rutas
├── config.php              Marca, textos y canales institucionales
├── iniciar_web.bat         Arranque en Windows
└── detener_web.bat         Apagado del servidor
```

---

## 5. Rutas (paridad con Django)

| Django (`email_generator/urls.py`)        | PHP                          |
|-------------------------------------------|------------------------------|
| `/` (generador)                            | `/`                          |
| `/vista-previa/`                           | `/vista-previa/`             |
| `/descargar/` (POST, `.docx`)              | `/descargar/`                |
| `/api/contexto/`                           | `/api/contexto/`             |
| `/historial/`                              | `/historial/`                |
| `/historial/<pk>/`                         | `/historial/{id}/`           |
| `/historial/<pk>/docx/`                    | `/historial/{id}/docx/`      |
| `/gestion/` y CRUD de artículos/familias   | `/gestion/...` (staff)       |
| `/admin/` (Django admin)                   | `/login/` + panel de gestión |

Endpoints JSON:

```json
GET /api/contexto/?serial=V10-000123
{
  "family": {"id": 1, "name": "Bio500/V10", "prefix": "V10", "allows_quotes": true, "channel": "..."},
  "items": [{"id": 1, "name": "Lector biométrico", "price": "80,00", "url": "/gestion/articulos/1/"}]
}
```

---

## 6. Paridad funcional con el proyecto Django

| Funcionalidad | Django | PHP |
|---|---|---|
| Autodetección de familia por prefijo más largo | `EquipmentFamily.detect()` | `Models\EquipmentFamily::detect()` |
| Artículos seleccionables (activos, no base, compatibles) | ORM | `ItemCatalog::forFamily()` |
| Partida base automática | `build_quote()` | `Pricing::buildQuote()` |
| Glosario institucional y control de tuteo | `services/naming.py` | `Services\Naming` |
| Documento por bloques (párrafos, títulos, viñetas, tabla) | `services/email_builder.py` | `Services\EmailBuilder` |
| Texto plano para portapapeles (tabla alineada) | `render_plain_text()` | `renderPlainText()` |
| `.docx` Calibri 11, títulos nivel 2, viñetas, tabla con bordes | `python-docx` | `Services\DocxExport` (Open XML + ZipArchive) |
| Validaciones y mensajes del formulario | `forms.py` | `Services\DiagnosticForm` |
| Historial con filtros `q`, `garantia`, `familia` | ORM | `DiagnosticTicket::search()` |
| Panel de gestión con precio en línea y habilitar/deshabilitar | vistas staff | `ManagementController` |
| Mensajes flash y CSRF | middleware Django | `Support\Flash`, `Support\Csrf` |
| Estilos e interacciones del front | `static/app.css|js` | copia adaptada en `public/assets` |

### Diferencias deliberadas

- **Base de datos propia**: SQLite en `storage/gestiobio.sqlite`; no se reutiliza
  `db.sqlite3` de Django (usuarios y hashes son distintos).
- **Precios en centavos** (`INTEGER`) para evitar errores de coma flotante; la
  capa `Formatting` los presenta como `1.234,56` o `12,50` en la tabla.
- **Autenticación propia** con `password_hash()`/`password_verify()`, sesión
  `gestiobio_php_session` y token CSRF por sesión; no hay Django admin: el panel
  `/gestion/` cubre el mantenimiento del catálogo.
- **Sin Composer**: no hay dependencias externas. El `.docx` se construye con la
  extensión `zip` y WordprocessingML estándar.
- El formulario usa `items[]` (array de PHP) y el JS se adaptó a ese nombre y al
  campo CSRF `_token`.

---

## 7. Pruebas

Servicios, tarifario, redacción, validaciones, persistencia y `.docx`:

```bat
php tests\smoke-test.php
```

Resultado esperado: `79 comprobaciones, 0 fallo(s)` y código de salida 0.
Comprueba, entre otras cosas, que el paquete `.docx` contiene las 7 partes Open
XML esperadas y que **todas** son XML bien formado.

Servidor HTTP en marcha (rutas, CSS/JS, API, vista previa, descarga, login):

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File tests\http-check.ps1
```

Resultado esperado: `HTTP OK: todas las comprobaciones pasaron`.

---

## 8. Configuración institucional

Todo lo editable está en `config.php` (equivalente a `settings.GESTIOBIO`):

- `app.brand_name`, `app.brand_subtitle`, `app.company`, `app.currency`
- `app.signature`, `app.platform_line`
- `app.administrative_channel` (canal por defecto)
- `app.channels` (retiro, encomiendas, correos de banco y ventas)
- `app.timezone` (`America/Caracas`) y `app.debug`
- `database.path` (ruta de la base SQLite)
- `session.*` y `auth.*` (login, redirecciones)

El catálogo inicial (3 familias y 6 artículos, incluida la partida base) vive en
`app/Support/Seeder.php` y se carga solo cuando la base está vacía. Para
reiniciar desde cero: borrar `storage/gestiobio.sqlite*` y ejecutar
`php bin/init-db.php`.

---

## 9. Despliegue

### Apache (XAMPP/WAMP)

1. Copiar el proyecto a `htdocs/gestiobio-php`.
2. Apuntar el `DocumentRoot` a `gestiobio-php/public` (recomendado) o dejar
   `public/.htaccess` con `mod_rewrite` activo.
3. Asegurar escritura en `storage/`.
4. En producción: `app.debug = false` en `config.php` y HTTPS obligatorio.

### Nginx

```nginx
server {
    listen 80;
    server_name gestiobio.local;
    root /ruta/gestiobio-php/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\. { deny all; }
}
```

Nunca publicar `app/`, `storage/`, `database/`, `bin/` ni `tests/`: solo
`public/` debe ser accesible por HTTP (las rutas ya están fuera del DocumentRoot).

---

## 10. Seguridad y mantenimiento

- Cambiar `admin`/`soporte` antes de publicar (`bin/set-password.php`).
- `app.debug = false` en producción para no exponer rutas ni mensajes internos.
- Servir siempre por HTTPS; las cookies de sesión son `HttpOnly` y `SameSite=Lax`.
- Todas las consultas usan sentencias preparadas y la salida HTML se escapa con
  `e()` (`htmlspecialchars`).
- Respaldos: copiar `storage/gestiobio.sqlite` (o usar `sqlite3 .backup`).
- El historial conserva el documento en texto plano (`document_body`) y
  reconstruye el HTML/Word a partir de los artículos guardados; si se cambia un
  precio después, los tickets antiguos se recalculan con el precio vigente, igual
  que en la versión Django.

