DROP DATABASE IF EXISTS viernes;
CREATE DATABASE IF NOT EXISTS viernes;
USE viernes;

CREATE TABLE productos (
id INT AUTO_INCREMENT PRIMARY KEY,
precio DECIMAL(12,2) NOT NULL DEFAULT 0,
nombre VARCHAR(60) NOT NULL
);

CREATE TABLE empleados (
id INT AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(60) NOT NULL,
apellido VARCHAR(60) NOT NULL,
cargo VARCHAR(60) NOT NULL,
salario DECIMAL(12,2) NOT NULL DEFAULT 0,
fecha_contratacion DATE NOT NULL,
telefono VARCHAR(30) DEFAULT NULL,
email VARCHAR(120) DEFAULT NULL,
estado ENUM('activo','inactivo','vacaciones') NOT NULL DEFAULT 'activo'
);

CREATE TABLE cambios (
id INT AUTO_INCREMENT PRIMARY KEY,
producto INT NOT NULL,
tipo ENUM('compra','venta') NOT NULL,
cantidad INT NOT NULL,
subtotal DECIMAL(12,2) NOT NULL,
sujeto VARCHAR(60) DEFAULT NULL,
empleado_id INT DEFAULT NULL,
dia TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (producto) REFERENCES productos(id) ON DELETE CASCADE,
FOREIGN KEY (empleado_id) REFERENCES empleados(id) ON DELETE SET NULL
);

CREATE TABLE cantidades (
producto INT PRIMARY KEY,
cantidad INT NOT NULL DEFAULT 0,
FOREIGN KEY (producto) REFERENCES productos(id) ON DELETE CASCADE
);

CREATE TABLE usuarios (
id INT AUTO_INCREMENT PRIMARY KEY,
nombre VARCHAR(50) NOT NULL UNIQUE,
nombre_completo VARCHAR(100) NOT NULL,
pass VARCHAR(255) NOT NULL,
rol ENUM('admin','gerencia','finanzas','ventas','operario') NOT NULL DEFAULT 'ventas',
activo TINYINT(1) NOT NULL DEFAULT 1,
creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE owners (
nombre VARCHAR(50) PRIMARY KEY,
pass VARCHAR(255) NOT NULL
);

CREATE TABLE vars (
ganancia DECIMAL(6,2) NOT NULL DEFAULT 0
);

CREATE TABLE gastos (
id INT AUTO_INCREMENT PRIMARY KEY,
concepto VARCHAR(100) NOT NULL,
tipo ENUM('gasto','impuesto') NOT NULL DEFAULT 'gasto',
monto DECIMAL(12,2) NOT NULL,
frecuencia ENUM('mensual','quincenal','semanal','trimestral','anual','personalizada') NOT NULL DEFAULT 'mensual',
cada_dias INT NOT NULL DEFAULT 0,
proximo_vencimiento DATE NOT NULL,
descripcion TEXT,
activo TINYINT(1) NOT NULL DEFAULT 1,
usuario_id INT DEFAULT NULL,
creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

INSERT INTO owners(nombre, pass) VALUES ('joaco', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km');
INSERT INTO usuarios(nombre, nombre_completo, pass, rol) VALUES
('joaco', 'Joaquín Administrador', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'admin'),
('gerencia', 'Gerente', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'gerencia'),
('finanzas', 'Finanzas', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'finanzas'),
('ventas', 'Ventas', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'ventas');

INSERT INTO vars(ganancia) VALUES (25.00);

INSERT INTO productos(nombre, precio) VALUES
('Camiseta', 120.00),
('Pantalón', 250.00),
('Zapatilla', 320.00);

INSERT INTO cantidades(producto, cantidad) VALUES
(1, 15),
(2, 8),
(3, 12);

INSERT INTO empleados(nombre, apellido, cargo, salario, fecha_contratacion, telefono, email, estado) VALUES
('Lucía', 'Pérez', 'Vendedora', 65000.00, '2024-02-15', '3512345678', 'lucia@empresa.com', 'activo'),
('Martín', 'Gómez', 'Supervisor', 82000.00, '2023-11-20', '3519876543', 'martin@empresa.com', 'activo');

INSERT INTO cambios(producto, tipo, cantidad, subtotal, sujeto, empleado_id, dia) VALUES
(1, 'venta', 4, 480.00, 'Cliente A', 1, '2025-01-15 10:00:00'),
(2, 'venta', 2, 500.00, 'Cliente B', 2, '2025-01-20 12:00:00'),
(1, 'compra', 10, 900.00, 'Proveedor X', NULL, '2025-01-11 09:00:00'),
(3, 'venta', 3, 960.00, 'Cliente C', 1, '2025-02-10 15:30:00');

INSERT INTO gastos(concepto, tipo, monto, frecuencia, cada_dias, proximo_vencimiento, descripcion, activo, usuario_id) VALUES
('Alquiler local', 'gasto', 180000.00, 'mensual', 0, '2025-10-05', 'Costo mensual del local comercial.', 1, 1),
('IVA mensual', 'impuesto', 42000.00, 'mensual', 0, '2025-10-10', 'Impuesto mensual del negocio.', 1, 2),
('Servicio eléctrico', 'gasto', 32000.00, 'mensual', 0, '2025-10-15', 'Factura del servicio eléctrico.', 1, 3);