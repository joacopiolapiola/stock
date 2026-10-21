<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

require_once "conexion.php";
require_once "auth.php";

$roles_validos = authRolesValidos();
$rol_sesion = $_SESSION["admin_rol"] ?? "";
if (!empty($_SESSION["admin_logged_in"]) && in_array($rol_sesion, $roles_validos, true)) {
    header("Location: " . authInicioRol($rol_sesion));
    exit;
}

$error = "";

$nombre = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nombre === "" || $password === "") {

        $error = "Completa todos los campos.";

    } else {

        $sql = "SELECT u.nombre, u.pass, u.rol, u.empleado_id, e.id AS empleado_vigente
            FROM owners u
            LEFT JOIN empleados e ON e.id = u.empleado_id
            WHERE u.nombre = :nombre
            LIMIT 1";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":nombre" => $nombre
        ]);

        $usuario = $stmt->fetch();

        if ($usuario) {
            $stored = $usuario["pass"] ?? '';

            $verified = false;

            if (is_string($stored) && (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$argon2') === 0)) {
                $verified = password_verify($password, $stored);
            } else {
                $verified = hash_equals((string)$stored, (string)$password);
            }

            $empleado_vigente = $usuario["rol"] !== "empleado" || $usuario["empleado_vigente"] !== null;
            if ($verified && $empleado_vigente && in_array($usuario["rol"], $roles_validos, true)) {
                session_regenerate_id(true);

                $_SESSION["admin_nombre"] = $usuario["nombre"];
                $_SESSION["admin_rol"] = $usuario["rol"];
                $_SESSION["empleado_id"] = $usuario["empleado_id"] === null ? null : (int) $usuario["empleado_id"];
                $_SESSION["admin_logged_in"] = true;

                header("Location: " . authInicioRol($usuario["rol"]));
                exit;
            } else {
                $error = "Nombre o contraseña incorrectos.";
            }
        } else {
            $error = "Nombre o contraseña incorrectos.";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="stylesheet" href="style.css">
    <title>Iniciar sesión</title>
</head>

<body>

    <h1>Iniciar sesión</h1>

    <?php if ($error !== ""): ?>

        <p class="mensaje-error">
            <?= htmlspecialchars($error) ?>
        </p>

    <?php endif; ?>

<article  class="producto tarjeta-producto">
    <form action="index.php" method="POST">

        <div>

            <label for="nombre">
                Nombre
            </label>

            <input
                type="text"
                id="nombre"
                name="nombre"
                autocomplete="username"
                required
                value="<?= htmlspecialchars($nombre) ?>"
            >

        </div>

        <br>

        <div>

            <label for="password">
                Contraseña
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >

        </div>

        <br>

        <button type="submit">
            Iniciar sesión
        </button>

    </form>
</article>
</body>

</html>