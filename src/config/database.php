<?php
class Database {
    private static $connection; //Propiedad estática para almacenar la conexión a la base de datos.
    //El modificador de acceso static permite acceder a la propiedad sin necesidad de crear una instancia de la clase.

    public static function getConnection() {
        if (!self::$connection) { /*self se usa dentro de una clase para
            hacer referencia a elementos estáticos (métodos o propiedades) de esa misma clase.*/
            // Cargar configuración dentro del método
            $config = require __DIR__ . '/database_config.php';

            //Almaceno los parametros de mi conexión por PDO
            $dsn = sprintf("mysql:host=%s;dbname=%s;charset=utf8", $config['host'], $config['dbname']);
            #DSN = DATA SOURCE NAME host, dbname, charset; Son los parametros
            $username = $config['username']; #su nombre de usuario, por defecto en XAMPP es root
            $password = $config['password']; #la contraseña, por defecto en XAMPP es '' (osea vacia)
            //intento conexión
            try {
                self::$connection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, #esto hace que cualquier error frene el programa y sea visible su descripcion
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, #los resultados de una consulta se devuelven en un array asociativo
                    PDO::ATTR_EMULATE_PREPARES => false #asegura que las consultas provengan de MySQL, evita inyecciones SQL (hackeos)
                ]);
            } catch (PDOException $e) {
                die(json_encode(['error' => $e->getMessage()])); //Sino, arroja mensaje por excepción
            }
        }

        return self::$connection;
    }
}
?>