<!DOCTYPE html>
<html lang="en">
<head>
    <?php
    ?>
    <link rel="stylesheet" href="style.css">
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>stock</title>
</head>
<body>
<h3>productos:</h3>
<table style="border: 1px solid black;">
<th>Nombre</th>
<th>Cantidad actual</th>
<th>Cantidad ultima venta</th>
<th>Ultima cantidad añadida</th>
<th>Precio de compra</th>
<th>Precio de venta</th>
<th>Ganancia ultima venta</th>
<tr>
    <?php
    $pdo = $conn->query("SELECT * FROM productos");
    $prods = $pdo->fetchAll(PDO::FETCH_ASSOC);
foreach($prods as $prod):
    $cant = $conn->query("SELECT * FROM cantidades WHERE producto = {$prod['id']}")->fetch(PDO::FETCH_ASSOC);
    $cambios = $conn->query("SELECT * FROM cambios WHERE producto = {$prod['id']}")->fetchAll(PDO::FETCH_ASSOC);
?>
    <td><?= $prod['nombre'] ?></td>
    <td><?= $cant['cantidad'] ?></td>
    <td><?= $cambios['ultima_venta'] ?></td>
    <td><?= $cant['ultima_entrada'] ?></td>
    <td><?= $prod['precio_compra'] ?></td>
    <td><?= $prod['precio_venta'] ?></td>
    <td><?= $prod['ganancia_ultima_venta'] ?></td>
</tr>
<?php endforeach; ?>
</table>


<h3>Nuevo producto</h3>
<form action="" method="post">
    <label for="">nombre del producto:
    <input type="text" name="nombre" id="">
    </label>
    <label for=""> cantidad actual:
    <input type="number" name="cant" id="">
    </label>
</form>

<h3>Reporte de hoy</h3>
    <form action="" method="post">
        <p>que producto</p>
       
        <select name="prod" id="">
            <option value=""></option>
            <?php foreach($prods as $prod): ?>
                <option value="<?= $prod['id'] ?>"><?= $prod['nombre'] ?></option>
            <?php endforeach; ?>
        </select>

        <label for="">sujeto cliente o proovedor:
        <input type="text" name="sujeto" id="">
        </label>

        <label> <select name="tipo" id="">
            <option value="compra">Compra</option>
            <option value="venta">Venta</option>
        </select> </label>

        <label for="">cantidad:
        <input type="number" name="cant" id=""> 
        </label>

        <label for="">subtotal:
        <input type="number" name="subtotal" id=""> 
        </label>

        <button type="submit">Registrar</button>
    </form>
</body>
</html>