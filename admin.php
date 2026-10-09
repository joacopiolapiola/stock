<?php
session_set_cookie_params([
'lifetime' => 0,
'path' => '/',
'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
'httponly' => true,
'samesite' => 'Lax'
]);

session_start();

if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] === '') {
header('Location: index.php');
exit;
}

require_once 'conexion.php';

function require_access(array $roles): void
{
if (!isset($_SESSION['usuario_rol']) || !in_array($_SESSION['usuario_rol'], $roles, true)) {
       http_response_code(403);
       echo 'No tienes permisos para acceder a esta sección.';
       exit;
}
}

function format_money($value): string
{
return '$' . number_format((float) $value, 2, ',', '.');
}

function format_date($value): string
{
if (!$value) {
       return 'Sin fecha';
}

return date('d/m/Y', strtotime((string) $value));
}

function normalize_role(string $rol): string
{
$rolesPermitidos = ['admin', 'gerencia', 'finanzas', 'ventas', 'operario'];
return in_array($rol, $rolesPermitidos, true) ? $rol : 'ventas';
}

$mensaje = '';
$tipo_mensaje = '';
$usuario_rol = normalize_role($_SESSION['usuario_rol'] ?? 'ventas');
$usuario_nombre = $_SESSION['usuario_nombre'] ?? 'Usuario';
$seccion = $_GET['seccion'] ?? 'resumen';
$can_admin = $usuario_rol === 'admin';
$can_gerencia = in_array($usuario_rol, ['admin', 'gerencia'], true);
$can_finanzas = in_array($usuario_rol, ['admin', 'gerencia', 'finanzas'], true);
$can_ventas = in_array($usuario_rol, ['admin', 'gerencia', 'ventas'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
$accion = $_POST['accion'] ?? '';

if ($accion === 'cerrar_sesion') {
       session_unset();
       session_destroy();
       header('Location: index.php');
       exit;
}

try {
       $pdo->beginTransaction();

       if ($accion === 'nuevo_producto') {
           require_access(['admin', 'gerencia', 'ventas']);

           $nombre = trim((string) ($_POST['nombre'] ?? ''));
           $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
           $cantidad = filter_input(INPUT_POST, 'cantidad_inicial', FILTER_VALIDATE_INT);

           if ($nombre === '' || mb_strlen($nombre) > 60 || $precio === false || $precio < 0 || $cantidad === false || $cantidad < 0) {
               throw new InvalidArgumentException('Completa el nombre, el precio y el stock inicial con valores válidos.');
           }

           $stmt = $pdo->prepare('INSERT INTO productos (precio, nombre) VALUES (:precio, :nombre)');
           $stmt->execute([
               ':precio' => $precio,
               ':nombre' => $nombre,
           ]);
           $producto_id = (int) $pdo->lastInsertId();

           $stock = $pdo->prepare('INSERT INTO cantidades (producto, cantidad) VALUES (:producto, :cantidad)');
           $stock->execute([
               ':producto' => $producto_id,
               ':cantidad' => $cantidad,
           ]);

           $mensaje = 'El producto se agregó correctamente.';
           $tipo_mensaje = 'exito';
       } elseif ($accion === 'actualizar_ganancia') {
           require_access(['admin', 'gerencia']);

           $ganancia = filter_input(INPUT_POST, 'ganancia', FILTER_VALIDATE_FLOAT);
           if ($ganancia === false || $ganancia === null || $ganancia < 0) {
               throw new InvalidArgumentException('La ganancia debe ser un porcentaje válido mayor o igual a cero.');
           }

           $stmt = $pdo->prepare('UPDATE vars SET ganancia = :ganancia');
           $stmt->execute([':ganancia' => $ganancia]);

           if ($stmt->rowCount() === 0 && (int) $pdo->query('SELECT COUNT(*) FROM vars')->fetchColumn() === 0) {
               $insert = $pdo->prepare('INSERT INTO vars (ganancia) VALUES (:ganancia)');
               $insert->execute([':ganancia' => $ganancia]);
           }

           $mensaje = 'La ganancia esperada se actualizó correctamente.';
           $tipo_mensaje = 'exito';
       } elseif ($accion === 'registrar_movimiento') {
           require_access(['admin', 'gerencia', 'ventas']);

           $producto_id = filter_input(INPUT_POST, 'producto', FILTER_VALIDATE_INT);
           $tipo = $_POST['tipo'] ?? '';
           $cantidad = filter_input(INPUT_POST, 'cantidad', FILTER_VALIDATE_INT);
           $subtotal = filter_input(INPUT_POST, 'subtotal', FILTER_VALIDATE_FLOAT);
           $sujeto = trim((string) ($_POST['sujeto'] ?? ''));
           $empleado_id = filter_input(INPUT_POST, 'empleado_id', FILTER_VALIDATE_INT);

           if ($producto_id === false || $producto_id === null || !in_array($tipo, ['compra', 'venta'], true) || $cantidad === false || $cantidad <= 0 || $subtotal === false || $subtotal < 0 || mb_strlen($sujeto) > 60) {
               throw new InvalidArgumentException('Completa todos los datos del movimiento con valores válidos.');
           }

           if ($empleado_id !== false && $empleado_id !== null && $empleado_id > 0) {
               $empleadoExiste = $pdo->prepare('SELECT id FROM empleados WHERE id = :id LIMIT 1');
               $empleadoExiste->execute([':id' => $empleado_id]);
               if (!$empleadoExiste->fetch()) {
                   throw new InvalidArgumentException('El empleado seleccionado no existe.');
               }
           }

           $stockConsulta = $pdo->prepare('SELECT cantidad FROM cantidades WHERE producto = :producto FOR UPDATE');
           $stockConsulta->execute([':producto' => $producto_id]);
           $stockActual = $stockConsulta->fetchColumn();

           if ($stockActual === false) {
               throw new InvalidArgumentException('El producto seleccionado no existe.');
           }

           if ($tipo === 'venta' && $cantidad > (int) $stockActual) {
               throw new InvalidArgumentException('No hay stock suficiente para registrar esta venta.');
           }

           $nuevoStock = $tipo === 'compra'
               ? (int) $stockActual + $cantidad
               : (int) $stockActual - $cantidad;

           $actualizarStock = $pdo->prepare('UPDATE cantidades SET cantidad = :cantidad WHERE producto = :producto');
           $actualizarStock->execute([
               ':cantidad' => $nuevoStock,
               ':producto' => $producto_id,
           ]);

           $insertarMovimiento = $pdo->prepare(
               'INSERT INTO cambios (producto, tipo, cantidad, subtotal, sujeto, empleado_id)
                VALUES (:producto, :tipo, :cantidad, :subtotal, :sujeto, :empleado_id)'
           );
           $insertarMovimiento->execute([
               ':producto' => $producto_id,
               ':tipo' => $tipo,
               ':cantidad' => $cantidad,
               ':subtotal' => $subtotal,
               ':sujeto' => $sujeto === '' ? null : $sujeto,
               ':empleado_id' => $empleado_id === false || $empleado_id === null || $empleado_id <= 0 ? null : $empleado_id,
           ]);

           $mensaje = 'El movimiento se registró correctamente.';
           $tipo_mensaje = 'exito';
       } elseif ($accion === 'nuevo_empleado') {
           require_access(['admin', 'gerencia', 'ventas']);

           $nombre = trim((string) ($_POST['nombre'] ?? ''));
           $apellido = trim((string) ($_POST['apellido'] ?? ''));
           $cargo = trim((string) ($_POST['cargo'] ?? ''));
           $salario = filter_input(INPUT_POST, 'salario', FILTER_VALIDATE_FLOAT);
           $fecha = trim((string) ($_POST['fecha_contratacion'] ?? ''));
           $telefono = trim((string) ($_POST['telefono'] ?? ''));
           $email = trim((string) ($_POST['email'] ?? ''));
           $estado = $_POST['estado'] ?? 'activo';

           if ($nombre === '' || $apellido === '' || $cargo === '' || $salario === false || $salario < 0 || $fecha === '' || !in_array($estado, ['activo', 'inactivo', 'vacaciones'], true)) {
               throw new InvalidArgumentException('Completa los datos del empleado con valores válidos.');
           }

           $stmt = $pdo->prepare('INSERT INTO empleados (nombre, apellido, cargo, salario, fecha_contratacion, telefono, email, estado) VALUES (:nombre, :apellido, :cargo, :salario, :fecha_contratacion, :telefono, :email, :estado)');
           $stmt->execute([
               ':nombre' => $nombre,
               ':apellido' => $apellido,
               ':cargo' => $cargo,
               ':salario' => $salario,
               ':fecha_contratacion' => $fecha,
               ':telefono' => $telefono === '' ? null : $telefono,
               ':email' => $email === '' ? null : $email,
               ':estado' => $estado,
           ]);

           $mensaje = 'El empleado se agregó correctamente.';
           $tipo_mensaje = 'exito';
       } elseif ($accion === 'nuevo_gasto') {
           require_access(['admin', 'gerencia', 'finanzas']);

           $concepto = trim((string) ($_POST['concepto'] ?? ''));
           $tipo = $_POST['tipo'] ?? 'gasto';
           $monto = filter_input(INPUT_POST, 'monto', FILTER_VALIDATE_FLOAT);
           $frecuencia = $_POST['frecuencia'] ?? 'mensual';
           $cada_dias = filter_input(INPUT_POST, 'cada_dias', FILTER_VALIDATE_INT);
           $proximo_vencimiento = trim((string) ($_POST['proximo_vencimiento'] ?? ''));
           $descripcion = trim((string) ($_POST['descripcion'] ?? ''));

           if ($concepto === '' || !in_array($tipo, ['gasto', 'impuesto'], true) || $monto === false || $monto < 0 || !in_array($frecuencia, ['mensual', 'quincenal', 'semanal', 'trimestral', 'anual', 'personalizada'], true) || $proximo_vencimiento === '') {
               throw new InvalidArgumentException('Completa los datos del gasto o impuesto con valores válidos.');
           }

           if ($frecuencia === 'personalizada') {
               if ($cada_dias === false || $cada_dias <= 0) {
                   throw new InvalidArgumentException('Cuando eliges una frecuencia personalizada, define cada cuántos días se repite.');
               }
           } else {
               $cada_dias = 0;
           }

           $stmt = $pdo->prepare('INSERT INTO gastos (concepto, tipo, monto, frecuencia, cada_dias, proximo_vencimiento, descripcion, usuario_id) VALUES (:concepto, :tipo, :monto, :frecuencia, :cada_dias, :proximo_vencimiento, :descripcion, :usuario_id)');
           $stmt->execute([
               ':concepto' => $concepto,
               ':tipo' => $tipo,
               ':monto' => $monto,
               ':frecuencia' => $frecuencia,
               ':cada_dias' => $cada_dias,
               ':proximo_vencimiento' => $proximo_vencimiento,
               ':descripcion' => $descripcion === '' ? null : $descripcion,
               ':usuario_id' => $_SESSION['usuario_id'] ?? null,
           ]);

           $mensaje = 'El gasto o impuesto se guardó correctamente.';
           $tipo_mensaje = 'exito';
       } elseif ($accion === 'nuevo_usuario') {
           require_access(['admin']);

           $nombre = trim((string) ($_POST['nombre'] ?? ''));
           $nombre_completo = trim((string) ($_POST['nombre_completo'] ?? ''));
           $rol = $_POST['rol'] ?? 'ventas';
           $password = (string) ($_POST['password'] ?? '');

           if ($nombre === '' || $nombre_completo === '' || $password === '' || !in_array($rol, ['admin', 'gerencia', 'finanzas', 'ventas', 'operario'], true)) {
               throw new InvalidArgumentException('Completa todos los campos del usuario y asigna un rol válido.');
           }

           $checkUser = $pdo->prepare('SELECT id FROM usuarios WHERE nombre = :nombre LIMIT 1');
           $checkUser->execute([':nombre' => $nombre]);
           if ($checkUser->fetch()) {
               throw new InvalidArgumentException('El nombre de usuario ya existe.');
           }

           $hash = password_hash($password, PASSWORD_BCRYPT);
           $stmt = $pdo->prepare('INSERT INTO usuarios (nombre, nombre_completo, pass, rol) VALUES (:nombre, :nombre_completo, :pass, :rol)');
           $stmt->execute([
               ':nombre' => $nombre,
               ':nombre_completo' => $nombre_completo,
               ':pass' => $hash,
               ':rol' => $rol,
           ]);

           $mensaje = 'El usuario se creó correctamente.';
           $tipo_mensaje = 'exito';
       } else {
           throw new InvalidArgumentException('La acción solicitada no es válida.');
       }

       $pdo->commit();
} catch (InvalidArgumentException $error) {
       if ($pdo->inTransaction()) {
           $pdo->rollBack();
       }
       $mensaje = $error->getMessage();
       $tipo_mensaje = 'error';
} catch (PDOException $error) {
       if ($pdo->inTransaction()) {
           $pdo->rollBack();
       }
       error_log($error->getMessage());
       $mensaje = 'No se pudo guardar la información. Verifica los datos e inténtalo nuevamente.';
       $tipo_mensaje = 'error';
}
}

$ganancia_esperada = (float) ($pdo->query('SELECT ganancia FROM vars LIMIT 1')->fetchColumn() ?: 0);

$productos = $pdo->query(
"SELECT p.id, p.nombre, p.precio,
           COALESCE(c.cantidad, 0) AS cantidad_actual,
           COALESCE(SUM(CASE WHEN ca.tipo = 'venta' THEN ca.cantidad ELSE 0 END), 0) AS unidades_vendidas,
           COALESCE(SUM(CASE WHEN ca.tipo = 'venta' THEN ca.subtotal ELSE 0 END), 0) AS ingresos_ventas,
           MAX(CASE WHEN ca.tipo = 'venta' THEN ca.dia END) AS ultima_venta,
           uc.cantidad AS ultima_compra_cantidad,
           uc.subtotal AS ultima_compra_subtotal,
           uc.dia AS ultima_compra_fecha
 FROM productos p
 LEFT JOIN cantidades c ON c.producto = p.id
 LEFT JOIN cambios ca ON ca.producto = p.id
 LEFT JOIN (
        SELECT compra.producto, compra.cantidad, compra.subtotal, compra.dia
        FROM cambios compra
        INNER JOIN (
            SELECT producto, MAX(dia) AS ultima_fecha
            FROM cambios
            WHERE tipo = 'compra'
            GROUP BY producto
        ) ultima ON ultima.producto = compra.producto AND ultima.ultima_fecha = compra.dia
        WHERE compra.tipo = 'compra'
 ) uc ON uc.producto = p.id
 GROUP BY p.id, p.nombre, p.precio, c.cantidad, uc.cantidad, uc.subtotal, uc.dia
 ORDER BY unidades_vendidas DESC, p.nombre"
)->fetchAll();

$estadisticas = $pdo->query(
"SELECT COUNT(DISTINCT CASE WHEN tipo = 'venta' THEN producto END) AS productos_vendidos,
           COALESCE(SUM(CASE WHEN tipo = 'venta' THEN cantidad ELSE 0 END), 0) AS unidades_vendidas,
           COALESCE(SUM(CASE WHEN tipo = 'venta' THEN subtotal ELSE 0 END), 0) AS ingresos_ventas,
           COALESCE(SUM(CASE WHEN tipo = 'compra' THEN subtotal ELSE 0 END), 0) AS compras_totales,
           COUNT(CASE WHEN tipo = 'venta' THEN 1 END) AS operaciones_venta
 FROM cambios"
)->fetch();

$empleados = $pdo->query(
"SELECT e.*, COALESCE(SUM(CASE WHEN c.tipo = 'venta' THEN c.subtotal ELSE 0 END), 0) AS ventas_totales,
           COUNT(CASE WHEN c.tipo = 'venta' THEN c.id END) AS ventas_registradas
 FROM empleados e
 LEFT JOIN cambios c ON c.empleado_id = e.id
 GROUP BY e.id, e.nombre, e.apellido, e.cargo, e.salario, e.fecha_contratacion, e.telefono, e.email, e.estado
 ORDER BY e.nombre, e.apellido"
)->fetchAll();

$gastos = $pdo->query(
'SELECT g.*, u.nombre_completo AS usuario_creador
 FROM gastos g
 LEFT JOIN usuarios u ON u.id = g.usuario_id
 WHERE g.activo = 1
 ORDER BY g.proximo_vencimiento ASC'
)->fetchAll();

$usuarios = $pdo->query(
'SELECT id, nombre, nombre_completo, rol, activo FROM usuarios ORDER BY nombre_completo ASC'
)->fetchAll();

$ventasMensuales = $pdo->query(
"SELECT DATE_FORMAT(dia, '%Y-%m') AS periodo,
           SUM(CASE WHEN tipo = 'venta' THEN subtotal ELSE 0 END) AS ventas,
           SUM(CASE WHEN tipo = 'compra' THEN subtotal ELSE 0 END) AS compras
 FROM cambios
 GROUP BY DATE_FORMAT(dia, '%Y-%m')
 ORDER BY periodo DESC
 LIMIT 6"
)->fetchAll();

$gastosMensuales = $pdo->query(
"SELECT DATE_FORMAT(proximo_vencimiento, '%Y-%m') AS periodo, SUM(monto) AS total
 FROM gastos
 WHERE activo = 1
 GROUP BY DATE_FORMAT(proximo_vencimiento, '%Y-%m')
 ORDER BY periodo DESC
 LIMIT 6"
)->fetchAll();

$topProductos = $pdo->query(
"SELECT p.nombre,
           SUM(CASE WHEN c.tipo = 'venta' THEN c.cantidad ELSE 0 END) AS unidades,
           SUM(CASE WHEN c.tipo = 'venta' THEN c.subtotal ELSE 0 END) AS ingresos
 FROM cambios c
 JOIN productos p ON p.id = c.producto
 WHERE c.tipo = 'venta'
 GROUP BY p.id, p.nombre
 ORDER BY unidades DESC, ingresos DESC
 LIMIT 5"
)->fetchAll();

$ventasPorEmpleado = $pdo->query(
"SELECT e.nombre, e.apellido, COALESCE(SUM(CASE WHEN c.tipo = 'venta' THEN c.subtotal ELSE 0 END), 0) AS ventas_totales,
           COUNT(CASE WHEN c.tipo = 'venta' THEN c.id END) AS ventas_registradas
 FROM empleados e
 LEFT JOIN cambios c ON c.empleado_id = e.id
 GROUP BY e.id, e.nombre, e.apellido
 ORDER BY ventas_totales DESC"
)->fetchAll();

$reciboMes = $_GET['recibo_mes'] ?? date('Y-m');
$reciboGastos = $pdo->prepare(
"SELECT * FROM gastos WHERE activo = 1 AND DATE_FORMAT(proximo_vencimiento, '%Y-%m') = :mes ORDER BY proximo_vencimiento ASC"
);
$reciboGastos->execute([':mes' => $reciboMes]);
$reciboGastosLista = $reciboGastos->fetchAll();
$reciboTotal = 0;
foreach ($reciboGastosLista as $gasto) {
$reciboTotal += (float) $gasto['monto'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Panel del emprendimiento</title>
<link rel="stylesheet" href="style.css">
<script>
       const temaGuardado = localStorage.getItem('tema');
       if (temaGuardado === 'oscuro') {
           document.documentElement.classList.add('tema-oscuro');
       }
</script>
</head>
<body>
<div class="topbar">
       <div>
           <h1>Panel del emprendimiento</h1>
           <p>Usuario: <strong><?= htmlspecialchars($usuario_nombre, ENT_QUOTES, 'UTF-8') ?></strong> | Rol: <strong><?= htmlspecialchars(strtoupper($usuario_rol), ENT_QUOTES, 'UTF-8') ?></strong></p>
       </div>
       <div class="section-actions">
           <form action="" method="post">
               <input type="hidden" name="accion" value="cerrar_sesion">
               <button type="submit">Cerrar sesión</button>
           </form>
           <button type="button" class="boton-tema" id="boton-tema" aria-label="Activar tema oscuro">🌙/🌞</button>
       </div>
</div>

<?php if ($mensaje !== ''): ?>
       <p class="mensaje <?= htmlspecialchars($tipo_mensaje, ENT_QUOTES, 'UTF-8') ?>" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<nav class="menu-principal" aria-label="Menú principal">
       <a href="admin.php?seccion=resumen" class="<?= $seccion === 'resumen' ? 'activo' : '' ?>">Resumen</a>
       <a href="admin.php?seccion=reportes" class="<?= $seccion === 'reportes' ? 'activo' : '' ?>">Reportes</a>
       <a href="admin.php?seccion=gastos" class="<?= $seccion === 'gastos' ? 'activo' : '' ?>">Gastos e impuestos</a>
       <a href="admin.php?seccion=personal" class="<?= $seccion === 'personal' ? 'activo' : '' ?>">Personal</a>
       <?php if ($can_admin): ?>
           <a href="admin.php?seccion=usuarios" class="<?= $seccion === 'usuarios' ? 'activo' : '' ?>">Usuarios</a>
       <?php endif; ?>
</nav>

<?php if ($seccion === 'resumen'): ?>
       <section class="estadisticas" aria-label="Resumen general del negocio">
           <article>
               <strong><?= (int) $estadisticas['productos_vendidos'] ?></strong>
               <span>Productos vendidos</span>
           </article>
           <article>
               <strong><?= (int) $estadisticas['unidades_vendidas'] ?></strong>
               <span>Unidades vendidas</span>
           </article>
           <article>
               <strong><?= format_money((float) $estadisticas['ingresos_ventas']) ?></strong>
               <span>Ingresos por ventas</span>
           </article>
           <article>
               <strong><?= format_money((float) ($estadisticas['ingresos_ventas'] - $estadisticas['compras_totales'])) ?></strong>
               <span>Utilidad estimada</span>
           </article>
       </section>

       <h2>Ventas por producto</h2>
       <table>
           <thead>
               <tr>
                   <th>Producto</th>
                   <th>Stock</th>
                   <th>Vendidas</th>
                   <th>Ingresos</th>
                   <th>Precio</th>
                   <th>Precio sugerido</th>
                   <th>Última venta</th>
                   <th>Última compra</th>
                   <th>Fecha última compra</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($productos as $producto): ?>
                   <tr>
                       <td><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= (int) $producto['cantidad_actual'] ?></td>
                       <td><?= (int) $producto['unidades_vendidas'] ?></td>
                       <td><?= format_money((float) $producto['ingresos_ventas']) ?></td>
                       <td><?= format_money((float) $producto['precio']) ?></td>
                       <td><?= format_money((float) $producto['precio'] * (1 + $ganancia_esperada / 100)) ?></td>
                       <td><?= $producto['ultima_venta'] ? htmlspecialchars($producto['ultima_venta'], ENT_QUOTES, 'UTF-8') : 'Sin ventas' ?></td>
                       <td><?= $producto['ultima_compra_cantidad'] ? format_money((float) $producto['ultima_compra_subtotal'] / (int) $producto['ultima_compra_cantidad']) : 'Sin compras' ?></td>
                       <td><?= $producto['ultima_compra_fecha'] ? htmlspecialchars($producto['ultima_compra_fecha'], ENT_QUOTES, 'UTF-8') : 'Sin compras' ?></td>
                   </tr>
               <?php endforeach; ?>
           </tbody>
       </table>

       <section class="paneles-formulario">
           <form action="" method="post" class="formulario">
               <h2>Agregar producto</h2>
               <p class="ayuda">Crea un producto y define su stock inicial.</p>
               <input type="hidden" name="accion" value="nuevo_producto">
               <label for="nombre">Nombre del producto</label>
               <input type="text" name="nombre" id="nombre" maxlength="60" required>
               <label for="precio">Precio registrado</label>
               <input type="number" name="precio" id="precio" min="0" step="0.01" required>
               <label for="cantidad_inicial">Stock inicial</label>
               <input type="number" name="cantidad_inicial" id="cantidad_inicial" min="0" step="1" required>
               <button type="submit">Agregar producto</button>
           </form>

           <form action="" method="post" class="formulario">
               <h2>Registrar movimiento</h2>
               <p class="ayuda">Las compras aumentan stock y las ventas lo descuentan.</p>
               <input type="hidden" name="accion" value="registrar_movimiento">
               <label for="producto">Producto</label>
               <select name="producto" id="producto" required>
                   <option value="">Selecciona un producto</option>
                   <?php foreach ($productos as $producto): ?>
                       <option value="<?= (int) $producto['id'] ?>"><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?> (stock: <?= (int) $producto['cantidad_actual'] ?>)</option>
                   <?php endforeach; ?>
               </select>
               <label for="tipo">Tipo de movimiento</label>
               <select name="tipo" id="tipo" required>
                   <option value="venta">Venta</option>
                   <option value="compra">Compra</option>
               </select>
               <label for="empleado_id">Empleado responsable</label>
               <select name="empleado_id" id="empleado_id">
                   <option value="">Sin empleado</option>
                   <?php foreach ($empleados as $empleado): ?>
                       <option value="<?= (int) $empleado['id'] ?>"><?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido'], ENT_QUOTES, 'UTF-8') ?></option>
                   <?php endforeach; ?>
               </select>
               <label for="sujeto">Cliente o proveedor</label>
               <input type="text" name="sujeto" id="sujeto" maxlength="60">
               <label for="cantidad">Cantidad</label>
               <input type="number" name="cantidad" id="cantidad" min="1" step="1" required>
               <label for="subtotal">Subtotal</label>
               <input type="number" name="subtotal" id="subtotal" min="0" step="0.01" required>
               <button type="submit">Registrar movimiento</button>
           </form>
       </section>

       <?php if ($can_gerencia): ?>
           <form action="" method="post" class="formulario">
               <h2>Ganancia esperada</h2>
               <p class="ayuda">Define el porcentaje que se suma al precio registrado para sugerir el precio de venta.</p>
               <input type="hidden" name="accion" value="actualizar_ganancia">
               <label for="ganancia">Ganancia (%)</label>
               <input type="number" name="ganancia" id="ganancia" min="0" step="0.01" value="<?= htmlspecialchars((string) $ganancia_esperada, ENT_QUOTES, 'UTF-8') ?>" required>
               <button type="submit">Guardar ganancia</button>
           </form>
       <?php endif; ?>
<?php elseif ($seccion === 'reportes'): ?>
       <?php
       $resumenVentas = 0;
       $resumenCompras = 0;
       $resumenGastos = 0;
       foreach ($ventasMensuales as $mes) {
           $resumenVentas += (float) $mes['ventas'];
           $resumenCompras += (float) $mes['compras'];
       }
       foreach ($gastosMensuales as $gasto) {
           $resumenGastos += (float) $gasto['total'];
       }
       ?>
       <h2>Reportes del negocio</h2>
       <div class="mini-stats">
           <div class="mini-stat">
               <span>Ventas</span>
               <strong><?= format_money($resumenVentas) ?></strong>
           </div>
           <div class="mini-stat">
               <span>Compras</span>
               <strong><?= format_money($resumenCompras) ?></strong>
           </div>
           <div class="mini-stat">
               <span>Gastos</span>
               <strong><?= format_money($resumenGastos) ?></strong>
           </div>
       </div>

       <div class="dashboard-grid">
           <section class="card">
               <h3>Ventas por mes</h3>
               <table>
                   <thead>
                       <tr>
                           <th>Mes</th>
                           <th>Ventas</th>
                           <th>Compras</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($ventasMensuales as $mes): ?>
                           <tr>
                               <td><?= htmlspecialchars($mes['periodo'], ENT_QUOTES, 'UTF-8') ?></td>
                               <td><?= format_money((float) $mes['ventas']) ?></td>
                               <td><?= format_money((float) $mes['compras']) ?></td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
           </section>

           <section class="card">
               <h3>Productos más vendidos</h3>
               <table>
                   <thead>
                       <tr>
                           <th>Producto</th>
                           <th>Unidades</th>
                           <th>Ingreso</th>
                       </tr>
                   </thead>
                   <tbody>
                       <?php foreach ($topProductos as $producto): ?>
                           <tr>
                               <td><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                               <td><?= (int) $producto['unidades'] ?></td>
                               <td><?= format_money((float) $producto['ingresos']) ?></td>
                           </tr>
                       <?php endforeach; ?>
                   </tbody>
               </table>
           </section>
       </div>

       <section class="card">
           <h3>Desempeño por empleado</h3>
           <table>
               <thead>
                   <tr>
                       <th>Empleado</th>
                       <th>Ventas</th>
                       <th>Operaciones</th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($ventasPorEmpleado as $empleado): ?>
                       <tr>
                           <td><?= htmlspecialchars(($empleado['nombre'] ?? '') . ' ' . ($empleado['apellido'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                           <td><?= format_money((float) $empleado['ventas_totales']) ?></td>
                           <td><?= (int) $empleado['ventas_registradas'] ?></td>
                       </tr>
                   <?php endforeach; ?>
               </tbody>
           </table>
       </section>
<?php elseif ($seccion === 'gastos'): ?>
       <?php if ($can_finanzas): ?>
           <form action="" method="post" class="formulario">
               <h2>Agregar gasto o impuesto</h2>
               <input type="hidden" name="accion" value="nuevo_gasto">
               <label for="concepto">Concepto</label>
               <input type="text" name="concepto" id="concepto" maxlength="100" required>
               <label for="tipo">Tipo</label>
               <select name="tipo" id="tipo" required>
                   <option value="gasto">Gasto</option>
                   <option value="impuesto">Impuesto</option>
               </select>
               <label for="monto">Monto</label>
               <input type="number" name="monto" id="monto" min="0" step="0.01" required>
               <label for="frecuencia">Frecuencia</label>
               <select name="frecuencia" id="frecuencia">
                   <option value="mensual">Mensual</option>
                   <option value="quincenal">Quincenal</option>
                   <option value="semanal">Semanal</option>
                   <option value="trimestral">Trimestral</option>
                   <option value="anual">Anual</option>
                   <option value="personalizada">Personalizada</option>
               </select>
               <label for="cada_dias">Cada X días (si es personalizada)</label>
               <input type="number" name="cada_dias" id="cada_dias" min="1" step="1" placeholder="Ej: 45">
               <label for="proximo_vencimiento">Próximo vencimiento</label>
               <input type="date" name="proximo_vencimiento" id="proximo_vencimiento" required>
               <label for="descripcion">Descripción</label>
               <textarea name="descripcion" id="descripcion" rows="3"></textarea>
               <button type="submit">Guardar gasto</button>
           </form>
       <?php endif; ?>

       <div class="section-actions">
           <form method="get">
               <input type="hidden" name="seccion" value="gastos">
               <label for="recibo_mes">Mes del recibo</label>
               <input type="month" name="recibo_mes" id="recibo_mes" value="<?= htmlspecialchars($reciboMes, ENT_QUOTES, 'UTF-8') ?>">
               <button type="submit">Actualizar recibo</button>
           </form>
       </div>

       <table>
           <thead>
               <tr>
                   <th>Concepto</th>
                   <th>Tipo</th>
                   <th>Monto</th>
                   <th>Frecuencia</th>
                   <th>Próximo vencimiento</th>
                   <th>Descripción</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($gastos as $gasto): ?>
                   <tr>
                       <td><?= htmlspecialchars($gasto['concepto'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><span class="tag <?= $gasto['tipo'] === 'impuesto' ? 'impuesto' : '' ?>"><?= htmlspecialchars($gasto['tipo'], ENT_QUOTES, 'UTF-8') ?></span></td>
                       <td><?= format_money((float) $gasto['monto']) ?></td>
                       <td><?= htmlspecialchars($gasto['frecuencia'], ENT_QUOTES, 'UTF-8') ?><?= $gasto['cada_dias'] > 0 ? ' (' . (int) $gasto['cada_dias'] . ' días)' : '' ?></td>
                       <td><?= format_date($gasto['proximo_vencimiento']) ?></td>
                       <td><?= htmlspecialchars((string) ($gasto['descripcion'] ?? 'Sin descripción'), ENT_QUOTES, 'UTF-8') ?></td>
                   </tr>
               <?php endforeach; ?>
           </tbody>
       </table>

       <section class="recibo">
           <div class="encabezado">
               <div>
                   <h3>Recibo de gastos e impuestos</h3>
                   <p>Período: <strong><?= htmlspecialchars($reciboMes, ENT_QUOTES, 'UTF-8') ?></strong></p>
               </div>
               <button type="button" onclick="window.print()">Imprimir recibo</button>
           </div>

           <table>
               <thead>
                   <tr>
                       <th>Concepto</th>
                       <th>Tipo</th>
                       <th>Frecuencia</th>
                       <th>Monto</th>
                       <th>Vence</th>
                   </tr>
               </thead>
               <tbody>
                   <?php foreach ($reciboGastosLista as $gasto): ?>
                       <tr>
                           <td><?= htmlspecialchars($gasto['concepto'], ENT_QUOTES, 'UTF-8') ?></td>
                           <td><?= htmlspecialchars($gasto['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                           <td><?= htmlspecialchars($gasto['frecuencia'], ENT_QUOTES, 'UTF-8') ?></td>
                           <td><?= format_money((float) $gasto['monto']) ?></td>
                           <td><?= format_date($gasto['proximo_vencimiento']) ?></td>
                       </tr>
                   <?php endforeach; ?>
                   <?php if (empty($reciboGastosLista)): ?>
                       <tr>
                           <td colspan="5">No hay gastos para ese período.</td>
                       </tr>
                   <?php endif; ?>
               </tbody>
               <tfoot>
                   <tr>
                       <th colspan="3">Total</th>
                       <th colspan="2"><?= format_money((float) $reciboTotal) ?></th>
                   </tr>
               </tfoot>
           </table>
       </section>
<?php elseif ($seccion === 'personal'): ?>
       <?php if ($can_ventas): ?>
           <form action="" method="post" class="formulario">
               <h2>Agregar empleado</h2>
               <input type="hidden" name="accion" value="nuevo_empleado">
               <label for="nombre_empleado">Nombre</label>
               <input type="text" id="nombre_empleado" name="nombre" required>
               <label for="apellido">Apellido</label>
               <input type="text" id="apellido" name="apellido" required>
               <label for="cargo">Cargo</label>
               <input type="text" id="cargo" name="cargo" required>
               <label for="salario">Salario</label>
               <input type="number" id="salario" name="salario" min="0" step="0.01" required>
               <label for="fecha_contratacion">Fecha de contratación</label>
               <input type="date" id="fecha_contratacion" name="fecha_contratacion" required>
               <label for="telefono">Teléfono</label>
               <input type="text" id="telefono" name="telefono">
               <label for="email">Email</label>
               <input type="email" id="email" name="email">
               <label for="estado">Estado</label>
               <select id="estado" name="estado">
                   <option value="activo">Activo</option>
                   <option value="inactivo">Inactivo</option>
                   <option value="vacaciones">Vacaciones</option>
               </select>
               <button type="submit">Guardar empleado</button>
           </form>
       <?php endif; ?>

       <table>
           <thead>
               <tr>
                   <th>Nombre</th>
                   <th>Cargo</th>
                   <th>Salario</th>
                   <th>Estado</th>
                   <th>Ventas</th>
                   <th>Operaciones</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($empleados as $empleado): ?>
                   <tr>
                       <td><?= htmlspecialchars($empleado['nombre'] . ' ' . $empleado['apellido'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= htmlspecialchars($empleado['cargo'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= format_money((float) $empleado['salario']) ?></td>
                       <td><?= htmlspecialchars($empleado['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= format_money((float) $empleado['ventas_totales']) ?></td>
                       <td><?= (int) $empleado['ventas_registradas'] ?></td>
                   </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
<?php elseif ($seccion === 'usuarios' && $can_admin): ?>
       <form action="" method="post" class="formulario">
           <h2>Crear usuario con permisos</h2>
           <input type="hidden" name="accion" value="nuevo_usuario">
           <label for="usuario_nombre">Nombre de usuario</label>
           <input type="text" id="usuario_nombre" name="nombre" required>
           <label for="usuario_completo">Nombre completo</label>
           <input type="text" id="usuario_completo" name="nombre_completo" required>
           <label for="usuario_rol">Rol</label>
           <select id="usuario_rol" name="rol">
               <option value="admin">Administrador</option>
               <option value="gerencia">Gerencia</option>
               <option value="finanzas">Finanzas</option>
               <option value="ventas">Ventas</option>
               <option value="operario">Operario</option>
           </select>
           <label for="usuario_pass">Contraseña</label>
           <input type="password" id="usuario_pass" name="password" required>
           <button type="submit">Crear usuario</button>
       </form>

       <table>
           <thead>
               <tr>
                   <th>Usuario</th>
                   <th>Nombre</th>
                   <th>Rol</th>
                   <th>Estado</th>
               </tr>
           </thead>
           <tbody>
               <?php foreach ($usuarios as $usuario): ?>
                   <tr>
                       <td><?= htmlspecialchars($usuario['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= htmlspecialchars($usuario['nombre_completo'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= htmlspecialchars($usuario['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                       <td><?= $usuario['activo'] ? 'Activo' : 'Inactivo' ?></td>
                   </tr>
               <?php endforeach; ?>
           </tbody>
       </table>
<?php endif; ?>

<script>
       const botonTema = document.getElementById('boton-tema');
       if (botonTema) {
           const actualizarBotonTema = () => {
               const temaOscuro = document.documentElement.classList.contains('tema-oscuro');
               botonTema.textContent = temaOscuro ? 'Tema claro' : 'Tema oscuro';
               botonTema.setAttribute('aria-label', temaOscuro ? 'Activar tema claro' : 'Activar tema oscuro');
           };

           actualizarBotonTema();
           botonTema.addEventListener('click', () => {
               const temaOscuro = document.documentElement.classList.toggle('tema-oscuro');
               localStorage.setItem('tema', temaOscuro ? 'oscuro' : 'claro');
               actualizarBotonTema();
           });
       }
</script>
</body>
</html>