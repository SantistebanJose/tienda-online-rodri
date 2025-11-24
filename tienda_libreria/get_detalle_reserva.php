<?php
// get_detalle_reserva.php - API para obtener el detalle de una reserva
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

// Verificar que el usuario esté logueado
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Debes iniciar sesión']);
    exit;
}

// Verificar parámetro ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID de reserva no proporcionado']);
    exit;
}

$db = new DB();
$usuario_id = intval($_SESSION['user_id']);
$reserva_id = intval($_GET['id']);

try {
    // Obtener datos de la reserva
    $stmt = $db->pdo->prepare("
        SELECT 
            r.id,
            r.fecha_reserva,
            r.estado,
            r.total,
            r.notas,
            TO_CHAR(r.fecha_reserva, 'DD/MM/YYYY HH24:MI') as fecha_formateada
        FROM reserva_web r
        WHERE r.id = $1 AND r.usuario_id = $2
    ");
    $stmt->execute([$reserva_id, $usuario_id]);
    $reserva = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reserva) {
        throw new Exception('Reserva no encontrada');
    }

    // Mapeo de estados
    $estados_info = [
        'pendiente' => ['color' => 'warning', 'icon' => 'fa-clock', 'texto' => 'Pendiente'],
        'confirmado' => ['color' => 'info', 'icon' => 'fa-check-circle', 'texto' => 'Confirmado'],
        'preparando' => ['color' => 'primary', 'icon' => 'fa-box', 'texto' => 'Preparando'],
        'listo' => ['color' => 'success', 'icon' => 'fa-check-double', 'texto' => 'Listo para recoger'],
        'entregado' => ['color' => 'secondary', 'icon' => 'fa-handshake', 'texto' => 'Entregado'],
        'cancelado' => ['color' => 'danger', 'icon' => 'fa-times-circle', 'texto' => 'Cancelado']
    ];

    $info_estado = $estados_info[$reserva['estado']] ?? $estados_info['pendiente'];

    // Obtener items de la reserva
    $stmt = $db->pdo->prepare("
        SELECT 
            d.id,
            d.cantidad,
            d.precio_unitario,
            d.subtotal,
            a.nombre,
            a.json_url_img
        FROM detalle_reserva_web d
        INNER JOIN articulo a ON d.articulo_id = a.id
        WHERE d.reserva_id = $1
        ORDER BY d.id
    ");
    $stmt->execute([$reserva_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar imágenes de los items
    foreach ($items as &$item) {
        $imagen_url = 'assets/img/productos/placeholder.jpg';
        
        if (!empty($item['json_url_img'])) {
            $json_decoded = json_decode($item['json_url_img'], true);
            if (is_array($json_decoded) && !empty($json_decoded)) {
                usort($json_decoded, function($a, $b) {
                    return ($a['index'] ?? 0) - ($b['index'] ?? 0);
                });
                $imagen_url = $json_decoded[0]['url'] ?? $imagen_url;
            }
        }
        
        $item['imagen'] = $imagen_url;
        unset($item['json_url_img']); // No enviar el JSON al frontend
    }

    // Preparar respuesta
    echo json_encode([
        'success' => true,
        'reserva' => [
            'id' => $reserva['id'],
            'fecha_reserva' => $reserva['fecha_formateada'],
            'estado' => $reserva['estado'],
            'estado_color' => $info_estado['color'],
            'estado_icon' => $info_estado['icon'],
            'estado_texto' => $info_estado['texto'],
            'total' => $reserva['total'],
            'notas' => $reserva['notas']
        ],
        'items' => $items
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}