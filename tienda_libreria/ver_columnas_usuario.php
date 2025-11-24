<?php
// ver_columnas_usuario.php - Ver columnas de la tabla usuario
require_once 'includes/db.php';

$db = new DB();

try {
    $stmt = $db->pdo->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'usuario'
        ORDER BY ordinal_position
    ");
    
    $columnas = $stmt->fetchAll();
    
    echo "<h2>Columnas de la tabla usuario:</h2>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Nullable</th></tr>";
    
    foreach ($columnas as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['column_name']) . "</td>";
        echo "<td>" . htmlspecialchars($col['data_type']) . "</td>";
        echo "<td>" . ($col['is_nullable'] === 'YES' ? 'Sí' : 'No') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br><h2>Datos de ejemplo:</h2>";
    $stmt = $db->pdo->query("SELECT * FROM usuario LIMIT 5");
    $datos = $stmt->fetchAll();
    
    if ($datos) {
        echo "<pre>";
        print_r($datos);
        echo "</pre>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
