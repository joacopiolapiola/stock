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

if (isset($_SESSION["usuario_rol"]) && $_SESSION["usuario_rol"] !== "") {
    header("Location: admin.php");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST["nombre"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($nombre === "" || $password === "") {
        $error = "Completa todos los campos.";
    } else {
        $usuario = null;

        $stmt = $pdo->prepare("SELECT id, nombre, nombre_completo, pass, rol FROM usuarios WHERE nombre = :nombre AND activo = 1 LIMIT 1");
        $stmt->execute([":nombre" => $nombre]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $stmt = $pdo->prepare("SELECT nombre, pass FROM owners WHERE nombre = :nombre LIMIT 1");
            $stmt->execute([":nombre" => $nombre]);
            $legacy = $stmt->fetch();

            if ($legacy) {
                $usuario = [
                    "id" => null,
                    "nombre" => $legacy["nombre"],
                    "nombre_completo" => $legacy["nombre"],
                    "pass" => $legacy["pass"],
                    "rol" => "admin",
                ];
            }
        }

        if ($usuario) {
            $stored = $usuario["pass"] ?? "";
            $verified = false;

            if (is_string($stored) && (strpos($stored, '$2y$') === 0 || strpos($stored, '$2a$') === 0 || strpos($stored, '$argon2') === 0)) {
                $verified = password_verify($password, $stored);
            } else {
                $verified = hash_equals((string) $stored, (string) $password);
            }

            if ($verified) {
                session_regenerate_id(true);
                $_SESSION["usuario_id"] = $usuario["id"];
                $_SESSION["usuario_nombre"] = $usuario["nombre_completo"] ?: $usuario["nombre"];
                $_SESSION["usuario_rol"] = $usuario["rol"] ?? "admin";
                $_SESSION["admin_logged_in"] = true;

                header("Location: admin.php");
                exit;
            }
        }

        $error = "Nombre o contraseña incorrectos.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <title>Login del negocio</title>
</head>
<body>
    <main class="login-wrapper">
        <section class="login-card">
            <h1>Acceso al sistema</h1>
            <p class="subtitulo">Inicia sesión para gestionar el emprendimiento.</p>

            <?php if ($error !== ""): ?>
                <p class="mensaje error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form action="index.php" method="POST" class="formulario login-form">
                <label for="nombre">Usuario</label>
                <input type="text" id="nombre" name="nombre" autocomplete="username" required value="<?= htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8') ?>">

                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>

                <button type="submit">Iniciar sesión</button>
            </form>
        </section>
    </main>
</body>
</html>