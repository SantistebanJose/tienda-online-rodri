<?php
// procesar_pedido.php - Procesar reservas web (crear, cancelar)
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => 'Error desconocido'
];

try {
    // ✅ VERIFICAR AUTENTICACIÓN
    $usuario_id = null;
    
    if (isset($_SESSION['id'])) {
        $usuario_id = intval($_SESSION['id']);
    } elseif (isset($_SESSION['user_id'])) {
        $usuario_id = intval($_SESSION['user_id']);
    } elseif (isset($_SESSION['usuario_id'])) {
        $usuario_id = intval($_SESSION['usuario_id']);
    } elseif (isset($_SESSION['cliente_id'])) {
        $usuario_id = intval($_SESSION['cliente_id']);
    } elseif (isset($_SESSION['persona_id'])) {
        $usuario_id = intval($_SESSION['persona_id']);
    }
    
    if (!$usuario_id || $usuario_id <= 0) {
        throw new Exception('Debes iniciar sesión para realizar un pedido');
    }

    $db = new DB();
    
    // Obtener datos del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['action'])) {
        throw new Exception('Datos inválidos');
    }
    
    $action = $input['action'];
    
    // ============================================
    // CANCELAR RESERVA
    // ============================================
    if ($action === 'cancelar_reserva') {
        $reserva_id = intval($input['reserva_id'] ?? 0);
        
        if ($reserva_id <= 0) {
            throw new Exception('ID de reserva inválido');
        }
        
        // Iniciar transacción
        $db->pdo->beginTransaction();
        
        try {
            // Verificar que la reserva pertenece al usuario y existe
            $stmt = $db->pdo->prepare("
                SELECT id, estado 
                FROM reserva_web 
                WHERE id = ? AND usuario_id = ?
            ");
            $stmt->execute([$reserva_id, $usuario_id]);
            $reserva = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$reserva) {
                throw new Exception('Reserva no encontrada');
            }
            
            if ($reserva['estado'] === 'cancelado') {
                throw new Exception('La reserva ya está cancelada');
            }
            
            if (in_array($reserva['estado'], ['entregado'])) {
                throw new Exception('No se puede cancelar una reserva entregada');
            }
            
            // Obtener detalles para devolver stock
            $stmt = $db->pdo->prepare("
                SELECT articulo_id, cantidad 
                FROM detalle_reserva_web 
                WHERE reserva_id = ?
            ");
            $stmt->execute([$reserva_id]);
            $detalles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Devolver stock a cada artículo
            $stmt_stock = $db->pdo->prepare("
                UPDATE articulo 
                SET stock = stock + ? 
                WHERE id = ?
            ");
            
            foreach ($detalles as $detalle) {
                $stmt_stock->execute([
                    $detalle['cantidad'],
                    $detalle['articulo_id']
                ]);
            }
            
            // Actualizar estado de la reserva
            $stmt = $db->pdo->prepare("
                UPDATE reserva_web 
                SET estado = 'cancelado' 
                WHERE id = ?
            ");
            $stmt->execute([$reserva_id]);
            
            $db->pdo->commit();
            
            $response['success'] = true;
            $response['message'] = 'Reserva cancelada con éxito';
            
        } catch (Exception $e) {
            $db->pdo->rollBack();
            throw $e;
        }
    }
    
    else {
        throw new Exception('Acción no válida');
    }
    
} catch (PDOException $e) {
    // Revertir transacción si está activa
    if (isset($db) && $db->pdo->inTransaction()) {
        try {
            $db->pdo->rollBack();
        } catch (Exception $e) {}
    }
    
    http_response_code(400);
    $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    error_log('PDOException en procesar_pedido.php: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Revertir transacción si está activa
    if (isset($db) && $db->pdo->inTransaction()) {
        try {
            $db->pdo->rollBack();
        } catch (Exception $e) {}
    }
    
    http_response_code(400);
    $response['message'] = $e->getMessage();
    error_log('Exception en procesar_pedido.php: ' . $e->getMessage());
}

echo json_encode($response);
?>