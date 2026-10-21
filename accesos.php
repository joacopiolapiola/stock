<?php
require_once __DIR__ . "/auth.php";
authRequerirRoles(["owner"]);
require_once __DIR__ . "/conexion.php";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$mensaje = "";
$tipo_mensaje = "";

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    if (!hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
        http_response_code(403);
        exit("La sesión del formulario venció. Recarga la página e inténtalo nuevamente.");
    }

    $nombre_usuario = trim($_POST["nombre_usuario"] ?? "");
    $contrasena = $_POST["contrasena"] ?? "";
    $rol = $_POST["rol"] ?? "";
    if (!preg_match('/^[A-Za-z0-9_.-]{3,20}$/D', $nombre_usuario) || strlen($contrasena) < 10 || strlen($contrasena) > 72 || !in_array($rol, ["rrhh", "administrador"], true)) {
        $mensaje = "Ingresa un usuario válido, una contraseña de 10 a 72 caracteres y un rol permitido.";
        $tipo_mensaje = "error";
    } else {
        try {
            $crear_usuario = $pdo->prepare("INSERT INTO owners (nombre, pass, rol, empleado_id) VALUES (:nombre, :pass, :rol, NULL)");
            $crear_usuario->execute([
                ":nombre" => $nombre_usuario,
                ":pass" => password_hash($contrasena, PASSWORD_DEFAULT),
                ":rol" => $rol,
            ]);
            header("Location: accesos.php?creado=1");
            exit;
        } catch (PDOException $error) {
            error_log($error->getMessage());
            $mensaje = "No se pudo crear la cuenta. Puede que el usuario ya exista.";
            $tipo_mensaje = "error";
        }
    }
}

if (($_GET["creado"] ?? "") === "1") {
    $mensaje = "La cuenta se creó correctamente.";
    $tipo_mensaje = "exito";
}

$cuentas = $pdo->query(
    "SELECT u.nombre, u.rol, e.nombre AS empleado
     FROM owners u
     LEFT JOIN empleados e ON e.id = u.empleado_id
     ORDER BY FIELD(u.rol, 'owner', 'rrhh', 'administrador', 'empleado'), u.nombre"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Accesos</title>
</head>
<body>
    <nav class="pestanas" aria-label="Secciones del panel">
        <a href="stock.php">Stock</a>
        <a href="presentismos.php">Presentismos</a>
        <a href="personal.php">Personal</a>
        <a href="accesos.php" aria-current="page">Accesos</a>
        <a class="cerrar-sesion" href="logout.php">Cerrar sesión</a>
    </nav>
    <h1>Accesos</h1>
    <?php if ($mensaje !== ""): ?>
        <p class="mensaje <?= $tipo_mensaje ?>" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, "UTF-8") ?></p>
    <?php endif; ?>
    <form method="post" class="formulario formulario-acceso">
        <h2>Crear cuenta de acceso</h2>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8") ?>">
        <label for="nombre_usuario">Usuario</label>
        <input type="text" id="nombre_usuario" name="nombre_usuario" minlength="3" maxlength="20" pattern="[A-Za-z0-9_.-]{3,20}" required autocomplete="username">
        <label for="contrasena">Contraseña inicial (mínimo 10 caracteres)</label>
        <input type="password" id="contrasena" name="contrasena" minlength="10" maxlength="72" required autocomplete="new-password">
        <label for="rol">Perfil</label>
        <select id="rol" name="rol" required>
            <option value="rrhh">RRHH</option>
            <option value="administrador">Administrador de stock</option>
        </select>
        <button type="submit">Crear cuenta</button>
    </form>
    <h2>Cuentas del sistema</h2>
    <div class="tabla-contenedor">
        <table>
            <thead><tr><th>Usuario</th><th>Perfil</th><th>Empleado vinculado</th></tr></thead>
            <tbody>
            <?php foreach ($cuentas as $cuenta): ?>
                <tr>
                    <td><?= htmlspecialchars($cuenta["nombre"], ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars(ucfirst($cuenta["rol"]), ENT_QUOTES, "UTF-8") ?></td>
                    <td><?= htmlspecialchars($cuenta["empleado"] ?? "-", ENT_QUOTES, "UTF-8") ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>