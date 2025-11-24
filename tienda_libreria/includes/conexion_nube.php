<?php

function conectar_nube() {
    $host = "localhost";
    $user = "postgres";
    $password = '76008509';
    $port = "5432";
    $nombreBD = "tienda_online_lbr";

    try {
        $dsn = "pgsql:host=$host;port=$port;dbname=$nombreBD";
        $conexion = new PDO($dsn, $user, $password);
        $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        //echo "conectadoo :)";
        return $conexion;
    } catch (PDOException $e) {
        die("Error de conexión: " . $e->getMessage());
    }

}


?>
