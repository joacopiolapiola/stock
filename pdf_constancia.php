<?php

function crearPdfConstancia(array $lineas): string
{
    $contenido = "";
    $posicion_y = 790;

    foreach ($lineas as $linea) {
        $tipo = $linea["tipo"] ?? "texto";
        $texto = trim((string) ($linea["texto"] ?? ""));
        $ancho = $tipo === "titulo" ? 62 : 82;
        $fragmentos = $texto === "" ? [""] : explode("\n", wordwrap($texto, $ancho, "\n", true));
        $tamano = $tipo === "titulo" ? 16 : ($tipo === "seccion" ? 12 : 10);
        $fuente = in_array($tipo, ["titulo", "seccion", "total"], true) ? "F2" : "F1";
        $espacio = $tipo === "vacio" ? 8 : ($tipo === "titulo" ? 26 : 17);

        if ($tipo === "vacio") {
            $posicion_y -= $espacio;
            continue;
        }

        foreach ($fragmentos as $fragmento) {
            if ($posicion_y < 45) {
                break 2;
            }

            $texto_pdf = iconv("UTF-8", "Windows-1252//TRANSLIT//IGNORE", $fragmento);
            if ($texto_pdf === false) {
                $texto_pdf = "";
            }
            $texto_pdf = str_replace(["\\", "(", ")"], ["\\\\", "\\(", "\\)"], $texto_pdf);

            if ($tipo === "titulo") {
                $contenido .= "0.12 0.29 0.31 rg\n";
            } elseif ($tipo === "total") {
                $contenido .= "0.08 0.35 0.28 rg\n";
            } else {
                $contenido .= "0 0 0 rg\n";
            }

            $contenido .= "BT /$fuente $tamano Tf 1 0 0 1 52 $posicion_y Tm ($texto_pdf) Tj ET\n";
            $posicion_y -= $tipo === "titulo" ? 25 : 16;
        }

        if ($tipo === "seccion") {
            $posicion_y -= 2;
            $contenido .= "0.12 0.29 0.31 RG 0.7 w 52 $posicion_y m 543 $posicion_y l S\n";
            $posicion_y -= 7;
        }
        if ($tipo === "titulo") {
            $contenido .= "0.12 0.29 0.31 RG 1 w 52 $posicion_y m 543 $posicion_y l S\n";
            $posicion_y -= 9;
        }
    }

    $objetos = [
        1 => "<< /Type /Catalog /Pages 2 0 R >>",
        2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>",
        4 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>",
        5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>",
        6 => "<< /Length " . strlen($contenido) . " >>\nstream\n" . $contenido . "\nendstream",
    ];

    $pdf = "%PDF-1.4\n";
    $desplazamientos = [0];
    foreach ($objetos as $numero => $objeto) {
        $desplazamientos[$numero] = strlen($pdf);
        $pdf .= $numero . " 0 obj\n" . $objeto . "\nendobj\n";
    }

    $inicio_xref = strlen($pdf);
    $pdf .= "xref\n0 7\n0000000000 65535 f \n";
    for ($numero = 1; $numero <= 6; $numero++) {
        $pdf .= str_pad((string) $desplazamientos[$numero], 10, "0", STR_PAD_LEFT) . " 00000 n \n";
    }
    $pdf .= "trailer\n<< /Size 7 /Root 1 0 R >>\nstartxref\n" . $inicio_xref . "\n%%EOF";

    return $pdf;
}