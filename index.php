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

$error = "";

$nombre = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nombre === "" || $password === "") {

        $error = "Completa todos los campos.";

    } else {

        $sql = "SELECT nombre, pass
                FROM owners
                WHERE nombre = :nombre
                LIMIT 1";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            ":nombre" => $nombre
        ]);

        $admin = $stmt->fetch();

        if ($admin) {
            $stored = $admin["pass"] ?? '';

            $verified = false;

            if (is_string($stored) && (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$argon2') === 0)) {
                $verified = password_verify($password, $stored);
            } else {
                $verified = hash_equals((string)$stored, (string)$password);
            }

            if ($verified) {
                // Crear una nueva sesión
                session_regenerate_id(true);

                $_SESSION["admin_nombre"] = $admin["nombre"];
                $_SESSION["admin_logged_in"] = true;

                header("Location: admin.php");
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
    <title>Login administrador</title>
</head>

<body>

    <h1>Administrador</h1>

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