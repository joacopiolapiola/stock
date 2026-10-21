<?php
require_once __DIR__ . "/auth.php";
authAutenticar();

$rol_usuario = $_SESSION["admin_rol"];
$vistas_por_rol = [
    "owner" => ["stock", "presentismos", "personal"],
    "rrhh" => ["presentismos", "personal"],
    "administrador" => ["stock"],
    "empleado" => [],
];
$vistas_permitidas = $vistas_por_rol[$rol_usuario] ?? [];
$pestana_activa = $_GET["pestana"] ?? ($rol_usuario === "rrhh" ? "personal" : "stock");
if (!in_array($pestana_activa, $vistas_permitidas, true)) {
    http_response_code(403);
    exit("No tienes permiso para acceder a esta sección.");
}

$puede_stock = in_array($rol_usuario, ["owner", "administrador"], true);
$puede_personal = in_array($rol_usuario, ["owner", "rrhh"], true);
$permisos_accion = [
    "nuevo_empleado" => ["owner", "rrhh"],
    "editar_empleado" => ["owner", "rrhh"],
    "eliminar_empleado" => ["owner", "rrhh"],
    "registrar_presentismo" => ["owner"],
    "generar_liquidacion_pdf" => ["owner", "rrhh"],
    "nuevo_producto" => ["owner", "administrador"],
    "actualizar_ganancia" => ["owner", "administrador"],
    "registrar_movimiento" => ["owner", "administrador"],
];

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $accion_actual = $_POST["accion"] ?? "";
    if (!isset($permisos_accion[$accion_actual])) {
        http_response_code(400);
        exit("La acción solicitada no es válida.");
    }
    authRequerirRoles($permisos_accion[$accion_actual]);
}

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

require_once "conexion.php";

$mensaje = "";
$tipo_mensaje = "";
$accion_actual = $_SERVER["REQUEST_METHOD"] === "POST" ? ($_POST["accion"] ?? "") : "";
if ($accion_actual === "registrar_presentismo") {
    $pestana_activa = "presentismos";
} elseif (in_array($accion_actual, ["nuevo_empleado", "editar_empleado", "eliminar_empleado", "generar_liquidacion_pdf"], true) || isset($_GET["editar_empleado"])) {
    $pestana_activa = "personal";
}
if ($accion_actual !== "" && !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"] ?? "")) {
    http_response_code(403);
    exit("La sesión del formulario venció. Recarga la página e inténtalo nuevamente.");
}
$tabla_presentismos = $pdo->query("SHOW TABLES LIKE 'presentismos'")->fetchColumn() !== false;

if (($_SERVER["REQUEST_METHOD"] ?? "GET") === "POST") {
    $accion = $_POST["accion"] ?? "";

    try {
        $pdo->beginTransaction();

        if ($accion === "nuevo_empleado" || $accion === "editar_empleado") {
            $empleado_id = filter_var($_POST["empleado_id"] ?? "", FILTER_VALIDATE_INT);
            $dni = trim($_POST["dni"] ?? "");
            $nombre_usuario_empleado = trim($_POST["usuario_empleado"] ?? "");
            $contrasena_empleado = $_POST["contrasena_empleado"] ?? "";
            $nombre_empleado = trim($_POST["nombre_empleado"] ?? "");
            $rol = trim($_POST["rol"] ?? "");
            $convenio = trim($_POST["convenio"] ?? "");
            $categoria = trim($_POST["categoria"] ?? "");
            $remuneracion_base = filter_var($_POST["remuneracion_base"] ?? "", FILTER_VALIDATE_FLOAT);
            $contrasena_invalida = ($accion === "nuevo_empleado" && (strlen($contrasena_empleado) < 10 || strlen($contrasena_empleado) > 72))
                || ($accion === "editar_empleado" && $contrasena_empleado !== "" && (strlen($contrasena_empleado) < 10 || strlen($contrasena_empleado) > 72));

            if (!preg_match('/^[0-9]{7,8}$/D', $dni) || !preg_match('/^[A-Za-z0-9_.-]{3,20}$/D', $nombre_usuario_empleado) || $nombre_empleado === "" || mb_strlen($nombre_empleado) > 120 || $rol === "" || mb_strlen($rol) > 100 || mb_strlen($convenio) > 160 || mb_strlen($categoria) > 100 || $remuneracion_base === false || $remuneracion_base < 0 || $contrasena_invalida || ($accion === "editar_empleado" && ($empleado_id === false || $empleado_id < 1))) {
                throw new InvalidArgumentException("Revisa el DNI, el usuario (3 a 20 caracteres), la contraseña inicial y los datos del empleado.");
            }

            if ($accion === "editar_empleado") {
                $dni_existente = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE dni = :dni AND id <> :id");
                $dni_existente->execute([":dni" => $dni, ":id" => $empleado_id]);
            } else {
                $dni_existente = $pdo->prepare("SELECT COUNT(*) FROM empleados WHERE dni = :dni");
                $dni_existente->execute([":dni" => $dni]);
            }
            if ((int) $dni_existente->fetchColumn() > 0) {
                throw new InvalidArgumentException("Ese DNI ya está asociado a otro empleado.");
            }

            if ($accion === "editar_empleado") {
                $usuario_existente = $pdo->prepare("SELECT nombre FROM owners WHERE empleado_id = :empleado_id");
                $usuario_existente->execute([":empleado_id" => $empleado_id]);
                $cuenta_empleado_actual = $usuario_existente->fetchColumn();
                $usuario_ocupado = $pdo->prepare("SELECT COUNT(*) FROM owners WHERE nombre = :nombre AND (empleado_id IS NULL OR empleado_id <> :empleado_id)");
                $usuario_ocupado->execute([":nombre" => $nombre_usuario_empleado, ":empleado_id" => $empleado_id]);
            } else {
                $cuenta_empleado_actual = false;
                $usuario_ocupado = $pdo->prepare("SELECT COUNT(*) FROM owners WHERE nombre = :nombre");
                $usuario_ocupado->execute([":nombre" => $nombre_usuario_empleado]);
            }
            if ((int) $usuario_ocupado->fetchColumn() > 0) {
                throw new InvalidArgumentException("Ese nombre de usuario ya está en uso.");
            }
            if ($accion === "editar_empleado" && $cuenta_empleado_actual === false && strlen($contrasena_empleado) < 10) {
                throw new InvalidArgumentException("Este empleado aún no tiene acceso. Define una contraseña inicial de al menos 10 caracteres.");
            }

            if ($accion === "nuevo_empleado") {
                $guardar_empleado = $pdo->prepare(
                    "INSERT INTO empleados (dni, nombre, rol, convenio, categoria, remuneracion_base)
                     VALUES (:dni, :nombre, :rol, :convenio, :categoria, :remuneracion_base)"
                );
                $mensaje = "El empleado se agregó correctamente.";
            } else {
                $guardar_empleado = $pdo->prepare(
                    "UPDATE empleados
                     SET dni = :dni, nombre = :nombre, rol = :rol, convenio = :convenio,
                         categoria = :categoria, remuneracion_base = :remuneracion_base
                     WHERE id = :id"
                );
                $guardar_empleado->bindValue(":id", $empleado_id, PDO::PARAM_INT);
                $mensaje = "Los datos del empleado se actualizaron correctamente.";
            }

            $guardar_empleado->bindValue(":dni", $dni);
            $guardar_empleado->bindValue(":nombre", $nombre_empleado);
            $guardar_empleado->bindValue(":rol", $rol);
            $guardar_empleado->bindValue(":convenio", $convenio === "" ? null : $convenio);
            $guardar_empleado->bindValue(":categoria", $categoria === "" ? null : $categoria);
            $guardar_empleado->bindValue(":remuneracion_base", $remuneracion_base);
            $guardar_empleado->execute();

            $id_empleado_cuenta = $accion === "nuevo_empleado" ? (int) $pdo->lastInsertId() : (int) $empleado_id;
            if ($cuenta_empleado_actual !== false) {
                if ($contrasena_empleado === "") {
                    $guardar_cuenta = $pdo->prepare("UPDATE owners SET nombre = :nombre, rol = 'empleado' WHERE empleado_id = :empleado_id");
                    $guardar_cuenta->execute([":nombre" => $nombre_usuario_empleado, ":empleado_id" => $id_empleado_cuenta]);
                } else {
                    $guardar_cuenta = $pdo->prepare("UPDATE owners SET nombre = :nombre, pass = :pass, rol = 'empleado' WHERE empleado_id = :empleado_id");
                    $guardar_cuenta->execute([
                        ":nombre" => $nombre_usuario_empleado,
                        ":pass" => password_hash($contrasena_empleado, PASSWORD_DEFAULT),
                        ":empleado_id" => $id_empleado_cuenta,
                    ]);
                }
            } else {
                $guardar_cuenta = $pdo->prepare("INSERT INTO owners (nombre, pass, rol, empleado_id) VALUES (:nombre, :pass, 'empleado', :empleado_id)");
                $guardar_cuenta->execute([
                    ":nombre" => $nombre_usuario_empleado,
                    ":pass" => password_hash($contrasena_empleado, PASSWORD_DEFAULT),
                    ":empleado_id" => $id_empleado_cuenta,
                ]);
            }
            $pdo->commit();
            $tipo_mensaje = "exito";
        } elseif ($accion === "eliminar_empleado") {
            $empleado_id = filter_var($_POST["empleado_id"] ?? "", FILTER_VALIDATE_INT);
            if ($empleado_id === false || $empleado_id < 1) {
                throw new InvalidArgumentException("El empleado seleccionado no es válido.");
            }

            $eliminar_empleado = $pdo->prepare("DELETE FROM empleados WHERE id = :id");
            $eliminar_empleado->execute([":id" => $empleado_id]);
            if ($eliminar_empleado->rowCount() === 0) {
                throw new InvalidArgumentException("El empleado ya no existe.");
            }
            $pdo->commit();
            $mensaje = "El empleado y sus registros de ausencias se eliminaron.";
            $tipo_mensaje = "exito";
        } elseif ($accion === "registrar_presentismo") {
            if (!$tabla_presentismos) {
                throw new InvalidArgumentException("Falta instalar la tabla de presentismos. Importa migracion_personal.sql en la base de datos actual.");
            }

            $empleado_id = filter_var($_POST["empleado_id"] ?? "", FILTER_VALIDATE_INT);
            $fecha = $_POST["fecha"] ?? "";
            $estado = $_POST["estado"] ?? "";
            $afecta_sueldo = $_POST["afecta_sueldo"] ?? "";
            $fecha_valida = DateTime::createFromFormat("!Y-m-d", $fecha);

            $estados_validos = ["presente", "inasistencia", "licencia_paga", "licencia_no_paga"];
            if ($empleado_id === false || $empleado_id < 1 || !$fecha_valida || $fecha_valida->format("Y-m-d") !== $fecha || !in_array($estado, $estados_validos, true) || !in_array($afecta_sueldo, ["si", "no"], true)) {
                throw new InvalidArgumentException("Completa el empleado, la fecha y el estado con valores válidos.");
            }

            $verificar_presentismo = $pdo->prepare("SELECT COUNT(*) FROM presentismos WHERE empleado_id = :empleado_id AND fecha = :fecha");
            $verificar_presentismo->execute([":empleado_id" => $empleado_id, ":fecha" => $fecha]);
            if ((int) $verificar_presentismo->fetchColumn() > 0) {
                throw new InvalidArgumentException("Ya existe un presentismo cargado para ese empleado y fecha.");
            }

            $registrar_presentismo = $pdo->prepare(
                "INSERT INTO presentismos (empleado_id, fecha, estado, afecta_sueldo)
                 VALUES (:empleado_id, :fecha, :estado, :afecta_sueldo)"
            );
            $registrar_presentismo->execute([
                ":empleado_id" => $empleado_id,
                ":fecha" => $fecha,
                ":estado" => $estado,
                ":afecta_sueldo" => $estado !== "presente" && $afecta_sueldo === "si" ? 1 : 0,
            ]);

            if ($estado !== "presente") {
                $registrar_ausencia = $pdo->prepare(
                    "INSERT INTO ausencias (empleado_id, fecha, dias, tipo, afecta_sueldo)
                     VALUES (:empleado_id, :fecha, 1, :tipo, :afecta_sueldo)"
                );
                $registrar_ausencia->execute([
                    ":empleado_id" => $empleado_id,
                    ":fecha" => $fecha,
                    ":tipo" => $estado,
                    ":afecta_sueldo" => $afecta_sueldo === "si" ? 1 : 0,
                ]);
            }

            $pdo->commit();
            $mensaje = "El presentismo se registró correctamente.";
            $tipo_mensaje = "exito";
        } elseif ($accion === "generar_liquidacion_pdf") {
            if (!$tabla_presentismos || $pdo->query("SHOW TABLES LIKE 'ausencias'")->fetchColumn() === false) {
                throw new InvalidArgumentException("Instala las tablas de presentismos y ausencias antes de generar la constancia.");
            }

            $empleado_id = filter_var($_POST["empleado_id"] ?? "", FILTER_VALIDATE_INT);
            $periodo_liquidacion = trim($_POST["periodo_liquidacion"] ?? "");
            $monto_presentismo = filter_var($_POST["monto_presentismo"] ?? "0", FILTER_VALIDATE_FLOAT);
            $monto_productividad = filter_var($_POST["monto_productividad"] ?? "0", FILTER_VALIDATE_FLOAT);
            $detalle_productividad = trim($_POST["detalle_productividad"] ?? "");
            $periodo_valido = DateTime::createFromFormat("!Y-m", $periodo_liquidacion);

            if ($empleado_id === false || $empleado_id < 1 || !$periodo_valido || $periodo_valido->format("Y-m") !== $periodo_liquidacion || $monto_presentismo === false || $monto_presentismo < 0 || $monto_productividad === false || $monto_productividad < 0 || mb_strlen($detalle_productividad) > 120) {
                throw new InvalidArgumentException("Completa un empleado, período e importes válidos para la liquidación.");
            }

            $retenciones_recibo = $_POST["retenciones"] ?? [];
            if (!is_array($retenciones_recibo) || count($retenciones_recibo) > 8) {
                throw new InvalidArgumentException("Puedes cargar hasta ocho retenciones por constancia.");
            }
            $retenciones_validadas = [];
            $total_retenciones = 0.0;
            foreach ($retenciones_recibo as $retencion) {
                if (!is_array($retencion)) {
                    throw new InvalidArgumentException("Revisa los datos de las retenciones.");
                }
                $concepto = trim($retencion["concepto"] ?? "");
                $importe = trim($retencion["importe"] ?? "");
                if ($concepto === "" && $importe === "") {
                    continue;
                }
                $importe_validado = filter_var($importe, FILTER_VALIDATE_FLOAT);
                if ($concepto === "" || mb_strlen($concepto) > 80 || $importe_validado === false || $importe_validado < 0) {
                    throw new InvalidArgumentException("Cada retención debe tener un concepto y un importe válido no negativo.");
                }
                $retenciones_validadas[] = ["concepto" => $concepto, "importe" => (float) $importe_validado];
                $total_retenciones += (float) $importe_validado;
            }

            $consulta_empleado_recibo = $pdo->prepare("SELECT id, dni, nombre, rol, remuneracion_base FROM empleados WHERE id = :id");
            $consulta_empleado_recibo->execute([":id" => $empleado_id]);
            $empleado_recibo = $consulta_empleado_recibo->fetch();
            if (!$empleado_recibo) {
                throw new InvalidArgumentException("El empleado seleccionado no existe.");
            }

            $fecha_inicio = $periodo_liquidacion . "-01";
            $fecha_fin = (new DateTimeImmutable($fecha_inicio))->modify("last day of this month")->format("Y-m-d");
            $consulta_presentes_recibo = $pdo->prepare(
                "SELECT COUNT(*) FROM presentismos
                 WHERE empleado_id = :empleado_id AND fecha BETWEEN :fecha_inicio AND :fecha_fin AND estado = 'presente'"
            );
            $consulta_presentes_recibo->execute([
                ":empleado_id" => $empleado_id,
                ":fecha_inicio" => $fecha_inicio,
                ":fecha_fin" => $fecha_fin,
            ]);
            $dias_presentes_recibo = (int) $consulta_presentes_recibo->fetchColumn();

            $consulta_licencias_recibo = $pdo->prepare(
                "SELECT tipo, SUM(dias) AS dias,
                        SUM(CASE WHEN afecta_sueldo = 1 THEN dias ELSE 0 END) AS dias_a_descontar
                 FROM ausencias
                 WHERE empleado_id = :empleado_id AND fecha BETWEEN :fecha_inicio AND :fecha_fin
                 GROUP BY tipo ORDER BY tipo"
            );
            $consulta_licencias_recibo->execute([
                ":empleado_id" => $empleado_id,
                ":fecha_inicio" => $fecha_inicio,
                ":fecha_fin" => $fecha_fin,
            ]);
            $licencias_recibo = $consulta_licencias_recibo->fetchAll();
            $dias_a_descontar_recibo = array_sum(array_map(static fn($licencia) => (float) $licencia["dias_a_descontar"], $licencias_recibo));
            $sueldo_base_recibo = (float) $empleado_recibo["remuneracion_base"];
            $descuento_ausencias_recibo = min($sueldo_base_recibo, $sueldo_base_recibo / 30 * $dias_a_descontar_recibo);
            $total_haberes = $sueldo_base_recibo + (float) $monto_presentismo + (float) $monto_productividad;
            $total_deducciones = $descuento_ausencias_recibo + $total_retenciones;
            $neto_liquidacion = max(0, $total_haberes - $total_deducciones);

            require_once __DIR__ . "/pdf_constancia.php";
            $lineas_pdf = [
                ["tipo" => "titulo", "texto" => "CONSTANCIA ORIENTATIVA DE LIQUIDACION DE SUELDO"],
                ["tipo" => "texto", "texto" => "Periodo: " . $periodo_valido->format("m/Y")],
                ["tipo" => "texto", "texto" => "Fecha de emision: " . date("d/m/Y")],
                ["tipo" => "vacio"],
                ["tipo" => "seccion", "texto" => "DATOS DEL EMPLEADO"],
                ["tipo" => "texto", "texto" => "Nombre: " . $empleado_recibo["nombre"]],
                ["tipo" => "texto", "texto" => "DNI: " . ($empleado_recibo["dni"] ?: "Sin completar")],
                ["tipo" => "texto", "texto" => "Puesto: " . $empleado_recibo["rol"]],
                ["tipo" => "vacio"],
                ["tipo" => "seccion", "texto" => "ASISTENCIAS Y LICENCIAS DEL PERIODO"],
                ["tipo" => "texto", "texto" => "Dias presentes registrados: " . $dias_presentes_recibo],
            ];

            $etiquetas_licencias = [
                "inasistencia" => "Inasistencia",
                "licencia_paga" => "Licencia paga",
                "licencia_no_paga" => "Licencia no paga",
            ];
            if (!$licencias_recibo) {
                $lineas_pdf[] = ["tipo" => "texto", "texto" => "Licencias o inasistencias: sin registros"];
            } else {
                foreach ($licencias_recibo as $licencia) {
                    $tipo_licencia = $licencia["tipo"];
                    $etiqueta_licencia = $etiquetas_licencias[$tipo_licencia] ?? ucfirst(str_replace("_", " ", $tipo_licencia));
                    $lineas_pdf[] = ["tipo" => "texto", "texto" => $etiqueta_licencia . ": " . number_format((float) $licencia["dias"], 2, ",", ".") . " dia(s)"];
                }
            }

            $lineas_pdf[] = ["tipo" => "vacio"];
            $lineas_pdf[] = ["tipo" => "seccion", "texto" => "HABERES"];
            $lineas_pdf[] = ["tipo" => "texto", "texto" => "Sueldo base: $" . number_format($sueldo_base_recibo, 2, ",", ".")];
            $lineas_pdf[] = ["tipo" => "texto", "texto" => "Presentismo: $" . number_format((float) $monto_presentismo, 2, ",", ".")];
            if ((float) $monto_productividad > 0 || $detalle_productividad !== "") {
                $lineas_pdf[] = ["tipo" => "texto", "texto" => "Productividad" . ($detalle_productividad !== "" ? " (" . $detalle_productividad . ")" : "") . ": $" . number_format((float) $monto_productividad, 2, ",", ".")];
            }
            $lineas_pdf[] = ["tipo" => "total", "texto" => "Total haberes: $" . number_format($total_haberes, 2, ",", ".")];
            $lineas_pdf[] = ["tipo" => "vacio"];
            $lineas_pdf[] = ["tipo" => "seccion", "texto" => "RETENCIONES Y DESCUENTOS"];
            if ($dias_a_descontar_recibo > 0) {
                $lineas_pdf[] = ["tipo" => "texto", "texto" => "Descuento estimado por " . number_format($dias_a_descontar_recibo, 2, ",", ".") . " dia(s) que afectan el sueldo: $" . number_format($descuento_ausencias_recibo, 2, ",", ".")];
            }
            foreach ($retenciones_validadas as $retencion) {
                $lineas_pdf[] = ["tipo" => "texto", "texto" => $retencion["concepto"] . ": $" . number_format($retencion["importe"], 2, ",", ".")];
            }
            if ($dias_a_descontar_recibo <= 0 && !$retenciones_validadas) {
                $lineas_pdf[] = ["tipo" => "texto", "texto" => "Sin retenciones o descuentos cargados"];
            }
            $lineas_pdf[] = ["tipo" => "total", "texto" => "Total retenciones y descuentos: $" . number_format($total_deducciones, 2, ",", ".")];
            $lineas_pdf[] = ["tipo" => "vacio"];
            $lineas_pdf[] = ["tipo" => "total", "texto" => "NETO A COBRAR: $" . number_format($neto_liquidacion, 2, ",", ".")];
            $lineas_pdf[] = ["tipo" => "vacio"];
            $lineas_pdf[] = ["tipo" => "texto", "texto" => "Calculo orientativo. Verificar convenio, normativa, conceptos y retenciones antes de emitir un recibo legal."];

            $pdo->commit();
            $nombre_pdf = "constancia_liquidacion_" . preg_replace("/[^0-9]/", "", (string) $empleado_recibo["dni"]) . "_" . $periodo_liquidacion . ".pdf";
            $contenido_pdf = crearPdfConstancia($lineas_pdf);
            header("Content-Type: application/pdf");
            header("Content-Disposition: attachment; filename=\"" . $nombre_pdf . "\"");
            header("Content-Length: " . strlen($contenido_pdf));
            header("Cache-Control: private, no-store, max-age=0");
            echo $contenido_pdf;
            exit;
        } elseif ($accion === "nuevo_producto") {
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

$ganancia_esperada = 0.0;
$productos = [];
$estadisticas = [
    "productos_vendidos" => 0,
    "unidades_vendidas" => 0,
    "ingresos_ventas" => 0,
    "operaciones_venta" => 0,
];

if ($puede_stock) {
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
}

$tablas_personal = $puede_personal
    && $pdo->query("SHOW TABLES LIKE 'empleados'")->fetchColumn() !== false
    && $pdo->query("SHOW TABLES LIKE 'ausencias'")->fetchColumn() !== false;
$periodo = $_GET["periodo"] ?? date("Y-m");
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo)) {
    $periodo = date("Y-m");
}
$periodo_liquidacion = $_GET["periodo_liquidacion"] ?? $periodo;
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodo_liquidacion)) {
    $periodo_liquidacion = date("Y-m");
}
$empleado_liquidacion_id = filter_var($_GET["liquidacion_empleado"] ?? "", FILTER_VALIDATE_INT);
$empleado_liquidacion = null;
$dias_presentes_liquidacion = 0;
$licencias_liquidacion = [];
$descuento_ausencias_liquidacion = 0.0;
$empleado_editar = null;
$empleados = [];
$presentismos = [];

if ($tablas_personal) {
    $consulta_empleados = $pdo->prepare(
        "SELECT e.id, e.dni, e.nombre, e.rol, e.convenio, e.categoria, e.remuneracion_base,
                COALESCE(SUM(a.dias), 0) AS dias_ausencia,
                COALESCE(SUM(CASE WHEN a.afecta_sueldo = 1 THEN a.dias ELSE 0 END), 0) AS dias_a_descontar
         FROM empleados e
         LEFT JOIN ausencias a ON a.empleado_id = e.id AND DATE_FORMAT(a.fecha, '%Y-%m') = :periodo
         GROUP BY e.id, e.dni, e.nombre, e.rol, e.convenio, e.categoria, e.remuneracion_base
         ORDER BY e.nombre"
    );
    $consulta_empleados->execute([":periodo" => $periodo]);
    $empleados = $consulta_empleados->fetchAll();

    if ($tabla_presentismos) {
        $consulta_presentismos = $pdo->prepare(
            "SELECT p.fecha, e.nombre, p.estado, p.afecta_sueldo
             FROM presentismos p
             INNER JOIN empleados e ON e.id = p.empleado_id
             WHERE DATE_FORMAT(p.fecha, '%Y-%m') = :periodo
             ORDER BY p.fecha DESC, e.nombre"
        );
        $consulta_presentismos->execute([":periodo" => $periodo]);
        $presentismos = $consulta_presentismos->fetchAll();
    }

    if ($empleado_liquidacion_id !== false && $empleado_liquidacion_id !== null && $empleado_liquidacion_id > 0 && $tabla_presentismos) {
        $consulta_empleado_liquidacion = $pdo->prepare("SELECT id, dni, nombre, rol, remuneracion_base FROM empleados WHERE id = :id");
        $consulta_empleado_liquidacion->execute([":id" => $empleado_liquidacion_id]);
        $empleado_liquidacion = $consulta_empleado_liquidacion->fetch() ?: null;

        if ($empleado_liquidacion) {
            $fecha_inicio_liquidacion = $periodo_liquidacion . "-01";
            $fecha_fin_liquidacion = (new DateTimeImmutable($fecha_inicio_liquidacion))->modify("last day of this month")->format("Y-m-d");
            $consulta_dias_presentes = $pdo->prepare(
                "SELECT COUNT(*) FROM presentismos
                 WHERE empleado_id = :empleado_id AND fecha BETWEEN :fecha_inicio AND :fecha_fin AND estado = 'presente'"
            );
            $consulta_dias_presentes->execute([
                ":empleado_id" => $empleado_liquidacion_id,
                ":fecha_inicio" => $fecha_inicio_liquidacion,
                ":fecha_fin" => $fecha_fin_liquidacion,
            ]);
            $dias_presentes_liquidacion = (int) $consulta_dias_presentes->fetchColumn();

            $consulta_licencias = $pdo->prepare(
                "SELECT tipo, SUM(dias) AS dias,
                        SUM(CASE WHEN afecta_sueldo = 1 THEN dias ELSE 0 END) AS dias_a_descontar
                 FROM ausencias
                 WHERE empleado_id = :empleado_id AND fecha BETWEEN :fecha_inicio AND :fecha_fin
                 GROUP BY tipo ORDER BY tipo"
            );
            $consulta_licencias->execute([
                ":empleado_id" => $empleado_liquidacion_id,
                ":fecha_inicio" => $fecha_inicio_liquidacion,
                ":fecha_fin" => $fecha_fin_liquidacion,
            ]);
            $licencias_liquidacion = $consulta_licencias->fetchAll();
            $dias_a_descontar_liquidacion = array_sum(array_map(static fn($licencia) => (float) $licencia["dias_a_descontar"], $licencias_liquidacion));
            $descuento_ausencias_liquidacion = min((float) $empleado_liquidacion["remuneracion_base"], (float) $empleado_liquidacion["remuneracion_base"] / 30 * $dias_a_descontar_liquidacion);
        }
    }

    $empleado_editar_id = filter_input(INPUT_GET, "editar_empleado", FILTER_VALIDATE_INT);
    if ($empleado_editar_id !== false && $empleado_editar_id !== null && $empleado_editar_id > 0) {
        $consulta_editar = $pdo->prepare("SELECT e.id, e.dni, e.nombre, e.rol, e.convenio, e.categoria, e.remuneracion_base, u.nombre AS usuario_empleado FROM empleados e LEFT JOIN owners u ON u.empleado_id = e.id WHERE e.id = :id");
        $consulta_editar->execute([":id" => $empleado_editar_id]);
        $empleado_editar = $consulta_editar->fetch() ?: null;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de gestión</title>
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
<h1>Panel de gestión</h1>
<?php if ($mensaje !== ""): ?>
    <p class="mensaje <?= $tipo_mensaje ?>" role="alert"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<nav class="pestanas" aria-label="Secciones del panel">
    <?php if ($puede_stock): ?><a id="pestana-stock" href="stock.php" <?= $pestana_activa === 'stock' ? 'aria-current="page"' : '' ?>>Stock</a><?php endif; ?>
    <?php if ($puede_personal): ?><a id="pestana-presentismos" href="presentismos.php" <?= $pestana_activa === 'presentismos' ? 'aria-current="page"' : '' ?>>Presentismos</a><?php endif; ?>
    <?php if ($puede_personal): ?><a id="pestana-personal" href="personal.php" <?= $pestana_activa === 'personal' ? 'aria-current="page"' : '' ?>>Personal</a><?php endif; ?>
    <?php if ($rol_usuario === 'owner'): ?><a href="accesos.php">Accesos</a><?php endif; ?>
    <a class="cerrar-sesion" href="logout.php">Cerrar sesión</a>
</nav>

<?php if ($puede_stock): ?>
<section id="panel-stock" class="panel-pestana" role="tabpanel" aria-labelledby="pestana-stock" <?= $pestana_activa !== 'stock' ? 'hidden' : '' ?>>
<h2>Estadísticas de ventas</h2>
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
<div class="tabla-contenedor">
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
</div>

<section class="paneles-formulario">
    <form action="" method="post" class="formulario">
        <h2>Agregar producto</h2>
        <p class="ayuda">Crea un producto y define su stock inicial.</p>
        <input type="hidden" name="accion" value="nuevo_producto">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
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
        <label for="sujeto">Cliente o proveedor <span>(opcional)</span></label>
        <input type="text" name="sujeto" id="sujeto" maxlength="20">
        <label for="cantidad">Cantidad</label>
        <input type="number" name="cantidad" id="cantidad" min="1" step="1" required>
        <label for="subtotal">Subtotal</label>
        <input type="number" name="subtotal" id="subtotal" min="0" step="1" required>
        <button type="submit">Registrar movimiento</button>
    </form>
</section>

<form action="" method="post" class="formulario formulario-ganancia">
    <h2>Ganancia esperada</h2>
    <p class="ayuda">Define el porcentaje que se suma al precio registrado para sugerir el precio de venta.</p>
    <input type="hidden" name="accion" value="actualizar_ganancia">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
    <label for="ganancia">Ganancia (%)</label>
    <input type="number" name="ganancia" id="ganancia" min="0" step="0.01" value="<?= htmlspecialchars((string) $ganancia_esperada, ENT_QUOTES, 'UTF-8') ?>" required>
    <button type="submit">Guardar ganancia</button>
</form>
</section>
<?php endif; ?>

<?php if ($puede_personal): ?>
<section id="panel-personal" class="panel-pestana" role="tabpanel" aria-labelledby="pestana-personal" <?= $pestana_activa !== 'personal' ? 'hidden' : '' ?>>
    <h2 id="titulo-personal">Gestión de personal</h2>
    <p class="aviso-legal"><strong>Estimación orientativa, no liquidación legal:</strong> el sistema toma la remuneración mensual base actualmente cargada y, solo para los días que marques como afectación salarial, estima una deducción de base ÷ 30 × días. En períodos anteriores también usa la base actual; no conserva historial salarial. La clasificación y el salario mínimo aplicable dependen de la legislación vigente, convenio colectivo, categoría, jornada y situación de cada ausencia. Verifica esos datos con un profesional antes de liquidar.</p>
    <?php if (!$tablas_personal): ?>
        <p class="mensaje error">Falta instalar las tablas de personal. Importa una vez el archivo <strong>migracion_personal.sql</strong> en la base de datos actual.</p>
    <?php else: ?>
        <form action="admin.php" method="get" class="selector-periodo">
            <input type="hidden" name="pestana" value="personal">
            <label for="periodo">Período de ausencias y estimación</label>
            <input type="month" id="periodo" name="periodo" value="<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit">Consultar período</button>
        </form>

        <?php if ($empleado_editar): ?>
            <form action="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>" method="post" class="formulario formulario-edicion">
                <h3>Editar empleado</h3>
                <input type="hidden" name="accion" value="editar_empleado">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="empleado_id" value="<?= (int) $empleado_editar['id'] ?>">
                <label for="editar_dni">DNI</label>
                <input type="text" id="editar_dni" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" required value="<?= htmlspecialchars($empleado_editar['dni'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_usuario_empleado">Usuario de acceso</label>
                <input type="text" id="editar_usuario_empleado" name="usuario_empleado" minlength="3" maxlength="20" pattern="[A-Za-z0-9_.-]{3,20}" required value="<?= htmlspecialchars($empleado_editar['usuario_empleado'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_contrasena_empleado">Nueva contraseña (opcional)</label>
                <input type="password" id="editar_contrasena_empleado" name="contrasena_empleado" minlength="10" maxlength="72" autocomplete="new-password">
                <label for="editar_nombre">Nombre y apellido</label>
                <input id="editar_nombre" name="nombre_empleado" maxlength="120" required value="<?= htmlspecialchars($empleado_editar['nombre'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_rol">Rol</label>
                <input id="editar_rol" name="rol" maxlength="100" required value="<?= htmlspecialchars($empleado_editar['rol'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_convenio">Convenio colectivo</label>
                <input id="editar_convenio" name="convenio" maxlength="160" value="<?= htmlspecialchars($empleado_editar['convenio'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_categoria">Categoría</label>
                <input id="editar_categoria" name="categoria" maxlength="100" value="<?= htmlspecialchars($empleado_editar['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                <label for="editar_remuneracion">Remuneración mensual base (ARS)</label>
                <input type="number" id="editar_remuneracion" name="remuneracion_base" min="0" step="0.01" required value="<?= htmlspecialchars((string) $empleado_editar['remuneracion_base'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Guardar cambios</button>
                <a href="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>&amp;pestana=personal">Cancelar</a>
            </form>
        <?php endif; ?>

        <div class="tabla-contenedor">
            <table>
                <thead>
                    <tr><th>Empleado</th><th>DNI</th><th>Rol</th><th>Convenio / categoría</th><th>Base mensual</th><th>Días de ausencia</th><th>Días marcados para descuento</th><th>Estimación orientativa</th><th>Acciones</th></tr>
                </thead>
                <tbody>
                <?php foreach ($empleados as $empleado):
                    $base_mensual = (float) $empleado['remuneracion_base'];
                    $dias_a_descontar = (float) $empleado['dias_a_descontar'];
                    $estimacion = max(0, $base_mensual - ($base_mensual / 30 * $dias_a_descontar));
                ?>
                    <tr>
                        <td><?= htmlspecialchars($empleado['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?php if (!empty($empleado['dni'])): ?><?= htmlspecialchars($empleado['dni'], ENT_QUOTES, 'UTF-8') ?><?php else: ?><span class="dni-pendiente">Pendiente</span><?php endif; ?></td>
                        <td><?= htmlspecialchars($empleado['rol'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(trim(($empleado['convenio'] ?? '') . ' / ' . ($empleado['categoria'] ?? ''), ' /'), ENT_QUOTES, 'UTF-8') ?: 'Sin especificar' ?></td>
                        <td>$<?= number_format($base_mensual, 2, ',', '.') ?></td>
                        <td><?= number_format((float) $empleado['dias_ausencia'], 2, ',', '.') ?></td>
                        <td><?= number_format($dias_a_descontar, 2, ',', '.') ?></td>
                        <td>$<?= number_format($estimacion, 2, ',', '.') ?></td>
                        <td class="acciones-empleado">
                            <a href="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>&amp;pestana=personal&amp;editar_empleado=<?= (int) $empleado['id'] ?>">Editar</a>
                            <form method="post" onsubmit="return confirm('¿Eliminar este empleado y todas sus ausencias?');">
                                <input type="hidden" name="accion" value="eliminar_empleado">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="empleado_id" value="<?= (int) $empleado['id'] ?>">
                                <button type="submit" class="boton-peligro">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$empleados): ?><tr><td colspan="9">Todavía no hay empleados cargados.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <section class="paneles-formulario panel-personal-formulario">
            <form action="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>" method="post" class="formulario">
                <h3>Agregar empleado</h3>
                <input type="hidden" name="accion" value="nuevo_empleado">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <label for="nuevo_dni">DNI</label>
                <input type="text" id="nuevo_dni" name="dni" inputmode="numeric" pattern="[0-9]{7,8}" maxlength="8" required>
                <label for="nuevo_usuario_empleado">Usuario de acceso</label>
                <input type="text" id="nuevo_usuario_empleado" name="usuario_empleado" minlength="3" maxlength="20" pattern="[A-Za-z0-9_.-]{3,20}" required>
                <label for="nuevo_contrasena_empleado">Contraseña inicial (mínimo 10 caracteres)</label>
                <input type="password" id="nuevo_contrasena_empleado" name="contrasena_empleado" minlength="10" maxlength="72" autocomplete="new-password" required>
                <label for="nuevo_nombre">Nombre y apellido</label>
                <input id="nuevo_nombre" name="nombre_empleado" maxlength="120" required>
                <label for="nuevo_rol">Rol</label>
                <input id="nuevo_rol" name="rol" maxlength="100" required>
                <label for="nuevo_convenio">Convenio colectivo</label>
                <input id="nuevo_convenio" name="convenio" maxlength="160" placeholder="Ej.: indicar el CCT aplicable">
                <label for="nuevo_categoria">Categoría</label>
                <input id="nuevo_categoria" name="categoria" maxlength="100">
                <label for="nuevo_remuneracion">Remuneración mensual base (ARS)</label>
                <input type="number" id="nuevo_remuneracion" name="remuneracion_base" min="0" step="0.01" required>
                <button type="submit">Agregar empleado</button>
            </form>

        </section>

        <section class="constancia-sueldo" id="constancia-sueldo" aria-labelledby="titulo-constancia">
            <h3 id="titulo-constancia">Constancia de liquidación de sueldo</h3>
            <p class="aviso-legal">Documento orientativo. Verifica convenio, normativa y conceptos antes de emitir un recibo legal.</p>
            <form action="admin.php#constancia-sueldo" method="get" class="formulario formulario-consulta-liquidacion">
                <input type="hidden" name="pestana" value="personal">
                <label for="liquidacion_empleado">Empleado</label>
                <select id="liquidacion_empleado" name="liquidacion_empleado" required>
                    <option value="">Selecciona un empleado</option>
                    <?php foreach ($empleados as $empleado): ?>
                        <option value="<?= (int) $empleado['id'] ?>" <?= (int) $empleado_liquidacion_id === (int) $empleado['id'] ? 'selected' : '' ?>><?= htmlspecialchars($empleado['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <label for="periodo_liquidacion">Período a liquidar</label>
                <input type="month" id="periodo_liquidacion" name="periodo_liquidacion" required value="<?= htmlspecialchars($periodo_liquidacion, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Consultar período</button>
            </form>

            <?php if ($empleado_liquidacion): ?>
                <div class="resumen-liquidacion">
                    <h4><?= htmlspecialchars($empleado_liquidacion['nombre'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($periodo_liquidacion, ENT_QUOTES, 'UTF-8') ?></h4>
                    <p>DNI: <?= htmlspecialchars($empleado_liquidacion['dni'] ?? 'Sin completar', ENT_QUOTES, 'UTF-8') ?> · Sueldo base: $<?= number_format((float) $empleado_liquidacion['remuneracion_base'], 2, ',', '.') ?></p>
                    <p>Días presentes registrados: <?= (int) $dias_presentes_liquidacion ?></p>
                    <h4>Licencias e inasistencias</h4>
                    <?php if ($licencias_liquidacion): ?>
                        <ul>
                        <?php foreach ($licencias_liquidacion as $licencia): ?>
                            <?php
                                $etiquetas_licencias = [
                                    'inasistencia' => 'Inasistencia',
                                    'licencia_paga' => 'Licencia paga',
                                    'licencia_no_paga' => 'Licencia no paga',
                                ];
                                $etiqueta_licencia = $etiquetas_licencias[$licencia['tipo']] ?? ucfirst(str_replace('_', ' ', $licencia['tipo']));
                            ?>
                            <li><?= htmlspecialchars($etiqueta_licencia, ENT_QUOTES, 'UTF-8') ?>: <?= number_format((float) $licencia['dias'], 2, ',', '.') ?> día(s)</li>
                        <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p>Sin licencias ni inasistencias registradas.</p>
                    <?php endif; ?>
                    <p>Descuento estimado por días que afectan el sueldo: $<?= number_format($descuento_ausencias_liquidacion, 2, ',', '.') ?></p>
                </div>

                <form action="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>&amp;pestana=personal" method="post" class="formulario formulario-liquidacion-pdf">
                    <h4>Conceptos de la liquidación</h4>
                    <input type="hidden" name="accion" value="generar_liquidacion_pdf">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="empleado_id" value="<?= (int) $empleado_liquidacion['id'] ?>">
                    <input type="hidden" name="periodo_liquidacion" value="<?= htmlspecialchars($periodo_liquidacion, ENT_QUOTES, 'UTF-8') ?>">
                    <label for="monto_presentismo">Presentismo (ARS)</label>
                    <input type="number" id="monto_presentismo" name="monto_presentismo" min="0" step="0.01" value="0.00" required>
                    <fieldset class="grupo-productividad">
                        <legend>Productividad (opcional)</legend>
                        <label for="detalle_productividad">Concepto</label>
                        <input type="text" id="detalle_productividad" name="detalle_productividad" maxlength="120" placeholder="Ej.: objetivo mensual">
                        <label for="monto_productividad">Importe (ARS)</label>
                        <input type="number" id="monto_productividad" name="monto_productividad" min="0" step="0.01" value="0.00">
                    </fieldset>
                    <fieldset class="grupo-retenciones">
                        <legend>Retenciones</legend>
                        <div id="retenciones-lista">
                            <div class="retencion-fila">
                                <div>
                                    <label for="retencion-concepto-0">Concepto</label>
                                    <input type="text" id="retencion-concepto-0" name="retenciones[0][concepto]" maxlength="80">
                                </div>
                                <div>
                                    <label for="retencion-importe-0">Importe (ARS)</label>
                                    <input type="number" id="retencion-importe-0" name="retenciones[0][importe]" min="0" step="0.01">
                                </div>
                                <button type="button" class="boton-quitar-retencion" aria-label="Quitar retención" disabled>Quitar</button>
                            </div>
                        </div>
                        <button type="button" id="agregar-retencion">Agregar retención</button>
                    </fieldset>
                    <button type="submit">Descargar constancia PDF</button>
                </form>
            <?php elseif ($empleado_liquidacion_id !== false && $empleado_liquidacion_id !== null && $empleado_liquidacion_id > 0): ?>
                <p class="mensaje error">No se encontró el empleado seleccionado para consultar su liquidación.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($puede_personal): ?>
<section id="panel-presentismos" class="panel-pestana" role="tabpanel" aria-labelledby="pestana-presentismos" <?= $pestana_activa !== 'presentismos' ? 'hidden' : '' ?>>
    <h2>Presentismos del período <?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?></h2>
    <?php if (!$tablas_personal || !$tabla_presentismos): ?>
        <p class="mensaje error">Falta instalar las tablas de personal y presentismos. Importa una vez el archivo <strong>migracion_personal.sql</strong> en la base de datos actual.</p>
    <?php else: ?>
        <?php if ($rol_usuario === "owner"): ?>
        <form action="admin.php?periodo=<?= htmlspecialchars($periodo, ENT_QUOTES, 'UTF-8') ?>&amp;pestana=presentismos" method="post" class="formulario formulario-presentismo">
            <h3>Cargar asistencia diaria</h3>
            <input type="hidden" name="accion" value="registrar_presentismo">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="presentismo_empleado">Empleado</label>
            <select id="presentismo_empleado" name="empleado_id" required>
                <option value="">Selecciona un empleado</option>
                <?php foreach ($empleados as $empleado): ?>
                    <option value="<?= (int) $empleado['id'] ?>"><?= htmlspecialchars($empleado['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
            <label for="presentismo_fecha">Fecha</label>
            <input type="date" id="presentismo_fecha" name="fecha" required>
            <label for="presentismo_estado">Estado</label>
            <select id="presentismo_estado" name="estado" required>
                <option value="presente">Presente</option>
                <option value="inasistencia">Ausente</option>
                <option value="licencia_paga">Licencia paga</option>
                <option value="licencia_no_paga">Licencia no paga</option>
            </select>
            <label for="presentismo_afecta_sueldo">¿Afecta la remuneración?</label>
            <select id="presentismo_afecta_sueldo" name="afecta_sueldo" required>
                <option value="no">No</option>
                <option value="si">Sí, ya verificado</option>
            </select>
            <button type="submit">Guardar presentismo</button>
        </form>
        <?php endif; ?>

        <h3>Registros del período</h3>
        <div class="tabla-contenedor">
            <table>
                <thead><tr><th>Fecha</th><th>Empleado</th><th>Estado</th><th>Afecta remuneración</th></tr></thead>
                <tbody>
                <?php foreach ($presentismos as $presentismo): ?>
                    <tr>
                        <td><?= htmlspecialchars($presentismo['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($presentismo['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $presentismo['estado'])), ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= (int) $presentismo['afecta_sueldo'] === 1 ? 'Sí' : 'No' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$presentismos): ?><tr><td colspan="4">No hay presentismos cargados para este período.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<script>
    const listaRetenciones = document.getElementById("retenciones-lista");
    const botonAgregarRetencion = document.getElementById("agregar-retencion");

    if (listaRetenciones && botonAgregarRetencion) {
        function actualizarRetenciones() {
            const filas = listaRetenciones.querySelectorAll(".retencion-fila");
            filas.forEach((fila) => {
                fila.querySelector(".boton-quitar-retencion").disabled = filas.length === 1;
            });
            botonAgregarRetencion.disabled = filas.length >= 8;
        }

        listaRetenciones.addEventListener("click", (evento) => {
            const botonQuitar = evento.target.closest(".boton-quitar-retencion");
            if (botonQuitar && listaRetenciones.querySelectorAll(".retencion-fila").length > 1) {
                botonQuitar.closest(".retencion-fila").remove();
                actualizarRetenciones();
            }
        });

        botonAgregarRetencion.addEventListener("click", () => {
            const indice = listaRetenciones.querySelectorAll(".retencion-fila").length;
            if (indice >= 8) return;

            const fila = document.createElement("div");
            fila.className = "retencion-fila";
            const grupoConcepto = document.createElement("div");
            const etiquetaConcepto = document.createElement("label");
            etiquetaConcepto.htmlFor = `retencion-concepto-${indice}`;
            etiquetaConcepto.textContent = "Concepto";
            const concepto = document.createElement("input");
            concepto.type = "text";
            concepto.id = `retencion-concepto-${indice}`;
            concepto.name = `retenciones[${indice}][concepto]`;
            concepto.maxLength = 80;
            grupoConcepto.append(etiquetaConcepto, concepto);

            const grupoImporte = document.createElement("div");
            const etiquetaImporte = document.createElement("label");
            etiquetaImporte.htmlFor = `retencion-importe-${indice}`;
            etiquetaImporte.textContent = "Importe (ARS)";
            const importe = document.createElement("input");
            importe.type = "number";
            importe.id = `retencion-importe-${indice}`;
            importe.name = `retenciones[${indice}][importe]`;
            importe.min = "0";
            importe.step = "0.01";
            grupoImporte.append(etiquetaImporte, importe);

            const quitar = document.createElement("button");
            quitar.type = "button";
            quitar.className = "boton-quitar-retencion";
            quitar.setAttribute("aria-label", "Quitar retención");
            quitar.textContent = "Quitar";
            fila.append(grupoConcepto, grupoImporte, quitar);
            listaRetenciones.append(fila);
            actualizarRetenciones();
        });
    }

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