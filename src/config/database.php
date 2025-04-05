<?php
// Cargar la configuración desde el archivo
$config = require __DIR__ . '/database_config.php';

// Construir el DSN con las variables del array
$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8"; #DSN = DATA SOURCE NAME host, dbname, charset; Son los parametros
$username = $config['username']; #su nombre de usuario, por defecto en XAMPP es root
$password = $config['password']; #la contraseña, por defecto en XAMPP es '' (osea vacia)

try { #creamos una instancia de PDO (una conexion a una base de datos, le pasamos los parametros) y con [] configuramos
    $pdo = new PDO($dsn, $username, $password, [ 
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, #esto hace que cualquier error frene el programa y sea visible su descripcion
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, #los resultados de una consulta se devuelven en un array asociativo
        PDO::ATTR_EMULATE_PREPARES => false #asegura que las consultas provengan de MySQL, evita inyecciones SQL (hackeos) 
]);
}
    #si todo sale mal, explota
catch (PDOException $e){
    die("error en la conexion: ". $e->getMessage());
}

return $pdo; #retorna la conexion a la base de datos, para usarla en otros archivos