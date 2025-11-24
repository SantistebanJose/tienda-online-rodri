<?php
// debug_reserva.php - Para debuggear el problema
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/db.php';

$response = [];

try {
    $db = new DB();
    
    // Verificar que la tabla reserva_web existe
    $stmt = $db->pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'reserva_web'
    ");
    $table_exists = $stmt->fetch();
    
    $response['tabla_reserva_web_existe'] = $table_exists ? true : false;
    
    // Verificar que la tabla detalle_reserva_web existe
    $stmt = $db->pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' AND table_name = 'detalle_reserva_web'
    ");
    $table_exists2 = $stmt->fetch();
    
    $response['tabla_detalle_reserva_web_existe'] = $table_exists2 ? true : false;
    
    // Verificar restricciones
    $stmt = $db->pdo->query("
        SELECT constraint_name, constraint_type 
        FROM information_schema.table_constraints 
        WHERE table_name = 'reserva_web'
    ");
    $constraints = $stmt->fetchAll();
    
    $response['restricciones_reserva_web'] = $constraints;
    
    // Verificar columnas de reserva_web
    $stmt = $db->pdo->query("
        SELECT column_name, data_type, is_nullable 
        FROM information_schema.columns 
        WHERE table_name = 'reserva_web'
    ");
    $columns = $stmt->fetchAll();
    
    $response['columnas_reserva_web'] = $columns;
    
    // Intentar insertar un registro de prueba
    if (isset($_SESSION['cliente_id'])) {
        $cliente_id = intval($_SESSION['cliente_id']);
        
        try {
            $stmt = $db->pdo->prepare("
                INSERT INTO reserva_web (usuario_id, estado, total, notas)
                VALUES (:usuario_id, :estado, :total, :notas)
                RETURNING id
            ");
            
            $stmt->execute([
                ':usuario_id' => $cliente_id,
                ':estado' => 'pendiente',
                ':total' => 100.00,
                ':notas' => 'Prueba de debug'
            ]);
            
            $result = $stmt->fetch();
            $response['test_insert'] = 'Exitoso - ID: ' . $result['id'];
            
            // Eliminar el registro de prueba
            $db->pdo->exec("DELETE FROM reserva_web WHERE id = " . $result['id']);
            
        } catch (PDOException $e) {
            $response['test_insert_error'] = $e->getMessage();
            $response['test_insert_code'] = $e->getCode();
        }
    }
    
    $response['usuario_logueado'] = isset($_SESSION['cliente_id']) ? $_SESSION['cliente_id'] : 'No logueado';
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
