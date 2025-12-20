# Guía de Despliegue en Ferozo

Esta guía detalla los pasos para desplegar el sistema de gestión de turnos en un hosting Ferozo (o cualquier panel compatible con cPanel/MySQL).

## 1. Preparación de Archivos
El sistema ya está limpio de archivos temporales y versiones antiguas.
- Asegurate de subir todo el contenido de la carpeta del proyecto a la carpeta `public_html` (o subcarpeta deseada, ej: `/turnos`) usando el Administrador de Archivos de Ferozo o via FTP (FileZilla).
- **Importante**: No subas la carpeta `.git` ni los archivos `.env.example` si no es necesario (pero `.env.example` sirve de referencia).

## 2. Base de Datos
1. Ingresá a tu panel Ferozo > Base de Datos > MySQL.
2. Creá una nueva base de datos (ej: `ver00456_turnos`).
3. Creá un usuario de base de datos y asignale permisos totales sobre la base creada.
4. Abrí **phpMyAdmin**.
5. Seleccioná la base de datos creada.
6. Andá a la pestaña **Importar**.
7. Seleccioná el archivo `database.sql` que está en la raíz del proyecto (este archivo ya incluye todas las actualizaciones recientes).
8. Hacé clic en "Continuar" para crear todas las tablas y datos iniciales (incluye usuario admin por defecto).

## 3. Configuración
1. En el servidor, renombrá el archivo `.env.example` a `.env` (si no existe, crealo).
2. Editá el archivo `.env` con los datos de tu base de datos y correo:

```ini
DB_HOST=localhost
DB_NAME=ver00456_turnos  <-- Tu base
DB_USER=ver00456_usuario <-- Tu usuario
DB_PASS=tu_contraseña

# Configuración de Correo (SMTP)
SMTP_HOST=mail.tudomino.com.ar
SMTP_USER=no-reply@tudominio.com.ar
SMTP_PASS=tu_contraseña_email
SMTP_PORT=465
SMTP_SECURE=ssl
```

## 4. Enlace de Cuenta (Marca Blanca)
El sistema está diseñado para que la empresa configure su propia identidad.

1. Ingresá al sistema con el usuario Administrador por defecto:
   - **Usuario (CUIT)**: `20111111112`
   - **Contraseña**: `Admin123`
2. Una vez dentro, andá a la sección de **Configuración** (icono de engranaje) o usuarios.
3. Creá un nuevo usuario Administrativo con los datos reales de la empresa (su CUIT real y la contraseña que ellos elijan).
4. Cerrá sesión e ingresá con el nuevo usuario para verificar.
5. (Opcional) Podés borrar o cambiar la contraseña del usuario `20111111112` para mayor seguridad.

## 5. Verificación Final
- Probá el inicio de sesión.
- Verificá que el envío de correos funcione (intentá recuperar contraseña o crear una reserva de prueba).
- Si ves errores de permisos, asegurate que las carpetas tengan permisos `755` y los archivos `644`.

¡El sistema está listo para usar!
