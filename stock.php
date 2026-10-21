<?php
require_once __DIR__ . "/auth.php";
authRequerirRoles(["owner", "administrador"]);
$_GET["pestana"] = "stock";
require __DIR__ . "/admin.php";