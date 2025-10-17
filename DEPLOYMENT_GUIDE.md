# Guía de Despliegue a Producción

Esta guía contiene los pasos y consideraciones clave para llevar tu aplicación de gestión de condominios a un entorno de producción.

---

## 1. Checklist del Archivo `.env` para Producción

Antes de desplegar, asegúrate de que tu archivo `.env` en el servidor de producción tenga los siguientes valores configurados correctamente. **Nunca subas tu archivo `.env` de producción a un repositorio de código.**

| Variable                  | Valor de Ejemplo                  | Descripción                                                                                             |
| ------------------------- | --------------------------------- | ------------------------------------------------------------------------------------------------------- |
| `APP_NAME`                | `"Macroactiva"`                     | El nombre de tu aplicación.                                                                             |
| `APP_ENV`                 | `production`                      | **Crítico.** Cambia a `production` para desactivar los mensajes de error detallados y mejorar el rendimiento. |
| `APP_KEY`                 | `base64:...`                      | **Crítico.** Debe ser una clave única y segura. Genera una nueva con `php artisan key:generate`.        |
| `APP_DEBUG`               | `false`                           | **Crítico.** Desactiva el modo de depuración para evitar exponer información sensible.                  |
| `APP_URL`                 | `https://tu-dominio.com`          | La URL principal de tu aplicación.                                                                      |
| `DB_CONNECTION`           | `mysql`                           | El tipo de base de datos que usarás (ej. `mysql`, `pgsql`).                                             |
| `DB_HOST`                 | `127.0.0.1`                       | La IP del servidor de tu base de datos (puede ser `localhost`).                                         |
| `DB_PORT`                 | `3306`                            | El puerto de tu base de datos.                                                                          |
| `DB_DATABASE`             | `nombre_db_produccion`            | El nombre de tu base de datos de producción.                                                            |
| `DB_USERNAME`             | `usuario_db_produccion`           | El usuario para acceder a la base de datos.                                                             |
| `DB_PASSWORD`             | `contraseña_segura`               | La contraseña para el usuario de la base de datos.                                                      |
| `MAIL_MAILER`             | `smtp`                            | El driver de correo que usarás (ej. `smtp`, `ses`, `mailgun`).                                          |
| `MAIL_HOST`               | `smtp.mailtrap.io`                | El servidor de tu proveedor de correo.                                                                  |
| `MAIL_PORT`               | `2525`                            | El puerto del servidor de correo.                                                                       |
| `MAIL_USERNAME`           | `tu_usuario_smtp`                 | Tu usuario de autenticación de correo.                                                                  |
| `MAIL_PASSWORD`           | `tu_password_smtp`                | Tu contraseña de autenticación de correo.                                                               |
| `MAIL_ENCRYPTION`         | `tls`                             | El tipo de encriptación (`tls` o `ssl`).                                                                |
| `MAIL_FROM_ADDRESS`       | `"no-reply@tu-dominio.com"`       | La dirección de correo desde la que se enviarán los emails.                                             |
| `MAIL_FROM_NAME`          | `"${APP_NAME}"`                   | El nombre que aparecerá como remitente.                                                                 |
| `WEBPAY_PLUS_COMMERCE_CODE` | `tu_codigo_de_comercio_real`    | **Crítico.** El código de comercio que te entrega Transbank para producción.                             |
| `WEBPAY_PLUS_API_KEY`     | `tu_api_key_real`                 | **Crítico.** La API Key que te entrega Transbank para producción.                                       |
| `WEBPAY_PLUS_INTEGRATION_TYPE` | `LIVE`                       | **Crítico.** Cambia a `LIVE` para procesar pagos reales.                                                  |

---

## 2. Comandos de Optimización en el Servidor

Una vez que hayas subido tu código al servidor, conéctate por SSH y ejecuta los siguientes comandos en la raíz de tu proyecto para optimizar la aplicación:

```bash
# 1. Instalar dependencias de Composer (sin las de desarrollo)
composer install --optimize-autoloader --no-dev

# 2. Ejecutar las migraciones de la base de datos
php artisan migrate --force

# 3. Crear el enlace simbólico para el almacenamiento de archivos (ej. PDFs, imágenes)
php artisan storage:link

# 4. Limpiar cachés antiguas
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# 5. Crear cachés de configuración y rutas para un rendimiento óptimo
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 6. (Opcional) Si usas colas, reinicia el worker para que use el nuevo código
php artisan queue:restart
```

---

## 3. Guía General de Despliegue

### Opción A: Hosting Compartido (con cPanel)

1.  **Comprimir el Proyecto:** Comprime todos los archivos de tu proyecto en un archivo `.zip`, **excluyendo** la carpeta `node_modules` y el archivo `.env`.
2.  **Subir el Archivo:** Usa el "Administrador de Archivos" de cPanel para subir el `.zip` a la carpeta raíz de tu dominio (generalmente `public_html` o similar).
3.  **Extraer Archivos:** Extrae el contenido del `.zip`.
4.  **Configurar el Document Root:** En cPanel, busca la configuración de "Dominios" o "Dominios de Complemento" y asegúrate de que el "Document Root" (la raíz del documento) apunte a la carpeta `/public` de tu proyecto. Esto es **crucial por seguridad**.
5.  **Crear la Base de Datos:** Usa el asistente de "Bases de Datos MySQL" en cPanel para crear una nueva base de datos y un usuario. Asigna todos los privilegios al usuario sobre la base de datos.
6.  **Configurar `.env`:** Crea un nuevo archivo `.env` en el servidor (puedes copiar y pegar desde `env.example`) y llénalo con los datos de producción, incluyendo los de la base de datos que acabas de crear.
7.  **Ejecutar Comandos:** Si tu hosting compartido te da acceso a una terminal o SSH, ejecuta los comandos de optimización listados arriba. Si no, es posible que necesites contactar a soporte para que te ayuden.

### Opción B: Servidor Privado Virtual (VPS)

1.  **Conexión al Servidor:** Conéctate a tu VPS usando SSH.
2.  **Clonar el Repositorio:** Clona tu proyecto desde tu repositorio de Git (ej. GitHub, GitLab).
    ```bash
    git clone https://github.com/tu_usuario/tu_proyecto.git /var/www/tu_proyecto
    ```
3.  **Configurar el Servidor Web (Nginx/Apache):**
    *   Configura tu servidor web para que sirva el sitio.
    *   Asegúrate de que el "root" del servidor apunte a la carpeta `/public` de tu proyecto.
    *   Configura las reglas de reescritura (`rewrite`) para que todas las peticiones pasen por `index.php`.
4.  **Instalar Dependencias:** Navega a la carpeta del proyecto y ejecuta `composer install --optimize-autoloader --no-dev`.
5.  **Configurar `.env`:** Copia `.env.example` a `.env` (`cp .env.example .env`) y edítalo con tus credenciales de producción. No olvides generar una nueva `APP_KEY` (`php artisan key:generate`).
6.  **Establecer Permisos:** Asegúrate de que las carpetas `storage` y `bootstrap/cache` tengan los permisos de escritura correctos para el servidor web.
    ```bash
    sudo chown -R www-data:www-data /var/www/tu_proyecto
    sudo chmod -R 775 /var/www/tu_proyecto/storage
    sudo chmod -R 775 /var/www/tu_proyecto/bootstrap/cache
    ```
7.  **Ejecutar Comandos de Optimización:** Ejecuta la secuencia de comandos de optimización listada en la sección 2.
8.  **Configurar un Supervisor (Opcional pero Recomendado):** Si usas colas de trabajo, configura un supervisor como `Supervisor` o `systemd` para mantener los workers de la cola (`php artisan queue:work`) corriendo de forma permanente.
9.  **Configurar Certificado SSL:** Usa Let's Encrypt o un servicio similar para instalar un certificado SSL y asegurar tu sitio con HTTPS.