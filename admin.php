<?php
require_once "conexion.php";

$mensaje = "";
$tipo_mensaje = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        $pdo->beginTransaction();

        if ($accion === "nuevo_producto") {
            $nombre = trim($_POST["nombre"] ?? "");
            $precio = filter_input(INPUT_POST, "precio", FILTER_VALIDATE_INT);
            $cantidad = filter_input(INPUT_POST, "cantidad_inicial", FILTER_VALIDATE_INT);

            if ($nombre === "" || mb_strlen($nombre) > 20 || $precio === false || $precio < 0 || $cantidad === false || $cantidad < 0) {
                throw new InvalidArgumentException("Completa el nombre, el precio y el stock inicial con valores válidos.");
            }

            $siguiente_id = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM productos")->fetchColumn();
            $insertar_producto = $pdo->prepare("INSERT INTO productos (id, precio, nombre) VALUES (:id, :precio, :nombre)");
            $insertar_producto->execute([
                ":id" => $siguiente_id,
                ":precio" => $precio,
                ":nombre" => $nombre,
            ]);

            $insertar_stock = $pdo->prepare("INSERT INTO cantidades (producto, cantidad) VALUES (:producto, :cantidad)");
            $insertar_stock->execute([
                ":producto" => $siguiente_id,
                ":cantidad" => $cantidad,
            ]);

            $pdo->commit();
            $mensaje = "El producto se agregó correctamente.";
            $tipo_mensaje = "exito";
        } elseif ($accion === "actualizar_ganancia") {
            $ganancia = filter_input(INPUT_POST, "ganancia", FILTER_VALIDATE_FLOAT);

            if ($ganancia === false || $ganancia === null || $ganancia < 0) {
                throw new InvalidArgumentException("La ganancia debe ser un porcentaje válido mayor o igual a cero.");
            }

            $actualizar_ganancia = $pdo->prepare("UPDATE vars SET ganancia = :ganancia");
            $actualizar_ganancia->execute([":ganancia" => $ganancia]);

            if ($actualizar_ganancia->rowCount() === 0 && (int) $pdo->query("SELECT COUNT(*) FROM vars")->fetchColumn() === 0) {
                $insertar_ganancia = $pdo->prepare("INSERT INTO vars (ganancia) VALUES (:ganancia)");
                $insertar_ganancia->execute([":ganancia" => $ganancia]);
            }

            $pdo->commit();
            $mensaje = "La ganancia esperada se actualizó correctamente.";
            $tipo_mensaje = "exito";
        } elseif ($accion === "registrar_movimiento") {
            $producto_id = filter_input(INPUT_POST, "producto", FILTER_VALIDATE_INT);
            $tipo = $_POST["tipo"] ?? "";
            $cantidad = filter_input(INPUT_POST, "cantidad", FILTER_VALIDATE_INT);
            $subtotal = filter_input(INPUT_POST, "subtotal", FILTER_VALIDATE_INT);
            $sujeto = trim($_POST["sujeto"] ?? "");

            if ($producto_id === false || $producto_id === null || !in_array($tipo, ["compra", "venta"], true) || $cantidad === false || $cantidad <= 0 || $subtotal === false || $subtotal < 0 || mb_strlen($sujeto) > 20) {
                throw new InvalidArgumentException("Completa todos los datos del movimiento con valores válidos.");
            }

            $stock_consulta = $pdo->prepare("SELECT cantidad FROM cantidades WHERE producto = :producto FOR UPDATE");
            $stock_consulta->execute([":producto" => $producto_id]);
            $stock_actual = $stock_consulta->fetchColumn();

            if ($stock_actual === false) {
                throw new InvalidArgumentException("El producto seleccionado no existe.");
            }

            if ($tipo === "venta" && $cantidad > (int) $stock_actual) {
                throw new InvalidArgumentException("No hay stock suficiente para registrar esta venta.");
            }

            $nuevo_stock = $tipo === "compra"
                ? (int) $stock_actual + $cantidad
                : (int) $stock_actual - $cantidad;
            $actualizar_stock = $pdo->prepare("UPDATE cantidades SET cantidad = :cantidad WHERE producto = :producto");
            $actualizar_stock->execute([
                ":cantidad" => $nuevo_stock,
                ":producto" => $producto_id,
            ]);

            $siguiente_id = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM cambios")->fetchColumn();
            $insertar_movimiento = $pdo->prepare(
                "INSERT INTO cambios (id, producto, tipo, cantidad, subtotal, sujeto)
                 VALUES (:id, :producto, :tipo, :cantidad, :subtotal, :sujeto)"
            );
            $insertar_movimiento->execute([
                ":id" => $siguiente_id,
                ":producto" => $producto_id,
                ":tipo" => $tipo,
                ":cantidad" => $cantidad,
                ":subtotal" => $subtotal,
                ":sujeto" => $sujeto === "" ? null : $sujeto,
            ]);

            $pdo->commit();
            $mensaje = "El movimiento se registró correctamente.";
            $tipo_mensaje = "exito";
        }
    } catch (InvalidArgumentException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = $error->getMessage();
        $tipo_mensaje = "error";
    } catch (PDOException $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($error->getMessage());
        $mensaje = "No se pudo guardar la información. Verifica los datos e inténtalo nuevamente.";
        $tipo_mensaje = "error";
    }
}

$ganancia_esperada = (float) ($pdo->query("SELECT ganancia FROM vars LIMIT 1")->fetchColumn() ?: 0);

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
            COUNT(CASE WHEN tipo = 'venta' THEN 1 END) AS operaciones_venta
     FROM cambios"
)->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>stock</title>
    <script>
        const temaGuardado = localStorage.getItem("tema");
        if (temaGuardado === "oscuro") {
            document.documentElement.classList.add("tema-oscuro");
        }
    </script>
</head>
<body>
<button type="button" class="boton-tema" id="boton-tema" aria-label="Activar tema oscuro">
    🌙/🌞
</button>
<h1>Estadísticas de ventas</h1>
<?php if ($mensaje !== ""): ?>
    <p class="mensaje <?= $tipo_mensaje ?>" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<section class="estadisticas" aria-label="Resumen de ventas">
    <article>
        <strong><?= (int) $estadisticas['productos_vendidos'] ?></strong>
        <span>Productos vendidos</span>
    </article>
    <article>
        <strong><?= (int) $estadisticas['unidades_vendidas'] ?></strong>
        <span>Unidades vendidas</span>
    </article>
    <article>
        <strong>$<?= number_format((float) $estadisticas['ingresos_ventas'], 2, ',', '.') ?></strong>
        <span>Ingresos por ventas</span>
    </article>
    <article>
        <strong><?= (int) $estadisticas['operaciones_venta'] ?></strong>
        <span>Operaciones de venta</span>
    </article>
</section>

<h2>Ventas por producto</h2>
<table>
    <thead>
        <tr>
            <th>Producto</th>
            <th>Stock actual</th>
            <th>Unidades vendidas</th>
            <th>Ingresos</th>
            <th>Precio registrado</th>
            <th>Precio sugerido de venta</th>
            <th>Última venta</th>
            <th>Precio última compra</th>
            <th>Cantidad última compra</th>
            <th>Fecha última compra</th>
        </tr>
    </thead>
    <tbody>
<?php foreach ($productos as $producto): ?>
        <tr>
            <td><?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= (int) $producto['cantidad_actual'] ?></td>
            <td><?= (int) $producto['unidades_vendidas'] ?></td>
            <td>$<?= number_format((float) $producto['ingresos_ventas'], 2, ',', '.') ?></td>
            <td>$<?= number_format((float) $producto['precio'], 2, ',', '.') ?></td>
            <td>$<?= number_format((float) $producto['precio'] * (1 + $ganancia_esperada / 100), 2, ',', '.') ?></td>
            <td><?= $producto['ultima_venta'] ? htmlspecialchars($producto['ultima_venta'], ENT_QUOTES, 'UTF-8') : 'Sin ventas' ?></td>
            <td><?= $producto['ultima_compra_cantidad'] ? '$' . number_format((float) $producto['ultima_compra_subtotal'] / (int) $producto['ultima_compra_cantidad'], 2, ',', '.') : 'Sin compras' ?></td>
            <td><?= $producto['ultima_compra_cantidad'] ? (int) $producto['ultima_compra_cantidad'] : 'Sin compras' ?></td>
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
    <input type="text" name="nombre" id="nombre" maxlength="20" required>
    <label for="precio">Precio registrado</label>
    <input type="number" name="precio" id="precio" min="0" step="1" required>
    <label for="cantidad_inicial">Stock inicial</label>
    <input type="number" name="cantidad_inicial" id="cantidad_inicial" min="0" step="1" required>
    <button type="submit">Agregar producto</button>
</form>

<form action="" method="post" class="formulario">
    <h2>Registrar movimiento</h2>
    <p class="ayuda">Las compras aumentan el stock y las ventas lo descuentan.</p>
    <input type="hidden" name="accion" value="registrar_movimiento">
    <label for="producto">Producto</label>
    <select name="producto" id="producto" required>
        <option value="">Selecciona un producto</option>
        <?php foreach ($productos as $producto): ?>
            <option value="<?= (int) $producto['id'] ?>">
                <?= htmlspecialchars($producto['nombre'], ENT_QUOTES, 'UTF-8') ?> (stock: <?= (int) $producto['cantidad_actual'] ?>)
            </option>
        <?php endforeach; ?>
    </select>
    <label for="tipo">Tipo de movimiento</label>
    <select name="tipo" id="tipo" required>
        <option value="venta">Venta</option>
        <option value="compra">Compra</option>
    </select>
    <label for="sujeto">Cliente o proveedor <span>(opcional)</span></label>
    <input type="text" name="sujeto" id="sujeto" maxlength="20">
    <label for="cantidad">Cantidad</label>
    <input type="number" name="cantidad" id="cantidad" min="1" step="1" required>
    <label for="subtotal">Subtotal</label>
    <input type="number" name="subtotal" id="subtotal" min="0" step="1" required>
    <button type="submit">Registrar movimiento</button>
</form>
</section>
<form action="" method="post" class="formulario">
    <h2>Ganancia esperada</h2>
    <p class="ayuda">Define el porcentaje que se suma al precio registrado para sugerir el precio de venta.</p>
    <input type="hidden" name="accion" value="actualizar_ganancia">
    <label for="ganancia">Ganancia (%)</label>
    <input type="number" name="ganancia" id="ganancia" min="0" step="0.01" value="<?= htmlspecialchars((string) $ganancia_esperada, ENT_QUOTES, 'UTF-8') ?>" required>
    <button type="submit">Guardar ganancia</button>
</form>
<script>
    const botonTema = document.getElementById("boton-tema");

    function actualizarBotonTema() {
        const temaOscuro = document.documentElement.classList.contains("tema-oscuro");
        botonTema.textContent = temaOscuro ? "Tema claro" : "Tema oscuro";
        botonTema.setAttribute("aria-label", temaOscuro ? "Activar tema claro" : "Activar tema oscuro");
    }
    
    actualizarBotonTema();
    botonTema.addEventListener("click", () => {
        const temaOscuro = document.documentElement.classList.toggle("tema-oscuro");
        localStorage.setItem("tema", temaOscuro ? "oscuro" : "claro");
        actualizarBotonTema();
    });
</script>
</body>
</html>