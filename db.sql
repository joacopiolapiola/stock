CREATE DATABASE IF NOT EXISTS viernes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE viernes;

CREATE TABLE IF NOT EXISTS productos (
    id INT NOT NULL PRIMARY KEY,
    precio INT NOT NULL,
    nombre VARCHAR(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cambios (
    id INT NOT NULL PRIMARY KEY,
    producto INT NOT NULL,
    tipo ENUM('compra', 'venta') NOT NULL,
    cantidad INT NOT NULL,
    subtotal INT NOT NULL,
    sujeto VARCHAR(20) NULL,
    dia TIMESTAMP NOT NULL UNIQUE DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cambios_producto FOREIGN KEY (producto) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cantidades (
    producto INT NOT NULL PRIMARY KEY,
    cantidad INT NOT NULL,
    CONSTRAINT fk_cantidades_producto FOREIGN KEY (producto) REFERENCES productos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS vars (
    ganancia DECIMAL(6,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS owners (
    nombre VARCHAR(20) NOT NULL PRIMARY KEY,
    pass VARCHAR(255) NOT NULL,
    rol ENUM('owner', 'rrhh', 'empleado', 'administrador') NOT NULL DEFAULT 'administrador',
    empleado_id INT NULL,
    UNIQUE KEY uq_owners_empleado (empleado_id),
    CONSTRAINT fk_owners_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS empleados (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(20) NULL,
    nombre VARCHAR(120) NOT NULL,
    rol VARCHAR(100) NOT NULL,
    convenio VARCHAR(160) NULL,
    categoria VARCHAR(100) NULL,
    remuneracion_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_empleados_dni (dni)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ausencias (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    dias DECIMAL(5,2) NOT NULL,
    tipo ENUM('inasistencia', 'licencia_paga', 'licencia_no_paga') NOT NULL,
    afecta_sueldo TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ausencias_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    INDEX idx_ausencias_empleado_fecha (empleado_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presentismos (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('presente', 'inasistencia', 'licencia_paga', 'licencia_no_paga') NOT NULL,
    afecta_sueldo TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presentismos_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE,
    UNIQUE KEY uq_presentismos_empleado_fecha (empleado_id, fecha),
    INDEX idx_presentismos_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO owners (nombre, pass, rol)
SELECT 'joaco', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'owner'
WHERE NOT EXISTS (SELECT 1 FROM owners WHERE nombre = 'joaco');