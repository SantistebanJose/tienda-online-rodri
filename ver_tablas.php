<?php
// ver_tablas.php - Ver todas las tablas
require_once 'includes/db.php';

$db = new DB();

try {
    $stmt = $db->pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    
    $tablas = $stmt->fetchAll();
    
    echo "<h2>Todas las tablas en la base de datos:</h2>";
    echo "<ul>";
    foreach ($tablas as $tabla) {
        echo "<li>" . htmlspecialchars($tabla['table_name']) . "</li>";
    }
    echo "</ul>";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
