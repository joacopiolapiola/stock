-- SOLO DESARROLLO: crea cuentas demo con contrasena 123. No importar en produccion.
USE viernes;

START TRANSACTION;

INSERT IGNORE INTO owners (nombre, pass, rol) VALUES
	('ejemplo_admin_1', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'administrador'),
	('ejemplo_admin_2', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'administrador'),
	('ejemplo_admin_3', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'administrador'),
	('ejemplo_admin_4', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'administrador'),
	('ejemplo_admin_5', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'administrador'),
	('ejemplo_rrhh', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'rrhh');

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM productos);
INSERT INTO productos (id, precio, nombre)
SELECT @siguiente_id, 12000, 'EJEMPLO Remera'
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'EJEMPLO Remera');
SET @producto_1 := (SELECT id FROM productos WHERE nombre = 'EJEMPLO Remera' LIMIT 1);

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM productos);
INSERT INTO productos (id, precio, nombre)
SELECT @siguiente_id, 35000, 'EJEMPLO Jean'
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'EJEMPLO Jean');
SET @producto_2 := (SELECT id FROM productos WHERE nombre = 'EJEMPLO Jean' LIMIT 1);

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM productos);
INSERT INTO productos (id, precio, nombre)
SELECT @siguiente_id, 28000, 'EJEMPLO Buzo'
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'EJEMPLO Buzo');
SET @producto_3 := (SELECT id FROM productos WHERE nombre = 'EJEMPLO Buzo' LIMIT 1);

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM productos);
INSERT INTO productos (id, precio, nombre)
SELECT @siguiente_id, 62000, 'EJEMPLO Campera'
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'EJEMPLO Campera');
SET @producto_4 := (SELECT id FROM productos WHERE nombre = 'EJEMPLO Campera' LIMIT 1);

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM productos);
INSERT INTO productos (id, precio, nombre)
SELECT @siguiente_id, 48000, 'EJEMPLO Zapatilla'
WHERE NOT EXISTS (SELECT 1 FROM productos WHERE nombre = 'EJEMPLO Zapatilla');
SET @producto_5 := (SELECT id FROM productos WHERE nombre = 'EJEMPLO Zapatilla' LIMIT 1);

INSERT INTO cantidades (producto, cantidad) SELECT @producto_1, 18 WHERE NOT EXISTS (SELECT 1 FROM cantidades WHERE producto = @producto_1);
INSERT INTO cantidades (producto, cantidad) SELECT @producto_2, 12 WHERE NOT EXISTS (SELECT 1 FROM cantidades WHERE producto = @producto_2);
INSERT INTO cantidades (producto, cantidad) SELECT @producto_3, 7 WHERE NOT EXISTS (SELECT 1 FROM cantidades WHERE producto = @producto_3);
INSERT INTO cantidades (producto, cantidad) SELECT @producto_4, 14 WHERE NOT EXISTS (SELECT 1 FROM cantidades WHERE producto = @producto_4);
INSERT INTO cantidades (producto, cantidad) SELECT @producto_5, 8 WHERE NOT EXISTS (SELECT 1 FROM cantidades WHERE producto = @producto_5);

INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
SELECT '99010001', 'EJEMPLO Ana Perez', 'Vendedora', 'Demo textil', 'Vendedor A', 450000.00
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE dni = '99010001');
SET @empleado_1 := (SELECT id FROM empleados WHERE dni = '99010001' LIMIT 1);

INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
SELECT '99010002', 'EJEMPLO Luis Gomez', 'Encargado', 'Demo textil', 'Encargado', 520000.00
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE dni = '99010002');
SET @empleado_2 := (SELECT id FROM empleados WHERE dni = '99010002' LIMIT 1);

INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
SELECT '99010003', 'EJEMPLO Mica Ruiz', 'Vendedora', 'Demo textil', 'Vendedor B', 430000.00
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE dni = '99010003');
SET @empleado_3 := (SELECT id FROM empleados WHERE dni = '99010003' LIMIT 1);

INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
SELECT '99010004', 'EJEMPLO Carlos Diaz', 'Deposito', 'Demo textil', 'Operario', 410000.00
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE dni = '99010004');
SET @empleado_4 := (SELECT id FROM empleados WHERE dni = '99010004' LIMIT 1);

INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
SELECT '99010005', 'EJEMPLO Sofia Luna', 'Administrativa', 'Demo textil', 'Administrativo', 480000.00
WHERE NOT EXISTS (SELECT 1 FROM empleados WHERE dni = '99010005');
SET @empleado_5 := (SELECT id FROM empleados WHERE dni = '99010005' LIMIT 1);

INSERT INTO owners (nombre, pass, rol, empleado_id) VALUES
	('empleado_demo_1', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'empleado', @empleado_1),
	('empleado_demo_2', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'empleado', @empleado_2),
	('empleado_demo_3', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'empleado', @empleado_3),
	('empleado_demo_4', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'empleado', @empleado_4),
	('empleado_demo_5', '$2y$10$d3OEWQr0cixAcM1rL/sOROTM8p7N9F8hhfbdV8zJ852sFYfCX42Km', 'empleado', @empleado_5)
ON DUPLICATE KEY UPDATE rol = VALUES(rol), empleado_id = VALUES(empleado_id);

SET @fecha_demo := CAST(DATE_FORMAT(CURRENT_DATE, '%Y-%m-01') AS DATE);
INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
SELECT @empleado_1, @fecha_demo, 'presente', 0 WHERE NOT EXISTS (SELECT 1 FROM presentismos WHERE empleado_id = @empleado_1 AND fecha = @fecha_demo);
INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
SELECT @empleado_2, DATE_ADD(@fecha_demo, INTERVAL 1 DAY), 'inasistencia', 1 WHERE NOT EXISTS (SELECT 1 FROM presentismos WHERE empleado_id = @empleado_2 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 1 DAY));
INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
SELECT @empleado_3, DATE_ADD(@fecha_demo, INTERVAL 2 DAY), 'licencia_paga', 0 WHERE NOT EXISTS (SELECT 1 FROM presentismos WHERE empleado_id = @empleado_3 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 2 DAY));
INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
SELECT @empleado_4, DATE_ADD(@fecha_demo, INTERVAL 3 DAY), 'licencia_no_paga', 1 WHERE NOT EXISTS (SELECT 1 FROM presentismos WHERE empleado_id = @empleado_4 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 3 DAY));
INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
SELECT @empleado_5, DATE_ADD(@fecha_demo, INTERVAL 4 DAY), 'presente', 0 WHERE NOT EXISTS (SELECT 1 FROM presentismos WHERE empleado_id = @empleado_5 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 4 DAY));

INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
SELECT @empleado_2, DATE_ADD(@fecha_demo, INTERVAL 1 DAY), 1.00, 'inasistencia', 1
WHERE NOT EXISTS (SELECT 1 FROM ausencias WHERE empleado_id = @empleado_2 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 1 DAY) AND tipo = 'inasistencia');
INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
SELECT @empleado_3, DATE_ADD(@fecha_demo, INTERVAL 2 DAY), 1.00, 'licencia_paga', 0
WHERE NOT EXISTS (SELECT 1 FROM ausencias WHERE empleado_id = @empleado_3 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 2 DAY) AND tipo = 'licencia_paga');
INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
SELECT @empleado_4, DATE_ADD(@fecha_demo, INTERVAL 3 DAY), 1.00, 'licencia_no_paga', 1
WHERE NOT EXISTS (SELECT 1 FROM ausencias WHERE empleado_id = @empleado_4 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 3 DAY) AND tipo = 'licencia_no_paga');
INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
SELECT @empleado_5, DATE_ADD(@fecha_demo, INTERVAL 5 DAY), 0.50, 'inasistencia', 1
WHERE NOT EXISTS (SELECT 1 FROM ausencias WHERE empleado_id = @empleado_5 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 5 DAY) AND tipo = 'inasistencia' AND dias = 0.50);
INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
SELECT @empleado_5, DATE_ADD(@fecha_demo, INTERVAL 6 DAY), 0.50, 'inasistencia', 1
WHERE NOT EXISTS (SELECT 1 FROM ausencias WHERE empleado_id = @empleado_5 AND fecha = DATE_ADD(@fecha_demo, INTERVAL 6 DAY) AND tipo = 'inasistencia' AND dias = 0.50);

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM cambios);
SET @siguiente_dia := (SELECT COALESCE(MAX(dia), CURRENT_TIMESTAMP - INTERVAL 1 SECOND) + INTERVAL 1 SECOND FROM cambios);
INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto, dia)
SELECT @siguiente_id, @producto_1, 'venta', 2, 24000, 'EJEMPLO Cliente 1', @siguiente_dia
WHERE NOT EXISTS (SELECT 1 FROM cambios WHERE producto = @producto_1 AND sujeto = 'EJEMPLO Cliente 1');

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM cambios);
SET @siguiente_dia := (SELECT COALESCE(MAX(dia), CURRENT_TIMESTAMP - INTERVAL 1 SECOND) + INTERVAL 1 SECOND FROM cambios);
INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto, dia)
SELECT @siguiente_id, @producto_2, 'compra', 8, 72000, 'EJEMPLO Proveedor 2', @siguiente_dia
WHERE NOT EXISTS (SELECT 1 FROM cambios WHERE producto = @producto_2 AND sujeto = 'EJEMPLO Proveedor 2');

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM cambios);
SET @siguiente_dia := (SELECT COALESCE(MAX(dia), CURRENT_TIMESTAMP - INTERVAL 1 SECOND) + INTERVAL 1 SECOND FROM cambios);
INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto, dia)
SELECT @siguiente_id, @producto_3, 'venta', 3, 84000, 'EJEMPLO Cliente 3', @siguiente_dia
WHERE NOT EXISTS (SELECT 1 FROM cambios WHERE producto = @producto_3 AND sujeto = 'EJEMPLO Cliente 3');

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM cambios);
SET @siguiente_dia := (SELECT COALESCE(MAX(dia), CURRENT_TIMESTAMP - INTERVAL 1 SECOND) + INTERVAL 1 SECOND FROM cambios);
INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto, dia)
SELECT @siguiente_id, @producto_4, 'compra', 3, 48000, 'EJEMPLO Proveedor 4', @siguiente_dia
WHERE NOT EXISTS (SELECT 1 FROM cambios WHERE producto = @producto_4 AND sujeto = 'EJEMPLO Proveedor 4');

SET @siguiente_id := (SELECT COALESCE(MAX(id), 0) + 1 FROM cambios);
SET @siguiente_dia := (SELECT COALESCE(MAX(dia), CURRENT_TIMESTAMP - INTERVAL 1 SECOND) + INTERVAL 1 SECOND FROM cambios);
INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto, dia)
SELECT @siguiente_id, @producto_5, 'venta', 2, 96000, 'EJEMPLO Cliente 5', @siguiente_dia
WHERE NOT EXISTS (SELECT 1 FROM cambios WHERE producto = @producto_5 AND sujeto = 'EJEMPLO Cliente 5');

INSERT INTO vars (ganancia)
SELECT 30.00
WHERE NOT EXISTS (SELECT 1 FROM vars);

COMMIT;