<?php
// includes/db.php

class DB {
    public $pdo;
    
    public function __construct() {
        $server = "aws-1-us-east-2.pooler.supabase.com";
        $bd = "postgres";
        $user = "postgres.ocjhhbzzlyoskqxjecvc";
        $pass = "yC?/*FAHFm28R!V";
        $port = "5432";
        
        try {
            $this->pdo = new PDO("pgsql:host=$server;port=$port;dbname=$bd", $user, $pass);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec("SET NAMES 'UTF8'");
        } catch (PDOException $e) {
            die("Error de conexión a la base de datos: " . $e->getMessage());
        }
    }
}