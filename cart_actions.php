<?php
// cart_actions.php
session_start();
header('Content-Type: application/json; charset=utf-8');

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

// Asegurar estructura de carrito en sesiÃ³n
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

switch ($action) {
    case 'add':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $cantidad = isset($_POST['cantidad']) ? max(1, intval($_POST['cantidad'])) : 1;
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID invÃ¡lido']);
            exit;
        }

        // Si ya existe, incrementar
        if (isset($_SESSION['cart'][$id])) {
            $_SESSION['cart'][$id]['cantidad'] += $cantidad;
        } else {
            $_SESSION['cart'][$id] = ['id' => $id, 'cantidad' => $cantidad, 'tipo' => 'articulo'];
        }

        $count = 0; foreach ($_SESSION['cart'] as $c) $count += isset($c['cantidad']) ? (int)$c['cantidad'] : 0;
        echo json_encode(['success' => true, 'message' => 'Producto agregado', 'cart_count' => $count]);
        exit;

    case 'remove':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0 && isset($_SESSION['cart'][$id])) {
            unset($_SESSION['cart'][$id]);
        }
        $count = 0; foreach ($_SESSION['cart'] as $c) $count += isset($c['cantidad']) ? (int)$c['cantidad'] : 0;
        echo json_encode(['success' => true, 'message' => 'Producto eliminado', 'cart_count' => $count]);
        exit;

    case 'update':
        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 0;
        if ($id > 0) {
            if ($cantidad <= 0) {
                if (isset($_SESSION['cart'][$id])) unset($_SESSION['cart'][$id]);
            } else {
                if (!isset($_SESSION['cart'][$id])) {
                    $_SESSION['cart'][$id] = ['id' => $id, 'cantidad' => $cantidad, 'tipo' => 'articulo'];
                } else {
                    $_SESSION['cart'][$id]['cantidad'] = $cantidad;
                }
            }
        }
        $count = 0; foreach ($_SESSION['cart'] as $c) $count += isset($c['cantidad']) ? (int)$c['cantidad'] : 0;
        echo json_encode(['success' => true, 'message' => 'Carrito actualizado', 'cart_count' => $count]);
        exit;

    case 'clear':
        $_SESSION['cart'] = [];
        echo json_encode(['success' => true, 'message' => 'Carrito vaciado', 'cart_count' => 0]);
        exit;

    case 'count':
        $count = 0; foreach ($_SESSION['cart'] as $c) $count += isset($c['cantidad']) ? (int)$c['cantidad'] : 0;
        echo json_encode(['success' => true, 'cart_count' => $count]);
        exit;

    default:
        echo json_encode(['success' => false, 'message' => 'Acción no reconocida']);
        exit;
}