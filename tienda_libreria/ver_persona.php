<?php
// ver_persona.php - Ver columnas de la tabla persona
require_once 'includes/db.php';

$db = new DB();

try {
    $stmt = $db->pdo->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'persona'
        ORDER BY ordinal_position
    ");
    
    $columnas = $stmt->fetchAll();
    
    echo "<h2>Columnas de la tabla persona:</h2>";
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
    $stmt = $db->pdo->query("SELECT * FROM persona LIMIT 5");
    $datos = $stmt->fetchAll();
    
    if ($datos) {
        echo "<pre>";
        print_r($datos);
        echo "</pre>";
    }
    
    // Ver si el ID 1 existe en persona
    echo "<br><br><h2>¿Existe persona con ID 1?</h2>";
    $stmt = $db->pdo->prepare("SELECT * FROM persona WHERE id = ?");
    $stmt->execute([1]);
    $persona = $stmt->fetch();
    
    if ($persona) {
        echo "<pre>";
        print_r($persona);
        echo "</pre>";
    } else {
        echo "No existe persona con ID 1";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
