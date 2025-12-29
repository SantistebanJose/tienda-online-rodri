<?php
// ARCHIVO: debug_catalog.php
// Script de diagnóstico para identificar problemas con imágenes y precios

require_once 'includes/header.php';
require_once 'includes/functions.php';

$db = new DB();

echo "<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
.debug-container { max-width: 1200px; margin: 0 auto; }
.debug-section { background: white; padding: 20px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.debug-title { color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px; margin-bottom: 20px; }
.product-row { padding: 15px; margin: 10px 0; border: 1px solid #e0e0e0; border-radius: 4px; }
.error { color: #e74c3c; font-weight: bold; }
.warning { color: #f39c12; font-weight: bold; }
.success { color: #27ae60; font-weight: bold; }
.code { background: #ecf0f1; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
th { background: #3498db; color: white; }
img.thumb { max-width: 100px; max-height: 100px; object-fit: cover; border: 2px solid #ddd; border-radius: 4px; }
</style>";

echo "<div class='debug-container'>";
echo "<h1 style='text-align: center; color: #2c3e50;'>🔍 Diagnóstico del Catálogo</h1>";

// ============================================
// 1. VERIFICAR ESTRUCTURA DE LA BASE DE DATOS
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>1. Estructura de la Tabla 'articulo'</h2>";

try {
    $stmt = $db->pdo->query("
        SELECT column_name, data_type, is_nullable, column_default
        FROM information_schema.columns
        WHERE table_name = 'articulo'
        ORDER BY ordinal_position
    ");
    
    $columns = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>Columna</th><th>Tipo</th><th>Nullable</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>{$col['column_name']}</td>";
        echo "<td>{$col['data_type']}</td>";
        echo "<td>{$col['is_nullable']}</td>";
        echo "<td>" . ($col['column_default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Verificar columnas críticas
    $critical_columns = ['precio_venta', 'json_url_img', 'stock', 'nombre'];
    $found_columns = array_column($columns, 'column_name');
    
    foreach ($critical_columns as $critical) {
        if (in_array($critical, $found_columns)) {
            echo "<p class='success'>✓ Columna '$critical' encontrada</p>";
        } else {
            echo "<p class='error'>✗ Columna '$critical' NO encontrada</p>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// ============================================
// 2. ANÁLISIS DE PRECIOS
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>2. Análisis de Precios</h2>";

try {
    // Contar productos con precio 0 o NULL
    $stmt = $db->pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN precio_venta IS NULL THEN 1 END) as precio_null,
            COUNT(CASE WHEN precio_venta = 0 THEN 1 END) as precio_cero,
            COUNT(CASE WHEN precio_venta > 0 THEN 1 END) as precio_valido,
            MIN(precio_venta) as precio_minimo,
            MAX(precio_venta) as precio_maximo,
            AVG(precio_venta) as precio_promedio
        FROM articulo
        WHERE deleted_at IS NULL
    ");
    
    $stats = $stmt->fetch();
    
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0;'>";
    echo "<div style='background: #ecf0f1; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Total Productos</h3><p style='font-size: 2em; margin: 0; color: #3498db;'>{$stats['total']}</p></div>";
    
    echo "<div style='background: #fee; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Precio NULL</h3><p style='font-size: 2em; margin: 0; color: #e74c3c;'>{$stats['precio_null']}</p></div>";
    
    echo "<div style='background: #ffeaa7; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Precio = 0</h3><p style='font-size: 2em; margin: 0; color: #f39c12;'>{$stats['precio_cero']}</p></div>";
    
    echo "<div style='background: #dfe6e9; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Precio Válido</h3><p style='font-size: 2em; margin: 0; color: #27ae60;'>{$stats['precio_valido']}</p></div>";
    echo "</div>";
    
    echo "<p><strong>Rango de precios:</strong> S/. " . number_format($stats['precio_minimo'], 2) . 
         " - S/. " . number_format($stats['precio_maximo'], 2) . "</p>";
    echo "<p><strong>Precio promedio:</strong> S/. " . number_format($stats['precio_promedio'], 2) . "</p>";
    
    // Mostrar productos con precio problemático
    if ($stats['precio_null'] > 0 || $stats['precio_cero'] > 0) {
        echo "<h3 style='margin-top: 30px;'>Productos con Precios Problemáticos:</h3>";
        
        $stmt = $db->pdo->query("
            SELECT id, nombre, precio_venta, stock, marca
            FROM articulo
            WHERE (precio_venta IS NULL OR precio_venta = 0)
            AND deleted_at IS NULL
            ORDER BY id
            LIMIT 20
        ");
        
        $problematic = $stmt->fetchAll();
        
        echo "<table>";
        echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Stock</th><th>Marca</th></tr>";
        foreach ($problematic as $prod) {
            $price_class = ($prod['precio_venta'] === null) ? 'error' : 'warning';
            $price_display = ($prod['precio_venta'] === null) ? 'NULL' : 'S/. 0.00';
            
            echo "<tr>";
            echo "<td>{$prod['id']}</td>";
            echo "<td>" . htmlspecialchars($prod['nombre']) . "</td>";
            echo "<td class='$price_class'>$price_display</td>";
            echo "<td>{$prod['stock']}</td>";
            echo "<td>" . htmlspecialchars($prod['marca'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// ============================================
// 3. ANÁLISIS DE IMÁGENES
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>3. Análisis de Imágenes</h2>";

try {
    $stmt = $db->pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN json_url_img IS NULL THEN 1 END) as img_null,
            COUNT(CASE WHEN json_url_img = '' THEN 1 END) as img_empty,
            COUNT(CASE WHEN json_url_img = '[]' THEN 1 END) as img_empty_array,
            COUNT(CASE WHEN json_url_img IS NOT NULL AND json_url_img != '' AND json_url_img != '[]' THEN 1 END) as img_con_datos
        FROM articulo
        WHERE deleted_at IS NULL
    ");
    
    $img_stats = $stmt->fetch();
    
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin: 20px 0;'>";
    echo "<div style='background: #ecf0f1; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Total</h3><p style='font-size: 2em; margin: 0;'>{$img_stats['total']}</p></div>";
    
    echo "<div style='background: #fee; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>NULL</h3><p style='font-size: 2em; margin: 0; color: #e74c3c;'>{$img_stats['img_null']}</p></div>";
    
    echo "<div style='background: #ffeaa7; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Vacío</h3><p style='font-size: 2em; margin: 0; color: #f39c12;'>{$img_stats['img_empty']}</p></div>";
    
    echo "<div style='background: #dfe6e9; padding: 15px; border-radius: 8px; text-align: center;'>";
    echo "<h3>Con Datos</h3><p style='font-size: 2em; margin: 0; color: #27ae60;'>{$img_stats['img_con_datos']}</p></div>";
    echo "</div>";
    
    // Mostrar ejemplos de productos con imágenes
    echo "<h3 style='margin-top: 30px;'>Ejemplos de Productos con Imágenes:</h3>";
    
    $stmt = $db->pdo->query("
        SELECT id, nombre, precio_venta, json_url_img
        FROM articulo
        WHERE json_url_img IS NOT NULL 
        AND json_url_img != '' 
        AND json_url_img != '[]'
        AND deleted_at IS NULL
        ORDER BY id
        LIMIT 10
    ");
    
    $products_with_images = $stmt->fetchAll();
    
    foreach ($products_with_images as $prod) {
        echo "<div class='product-row'>";
        echo "<h4>ID: {$prod['id']} - " . htmlspecialchars($prod['nombre']) . "</h4>";
        echo "<p><strong>Precio:</strong> S/. " . number_format($prod['precio_venta'], 2) . "</p>";
        
        echo "<p><strong>JSON Raw:</strong></p>";
        echo "<div class='code'>" . htmlspecialchars($prod['json_url_img']) . "</div>";
        
        // Intentar decodificar JSON
        $images = json_decode($prod['json_url_img'], true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "<p class='success'>✓ JSON válido</p>";
            
            if (is_array($images) && !empty($images)) {
                echo "<p><strong>Imágenes encontradas:</strong> " . count($images) . "</p>";
                
                echo "<div style='display: flex; gap: 10px; flex-wrap: wrap; margin: 10px 0;'>";
                foreach ($images as $idx => $img) {
                    if (isset($img['url'])) {
                        echo "<div style='text-align: center;'>";
                        echo "<img src='" . htmlspecialchars($img['url']) . "' class='thumb' onerror='this.style.border=\"2px solid red\"'>";
                        echo "<p style='font-size: 0.8em; margin: 5px 0;'>Img " . ($idx + 1) . "</p>";
                        echo "</div>";
                    }
                }
                echo "</div>";
                
                // Mostrar estructura del primer elemento
                echo "<p><strong>Estructura del primer elemento:</strong></p>";
                echo "<div class='code'><pre>" . print_r($images[0], true) . "</pre></div>";
            } else {
                echo "<p class='warning'>⚠ Array vacío</p>";
            }
        } else {
            echo "<p class='error'>✗ Error al decodificar JSON: " . json_last_error_msg() . "</p>";
        }
        
        echo "</div>";
    }
    
    // Mostrar productos SIN imágenes
    echo "<h3 style='margin-top: 30px;'>Productos SIN Imágenes (primeros 10):</h3>";
    
    $stmt = $db->pdo->query("
        SELECT id, nombre, precio_venta, json_url_img
        FROM articulo
        WHERE (json_url_img IS NULL OR json_url_img = '' OR json_url_img = '[]')
        AND deleted_at IS NULL
        ORDER BY id
        LIMIT 10
    ");
    
    $no_images = $stmt->fetchAll();
    
    echo "<table>";
    echo "<tr><th>ID</th><th>Nombre</th><th>Precio</th><th>Estado JSON</th></tr>";
    foreach ($no_images as $prod) {
        $json_status = 'Vacío';
        if ($prod['json_url_img'] === null) $json_status = 'NULL';
        elseif ($prod['json_url_img'] === '[]') $json_status = 'Array vacío';
        
        echo "<tr>";
        echo "<td>{$prod['id']}</td>";
        echo "<td>" . htmlspecialchars($prod['nombre']) . "</td>";
        echo "<td>S/. " . number_format($prod['precio_venta'], 2) . "</td>";
        echo "<td class='warning'>$json_status</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

// ============================================
// 4. VERIFICAR FUNCIÓN procesarImagenesArticulo()
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>4. Verificación de la Función procesarImagenesArticulo()</h2>";

if (function_exists('procesarImagenesArticulo')) {
    echo "<p class='success'>✓ Función 'procesarImagenesArticulo()' existe</p>";
    
    // Probar con diferentes tipos de entrada
    $test_cases = [
        'null' => null,
        'string vacío' => '',
        'array vacío JSON' => '[]',
        'JSON válido simple' => '[{"url":"https://example.com/img.jpg"}]',
        'JSON con múltiples imágenes' => '[{"url":"img1.jpg"},{"url":"img2.jpg"}]',
    ];
    
    echo "<h3>Pruebas de la Función:</h3>";
    foreach ($test_cases as $name => $input) {
        echo "<div style='margin: 15px 0; padding: 10px; background: #f8f9fa; border-left: 3px solid #3498db;'>";
        echo "<strong>Caso: $name</strong><br>";
        echo "Input: <code>" . htmlspecialchars(var_export($input, true)) . "</code><br>";
        
        $result = procesarImagenesArticulo($input);
        echo "Output: <code>" . htmlspecialchars(print_r($result, true)) . "</code>";
        echo "</div>";
    }
} else {
    echo "<p class='error'>✗ Función 'procesarImagenesArticulo()' NO encontrada en includes/functions.php</p>";
    echo "<p>Esta función es crítica para procesar las imágenes. Debe estar definida en includes/functions.php</p>";
}

echo "</div>";

// ============================================
// 5. CONSULTA SQL ACTUAL
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>5. Consulta SQL Actual (primeros 5 resultados)</h2>";

try {
    $sql = "
        SELECT 
            a.id, 
            a.nombre, 
            a.precio_venta, 
            a.stock, 
            a.marca, 
            a.categoria_id,
            a.json_url_img,
            c.descripcion as categoria_nombre 
        FROM articulo a
        LEFT JOIN categoria c ON a.categoria_id = c.id
        WHERE a.stock > 0 AND a.deleted_at IS NULL
        ORDER BY a.nombre ASC
        LIMIT 5
    ";
    
    echo "<div class='code'>$sql</div>";
    
    $stmt = $db->pdo->query($sql);
    $results = $stmt->fetchAll();
    
    echo "<h3>Resultados:</h3>";
    foreach ($results as $row) {
        echo "<div class='product-row'>";
        echo "<h4>" . htmlspecialchars($row['nombre']) . "</h4>";
        echo "<p><strong>ID:</strong> {$row['id']}</p>";
        echo "<p><strong>Precio en BD:</strong> " . var_export($row['precio_venta'], true) . "</p>";
        echo "<p><strong>Precio formateado:</strong> S/. " . number_format($row['precio_venta'] ?? 0, 2) . "</p>";
        echo "<p><strong>Stock:</strong> {$row['stock']}</p>";
        echo "<p><strong>JSON imágenes:</strong> <code>" . htmlspecialchars(substr($row['json_url_img'] ?? 'NULL', 0, 100)) . "...</code></p>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
}

echo "</div>";

// ============================================
// 6. RECOMENDACIONES
// ============================================
echo "<div class='debug-section'>";
echo "<h2 class='debug-title'>6. Recomendaciones y Soluciones</h2>";

echo "<ol style='line-height: 2;'>";
echo "<li><strong>Para precios en 0 o NULL:</strong> Ejecuta UPDATE para asignar precios por defecto o corregir los valores</li>";
echo "<li><strong>Para imágenes:</strong> Verifica que el JSON esté bien formado y que las URLs sean accesibles</li>";
echo "<li><strong>Función procesarImagenesArticulo():</strong> Asegúrate de que esté definida en includes/functions.php</li>";
echo "<li><strong>Permisos de imágenes:</strong> Si usas Google Drive, verifica que los archivos sean públicos</li>";
echo "</ol>";

echo "<h3>Script SQL Sugerido para Corregir Precios:</h3>";
echo "<div class='code'>";
echo "-- Ver productos con precio 0 o NULL<br>";
echo "SELECT id, nombre, precio_venta FROM articulo WHERE precio_venta IS NULL OR precio_venta = 0;<br><br>";
echo "-- Actualizar precio por defecto (ajusta según necesites)<br>";
echo "UPDATE articulo SET precio_venta = 10.00 WHERE precio_venta IS NULL OR precio_venta = 0;";
echo "</div>";

echo "</div>";

echo "</div>"; // Cierre debug-container

?>