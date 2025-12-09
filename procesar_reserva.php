<?php
// procesar_reserva.php - Procesar la compra y guardar en reserva_web y detalle_reserva_web
session_start();
header('Content-Type: application/json');

require_once 'includes/db.php';

$response = [
    'success' => false,
    'message' => 'Error desconocido',
    'debug' => []
];

try {
    // ✅ VERIFICAR AUTENTICACIÓN - Usar 'id' de la sesión (coincide con persona.id)
    $usuario_id = null;
    
    // Buscar el usuario en variables de sesión comunes
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
        http_response_code(401);
        $response['message'] = 'Debes iniciar sesión para realizar una compra';
        echo json_encode($response);
        exit;
    }
    
    // Obtener datos JSON del POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['items']) || !is_array($input['items'])) {
        throw new Exception('No hay items en la compra');
    }

    $items = $input['items'];
    $notas = isset($input['notas']) ? trim($input['notas']) : '';
    
    if (count($items) === 0) {
        throw new Exception('El carrito está vacío');
    }

    $db = new DB();
    
    // Verificar que el usuario existe en tabla persona
    $stmt_verify = $db->pdo->prepare("
        SELECT id, nombres, apellidos, email 
        FROM persona_web 
        WHERE id = ? 
        AND tipo_persona = 'cliente'
        AND deleted_at IS NULL
    ");
    $stmt_verify->execute([$usuario_id]);
    $usuario = $stmt_verify->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        throw new Exception('Usuario no encontrado o no es cliente');
    }
    
    // Iniciar transacción
    $db->pdo->beginTransaction();

    // Obtener artículos del carrito
    $ids = array_column($items, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    $sql = "
        SELECT id, nombre, stock, precio_venta 
        FROM articulo 
        WHERE id IN ($placeholders) 
        -- AND activo = true
        AND deleted_at IS NULL
    ";
    
    $stmt = $db->pdo->prepare($sql);
    
    foreach ($ids as $i => $id) {
        $stmt->bindValue($i + 1, intval($id), PDO::PARAM_INT);
    }
    
    $stmt->execute();
    $articulos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($articulos) !== count($ids)) {
        throw new Exception('Algunos productos no existen o no están disponibles');
    }
    
    // Crear mapeo de artículos
    $articuloMap = [];
    foreach ($articulos as $art) {
        $articuloMap[$art['id']] = $art;
    }
    
    $total_pedido = 0;
    $detalles = [];
    
    // Validar stock y preparar detalles
    foreach ($items as $item) {
        $articulo_id = intval($item['id']);
        $cantidad = intval($item['cantidad']);
        
        if (!isset($articuloMap[$articulo_id])) {
            throw new Exception('Producto inválido: ' . $articulo_id);
        }
        
        if ($cantidad <= 0) {
            throw new Exception('Cantidad inválida para producto ' . $articulo_id);
        }
        
        $articulo = $articuloMap[$articulo_id];
        
        // Validar stock
        if ($articulo['stock'] < $cantidad) {
            throw new Exception(
                "Stock insuficiente para '{$articulo['nombre']}'. " .
                "Solicitado: {$cantidad}, Disponible: {$articulo['stock']}"
            );
        }
        
        $precio_unitario = floatval($articulo['precio_venta']);
        $subtotal = $precio_unitario * $cantidad;
        $total_pedido += $subtotal;
        
        $detalles[] = [
            'articulo_id' => $articulo_id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio_unitario,
            'subtotal' => $subtotal
        ];
    }
    
    if ($total_pedido <= 0) {
        throw new Exception('El total del pedido debe ser mayor a 0');
    }
    
    // ✅ INSERTAR EN reserva_web
    $stmt_reserva = $db->pdo->prepare("
        INSERT INTO reserva_web (usuario_id, estado, total, notas, fecha_reserva)
        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
    ");
    
    $stmt_reserva->execute([
        $usuario_id,
        'pendiente',
        $total_pedido,
        !empty($notas) ? $notas : null
    ]);
    
    // Obtener el ID de la reserva creada
    $reserva_id = $db->pdo->lastInsertId('reserva_web_id_seq');
    
    if (!$reserva_id) {
        throw new Exception('Error al obtener ID de la reserva');
    }
    
    // ✅ INSERTAR DETALLES DE LA RESERVA
    $stmt_detalle = $db->pdo->prepare("
        INSERT INTO detalle_reserva_web 
        (reserva_id, articulo_id, cantidad, precio_unitario, subtotal)
        VALUES (?, ?, ?, ?, ?)
    ");
    
    foreach ($detalles as $detalle) {
        $stmt_detalle->execute([
            $reserva_id,
            $detalle['articulo_id'],
            $detalle['cantidad'],
            $detalle['precio_unitario'],
            $detalle['subtotal']
        ]);
    }
    
    /* ✅ ACTUALIZAR STOCK (reducir las cantidades)
    $stmt_stock = $db->pdo->prepare("
        UPDATE articulo 
        SET stock = stock - ? 
        WHERE id = ?
    "); 
    
    foreach ($detalles as $detalle) {
        $stmt_stock->execute([
            $detalle['cantidad'],
            $detalle['articulo_id']
        ]);
    } */
    
    // Confirmar transacción
    $db->pdo->commit();
    
    // Vaciar carrito de sesión
    if (isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    $response['success'] = true;
    $response['message'] = '¡Compra realizada exitosamente!';
    $response['reserva_id'] = $reserva_id;
    $response['total'] = $total_pedido;
    
} catch (PDOException $e) {
    // Revertir transacción en caso de error
    if (isset($db) && $db->pdo->inTransaction()) {
        try {
            $db->pdo->rollBack();
        } catch (Exception $e) {}
    }
    
    // LOG DE ERROR PARA DEBUG
    file_put_contents('debug_errors.log', date('Y-m-d H:i:s') . " [PDO] " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(400);
    $response['message'] = 'Error en la base de datos: ' . $e->getMessage();
    
    error_log('PDOException en procesar_reserva.php: ' . $e->getMessage());
    
} catch (Exception $e) {
    // Revertir transacción en caso de error
    if (isset($db) && $db->pdo->inTransaction()) {
        try {
            $db->pdo->rollBack();
        } catch (Exception $e) {}
    }
    
    // LOG DE ERROR PARA DEBUG
    file_put_contents('debug_errors.log', date('Y-m-d H:i:s') . " [PHP] " . $e->getMessage() . "\n", FILE_APPEND);
    
    http_response_code(400);
    $response['message'] = $e->getMessage();
    
    error_log('Exception en procesar_reserva.php: ' . $e->getMessage());
}

echo json_encode($response);
?>