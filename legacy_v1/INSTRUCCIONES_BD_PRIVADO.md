# Instrucciones Privadas de Base de Datos

Este archivo contiene la información sensible y los pasos para conectar tu aplicación con la base de datos `c2031975_peiranB` que mostraste en la captura.

> [!IMPORTANT]
> **NO SUBAS ESTE ARCHIVO A GIT.**
> He agregado este archivo a `.gitignore` para evitar que se suba por error, pero tené cuidado.

## 1. Configuración de Credenciales

Abrí el archivo `config/config.php` y editá la sección de Base de Datos con los datos reales de tu hosting (Ferozo/DonWeb).

```php
// config/config.php

// ...
// 2. BASE DE DATOS
define('DB_HOST', 'localhost'); // Generalmente es localhost en Ferozo
define('DB_NAME', 'c2031975_peiranB'); // El nombre exacto de la captura
define('DB_USER', 'c2031975_peiranB'); // A MENOS que hayas creado otro usuario, suele ser igual al nombre de la DB o te lo da el panel.
define('DB_PASS', 'TU_CONTRASEÑA_ACA'); // La contraseña que creaste en el panel para ese usuario.
// ...
```

## 2. Estructura de la Base de Datos (Schema)

Como la base de datos está vacía ("No se han encontrado tablas"), tenés que crear la estructura.

1.  Entrá a **phpMyAdmin** desde tu panel de hosting.
2.  Seleccioná la base de datos `c2031975_peiranB` a la izquierda.
3.  Andá a la pestaña **SQL** (arriba).
4.  Copiá y pegá todo el siguiente código y dale a **Continuar**:

```sql
-- Tabla de Usuarios (Empresas/Clientes)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cuit VARCHAR(20) NOT NULL UNIQUE,
    company_name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'client', 'operator') DEFAULT 'client',
    status ENUM('active', 'rejected', 'pending') DEFAULT 'pending',
    email_verified TINYINT(1) DEFAULT 0,
    branch_id INT NULL, -- Para operadores asignados a una sucursal
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Sucursales
CREATE TABLE IF NOT EXISTS branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de Turnos (Appointments)
CREATE TABLE IF NOT EXISTS appointments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    branch_id INT NOT NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NOT NULL,
    vehicle_type VARCHAR(50) NOT NULL,
    quantity INT NOT NULL,
    needs_forklift TINYINT(1) DEFAULT 0,
    needs_helper TINYINT(1) DEFAULT 0,
    observations TEXT,
    driver_name VARCHAR(100),
    driver_dni VARCHAR(50),
    helper_name VARCHAR(100),
    helper_dni VARCHAR(50),
    outlook_event_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (branch_id) REFERENCES branches(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar Sucursales por defecto (Ejemplo)
INSERT INTO branches (name, address) VALUES 
('Planta Principal', 'Dirección Principal 123'),
('Depósito Secundario', 'Ruta 5 Km 20');

-- Crear un usuario Administrador por defecto
-- Usuario: 20123456789 (CUIT)
-- Password: admin (Hasheada)
INSERT INTO users (cuit, company_name, password_hash, role, status, email_verified) VALUES 
('20123456789', 'Administrador Sistema', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', 1);
```

## 3. Verificación

Una vez importado el SQL y configurado el `config.php`:
1.  Intentá loguearte con el usuario de prueba:
    *   **CUIT**: 20123456789
    *   **Pass**: admin
2.  Si entra, ¡ya estás conectado!
