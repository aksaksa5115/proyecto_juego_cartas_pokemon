<?php
 include_once __DIR__ . '/../config/database.php'; //Incluye el archivo de conexión a la base de datos.
?>

<!DOCTYPE html>
<html lang="en-es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pokemon Web Cards</title>
</head>
<body>
    <h1 style="text-align: center;"><u>Bienvenido a la Web de Pokemon</u></h1>
    <h2>Listado de cartas pokemonm</h2>
    <table border="1" cellpadding="5" cellspacing="0">
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Ataque</th>
            <th>Ataque Nombre</th>
        </tr>
        <?php
        // Realiza la consulta a la base de datos para obtener los datos de las cartas pokemon.
        $sql = "SELECT id, nombre, ataque, ataque_nombre FROM carta";
        $result = $pdo->query($sql);
        // Verifica si hay resultados y los muestra en la tabla.
        // "num_rows" Retorna el número de filas del resultado.
        if ($result->rowCount() > 0) {
            while($row = $result->fetch(PDO::FETCH_ASSOC)) { //Creo un arreglo asociativo
                echo "<tr>";
                echo "<td>" . $row["id"] . "</td>";
                echo "<td>" . $row["nombre"] . "</td>";
                echo "<td>" . $row["ataque"] . "</td>";
                echo "<td>" . $row["ataque_nombre"] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='4'>No se encontraron cartas pokemon.</td></tr>";
        }
        ?>
</body>
</html>

<?php $pdo = null; // Cierra la conexión a la base de datos. ?>

