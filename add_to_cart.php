<?php
/**
 * Add to Cart - Agregar productos al carrito
 * tienda_libreria/add_to_cart.php
 */

session_start();

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');

// Habilitar CORS si es necesario
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Solo aceptar método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido. Use POST.'
    ]);
    exit;
}

try {
    // Recibir y validar datos
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $tipo = isset($_POST['tipo']) ? trim($_POST['tipo']) : '';
    $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;
    
    // Validar ID
    if ($id <= 0) {
        throw new Exception('ID de producto inválido');
    }
    
    // Validar tipo (articulo o servicio)
    $tipos_validos = ['articulo', 'servicio'];
    if (!in_array($tipo, $tipos_validos)) {
        throw new Exception('Tipo de producto inválido');
    }
    
    // Validar cantidad
    if ($cantidad <= 0 || $cantidad > 999) {
        throw new Exception('Cantidad inválida');
    }
    
    // Inicializar carrito si no existe
    if (!isset($_SESSION['carrito'])) {
        $_SESSION['carrito'] = [];
    }
    
    // Crear clave única para el item
    $item_key = $tipo . '_' . $id;
    
    // Verificar si el producto ya existe en el carrito
    if (isset($_SESSION['carrito'][$item_key])) {
        // Incrementar cantidad
        $_SESSION['carrito'][$item_key]['cantidad'] += $cantidad;
        $accion = 'actualizado';
    } else {
        // Agregar nuevo producto
        $_SESSION['carrito'][$item_key] = [
            'id' => $id,
            'tipo' => $tipo,
            'cantidad' => $cantidad,
            'fecha_agregado' => date('Y-m-d H:i:s')
        ];
        $accion = 'agregado';
    }
    
    // Contar total de items en el carrito
    $cart_count = 0;
    foreach ($_SESSION['carrito'] as $item) {
        $cart_count += $item['cantidad'];
    }
    
    // Respuesta exitosa
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Producto ' . $accion . ' correctamente',
        'cart_count' => $cart_count,
        'item_key' => $item_key,
        'accion' => $accion,
        'debug' => [
            'id' => $id,
            'tipo' => $tipo,
            'cantidad' => $cantidad
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Manejo de errores
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ], JSON_UNESCAPED_UNICODE);
}

exit;