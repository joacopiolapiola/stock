<?php
require_once __DIR__ . "/auth.php";
authRequerirRoles(["empleado"]);
require_once __DIR__ . "/conexion.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$empleado_id = (int) $_SESSION["empleado_id"];
$consulta_empleado = $pdo->prepare("SELECT nombre, dni FROM empleados WHERE id = :id");
$consulta_empleado->execute([":id" => $empleado_id]);
$empleado = $consulta_empleado->fetch();
if (!$empleado) {
    $_SESSION = [];
    session_destroy();
    header("Location: index.php");
    exit;
}

$mensaje = "";
$tipo_mensaje = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("La sesión del formulario venció. Recarga la página e inténtalo nuevamente.");
    }

    $fecha = $_POST["fecha"] ?? "";
    $estado = $_POST["estado"] ?? "";
    $fecha_valida = DateTime::createFromFormat("!Y-m-d", $fecha);
    if (!$fecha_valida || $fecha_valida->format("Y-m-d") !== $fecha || $fecha > date("Y-m-d") || !in_array($estado, ["presente", "inasistencia"], true)) {
        $mensaje = "Selecciona una fecha válida y el estado de tu asistencia.";
        $tipo_mensaje = "error";
    } else {
        try {
            $pdo->beginTransaction();
            $duplicado = $pdo->prepare("SELECT COUNT(*) FROM presentismos WHERE empleado_id = :empleado_id AND fecha = :fecha");
            $duplicado->execute([":empleado_id" => $empleado_id, ":fecha" => $fecha]);
            if ((int) $duplicado->fetchColumn() > 0) {
                throw new InvalidArgumentException("Ya registraste tu asistencia para esa fecha.");
            }

            $registrar = $pdo->prepare("INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo) VALUES (:empleado_id, :fecha, :estado, 0)");
            $registrar->execute([":empleado_id" => $empleado_id, ":fecha" => $fecha, ":estado" => $estado]);

            if ($estado === "inasistencia") {
                $registrar_ausencia = $pdo->prepare("INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo) VALUES (:empleado_id, :fecha, 1, 'inasistencia', 0)");
                $registrar_ausencia->execute([":empleado_id" => $empleado_id, ":fecha" => $fecha]);
            }

            $pdo->commit();
            $mensaje = "Tu asistencia quedó registrada.";
            $tipo_mensaje = "exito";
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
            $mensaje = "No se pudo registrar la asistencia. Comprueba si ya existe para esa fecha.";
            $tipo_mensaje = "error";
        }
    }
}

$historial = $pdo->prepare("SELECT fecha, estado FROM presentismos WHERE empleado_id = :empleado_id ORDER BY fecha DESC LIMIT 31");
$historial->execute([":empleado_id" => $empleado_id]);
$registros = $historial->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Mi presentismo</title>
</head>
<body>
    <nav class="pestanas" aria-label="Cuenta">
        <span>Mi presentismo</span>
        <a class="cerrar-sesion" href="logout.php">Cerrar sesión</a>
    </nav>
    <h1>Mi presentismo</h1>
    <p><?= htmlspecialchars($empleado["nombre"], ENT_QUOTES, "UTF-8") ?> · DNI <?= htmlspecialchars($empleado["dni"] ?? "Sin completar", ENT_QUOTES, "UTF-8") ?></p>
    <?php if ($mensaje !== ""): ?>
        <p class="mensaje <?= $tipo_mensaje ?>" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
    <form method="post" class="formulario formulario-presentismo propio-presentismo">
        <h2>Marcar asistencia</h2>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
        <label for="fecha">Fecha</label>
        <input type="date" id="fecha" name="fecha" max="<?= date("Y-m-d") ?>" required>
        <label for="estado">Estado</label>
        <select id="estado" name="estado" required>
            <option value="presente">Presente</option>
            <option value="inasistencia">Ausente</option>
        </select>
        <button type="submit">Guardar asistencia</button>
    </form>
    <h2>Mis últimos registros</h2>
    <div class="tabla-contenedor">
        <table>
            <thead><tr><th>Fecha</th><th>Estado</th></tr></thead>
            <tbody>
            <?php foreach ($registros as $registro): ?>
                <tr>
                    <td><?= htmlspecialchars($registro["fecha"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= $registro["estado"] === "presente" ? "Presente" : "Ausente" ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$registros): ?><tr><td colspan="2">Todavía no tienes registros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</body>
</html>