<?php
require_once __DIR__ . "/auth.php";
authRequerirRoles(["owner", "rrhh"]);
$_GET["pestana"] = "personal";
require __DIR__ . "/admin.php";