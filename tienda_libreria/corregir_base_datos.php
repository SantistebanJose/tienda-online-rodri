<?php
// corregir_base_datos.php
// Script para arreglar la integridad referencial de la base de datos
require_once 'includes/db.php';

$db = new DB();
$pdo = $db->pdo;

echo "<!DOCTYPE html><html><head><title>Corrección BD</title>";
echo "<style>body{font-family:sans-serif;padding:20px;} .success{color:green;} .error{color:red;} .info{color:blue;}</style>";
echo "</head><body>";
echo "<h1>Diagnóstico y Corrección de Base de Datos</h1>";

function ejecutar($pdo, $sql, $mensaje) {
    try {
        $pdo->exec($sql);
        echo "<p class='success'>✅ Éxito: $mensaje</p>";
    } catch (PDOException $e) {
        // Ignorar errores de "no existe" al borrar
        if (strpos($sql, 'DROP') !== false) {
            echo "<p class='info'>ℹ️ Info: $mensaje (Posiblemente no existía)</p>";
        } else {
            echo "<p class='error'>❌ Error: $mensaje <br>Detalle: " . $e->getMessage() . "</p>";
        }
    }
}

// 1. Verificar tablas existentes
echo "<h2>1. Verificando tablas</h2>";
try {
    $stmt = $pdo->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname != 'pg_catalog' AND schemaname != 'information_schema'");
    $tablas = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tablas encontradas: " . implode(", ", $tablas) . "<br>";
    
    if (!in_array('persona', $tablas)) {
        echo "<p class='error'>CRÍTICO: La tabla 'persona' no existe.</p>";
    } else {
        echo "<p class='success'>Tabla 'persona' existe.</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>Error al listar tablas: " . $e->getMessage() . "</p>";
}

// 2. Corregir Clave Foránea en reserva_web
echo "<h2>2. Corregir Relaciones (Foreign Keys)</h2>";

// Intentar borrar restricciones viejas con nombres comunes
ejecutar($pdo, "ALTER TABLE reserva_web DROP CONSTRAINT IF EXISTS fk_usuario", "Eliminando fk_usuario antigua");
ejecutar($pdo, "ALTER TABLE reserva_web DROP CONSTRAINT IF EXISTS reserva_web_usuario_id_fkey", "Eliminando reserva_web_usuario_id_fkey antigua");

// Crear la nueva restricción apuntando a PERSONA
$sql_fk = "ALTER TABLE reserva_web 
           ADD CONSTRAINT fk_reserva_persona 
           FOREIGN KEY (usuario_id) 
           REFERENCES persona(id) 
           ON DELETE CASCADE";

ejecutar($pdo, $sql_fk, "Creando nueva FK apuntando a tabla 'persona'");

// 3. Verificar tipos de datos
echo "<h2>3. Verificar Tipos de Datos</h2>";
try {
    // Verificar tipo de dato de usuario_id en reserva_web
    $stmt = $pdo->query("SELECT data_type FROM information_schema.columns WHERE table_name = 'reserva_web' AND column_name = 'usuario_id'");
    $tipo_reserva = $stmt->fetchColumn();
    
    // Verificar tipo de dato de id en persona
    $stmt = $pdo->query("SELECT data_type FROM information_schema.columns WHERE table_name = 'persona' AND column_name = 'id'");
    $tipo_persona = $stmt->fetchColumn();
    
    echo "Tipo en reserva_web.usuario_id: $tipo_reserva<br>";
    echo "Tipo en persona.id: $tipo_persona<br>";
    
    if ($tipo_reserva == $tipo_persona) {
        echo "<p class='success'>Los tipos de datos coinciden.</p>";
    } else {
        echo "<p class='error'>ADVERTENCIA: Los tipos de datos no coinciden ($tipo_reserva vs $tipo_persona). Esto podría causar errores.</p>";
    }
} catch (Exception $e) {}

echo "<br><hr>";
echo "<h3>Instrucciones:</h3>";
echo "<p>Si viste '✅ Éxito' o mensajes verdes arriba, intenta realizar la compra nuevamente.</p>";
echo "<a href='carrito.php'>Volver al Carrito</a>";
echo "</body></html>";
?>
