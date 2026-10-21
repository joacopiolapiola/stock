<?php

function authRolesValidos(): array
{
    return ["owner", "rrhh", "empleado", "administrador"];
}

function authAutenticar(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $rol = $_SESSION["admin_rol"] ?? "";
    if (empty($_SESSION["admin_logged_in"]) || !in_array($rol, authRolesValidos(), true)) {
        header("Location: index.php");
        exit;
    }

    if ($rol === "empleado" && (int) ($_SESSION["empleado_id"] ?? 0) < 1) {
        $_SESSION = [];
        session_destroy();
        header("Location: index.php");
        exit;
    }
}

function authRequerirRoles(array $rolesPermitidos): void
{
    authAutenticar();
    if (!in_array($_SESSION["admin_rol"], $rolesPermitidos, true)) {
        http_response_code(403);
        exit("No tienes permiso para acceder a esta sección.");
    }
}

function authInicioRol(string $rol): string
{
    return match ($rol) {
        "owner", "administrador" => "stock.php",
        "rrhh" => "personal.php",
        "empleado" => "mi_presentismo.php",
        default => "index.php",
    };
}