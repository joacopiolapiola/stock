CREATE TABLE IF NOT EXISTS empleados (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    dni VARCHAR(20) NULL,
    nombre VARCHAR(120) NOT NULL,
    rol VARCHAR(100) NOT NULL,
    convenio VARCHAR(160) NULL,
    categoria VARCHAR(100) NULL,
    remuneracion_base DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uq_empleados_dni (dni),
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @agregar_dni_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados' AND COLUMN_NAME = 'dni') = 0,
    'ALTER TABLE empleados ADD COLUMN dni VARCHAR(20) NULL AFTER id',
    'SELECT 1'
);
PREPARE agregar_dni FROM @agregar_dni_sql;
EXECUTE agregar_dni;
DEALLOCATE PREPARE agregar_dni;

SET @agregar_indice_dni_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empleados' AND INDEX_NAME = 'uq_empleados_dni') = 0,
    'ALTER TABLE empleados ADD UNIQUE INDEX uq_empleados_dni (dni)',
    'SELECT 1'
);
PREPARE agregar_indice_dni FROM @agregar_indice_dni_sql;
EXECUTE agregar_indice_dni;
DEALLOCATE PREPARE agregar_indice_dni;

CREATE TABLE IF NOT EXISTS ausencias (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    dias DECIMAL(5,2) NOT NULL,
    tipo ENUM('inasistencia', 'licencia_paga', 'licencia_no_paga') NOT NULL,
    afecta_sueldo TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ausencias_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        ON DELETE CASCADE,
    INDEX idx_ausencias_empleado_fecha (empleado_id, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS presentismos (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empleado_id INT NOT NULL,
    fecha DATE NOT NULL,
    estado ENUM('presente', 'inasistencia', 'licencia_paga', 'licencia_no_paga') NOT NULL,
    afecta_sueldo TINYINT(1) NOT NULL DEFAULT 0,
    creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_presentismos_empleado
        FOREIGN KEY (empleado_id) REFERENCES empleados(id)
        ON DELETE CASCADE,
    UNIQUE KEY uq_presentismos_empleado_fecha (empleado_id, fecha),
    INDEX idx_presentismos_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS owners (
    nombre VARCHAR(20) NOT NULL PRIMARY KEY,
    pass VARCHAR(255) NOT NULL,
    rol ENUM('owner', 'rrhh', 'empleado', 'administrador') NOT NULL DEFAULT 'administrador',
    empleado_id INT NULL,
    UNIQUE KEY uq_owners_empleado (empleado_id),
    CONSTRAINT fk_owners_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @owners_rol_existia := (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND COLUMN_NAME = 'rol');
SET @agregar_rol_owner_sql := IF(
    @owners_rol_existia = 0,
    'ALTER TABLE owners ADD COLUMN rol ENUM(''owner'', ''rrhh'', ''empleado'', ''administrador'') NOT NULL DEFAULT ''administrador'' AFTER pass',
    'SELECT 1'
);
PREPARE agregar_rol_owner FROM @agregar_rol_owner_sql;
EXECUTE agregar_rol_owner;
DEALLOCATE PREPARE agregar_rol_owner;

SET @agregar_empleado_owner_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND COLUMN_NAME = 'empleado_id') = 0,
    'ALTER TABLE owners ADD COLUMN empleado_id INT NULL AFTER rol',
    'SELECT 1'
);
PREPARE agregar_empleado_owner FROM @agregar_empleado_owner_sql;
EXECUTE agregar_empleado_owner;
DEALLOCATE PREPARE agregar_empleado_owner;

SET @agregar_indice_owner_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND INDEX_NAME = 'uq_owners_empleado') = 0,
    'ALTER TABLE owners ADD UNIQUE INDEX uq_owners_empleado (empleado_id)',
    'SELECT 1'
);
PREPARE agregar_indice_owner FROM @agregar_indice_owner_sql;
EXECUTE agregar_indice_owner;
DEALLOCATE PREPARE agregar_indice_owner;

SET @agregar_fk_owner_sql := IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'owners' AND CONSTRAINT_NAME = 'fk_owners_empleado') = 0,
    'ALTER TABLE owners ADD CONSTRAINT fk_owners_empleado FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE agregar_fk_owner FROM @agregar_fk_owner_sql;
EXECUTE agregar_fk_owner;
DEALLOCATE PREPARE agregar_fk_owner;

UPDATE owners SET rol = 'owner' WHERE nombre = 'joaco' AND @owners_rol_existia = 0;