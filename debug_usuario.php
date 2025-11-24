<?php
// debug_usuario.php - Debuggear el ID del usuario
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once 'includes/db.php';

$response = [];

try {
    $db = new DB();
    
    // Ver qué hay en la sesión
    $response['session_cliente_id'] = $_SESSION['cliente_id'] ?? null;
    $response['session_cliente_nombre'] = $_SESSION['cliente_nombre'] ?? null;
    $response['session_cliente_email'] = $_SESSION['cliente_email'] ?? null;
    
    // Ver si existe ese usuario en la tabla usuario
    if (isset($_SESSION['cliente_id'])) {
        $stmt = $db->pdo->prepare("SELECT * FROM usuario WHERE id = ?");
        $stmt->execute([$_SESSION['cliente_id']]);
        $usuario = $stmt->fetch();
        
        $response['usuario_en_bd'] = $usuario;
    }
    
    // Ver todos los usuarios
    $stmt = $db->pdo->query("SELECT id, nombre, email FROM usuario LIMIT 10");
    $usuarios = $stmt->fetchAll();
    
    $response['usuarios_en_bd'] = $usuarios;
    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    $response['success'] = false;
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
?>
