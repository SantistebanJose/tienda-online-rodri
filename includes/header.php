<?php
// includes/header.php

// Iniciar sesión solo si no hay una activa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cargar la clase DB automáticamente
require_once __DIR__ . '/db.php';

// Contador del carrito (sumatoria de cantidades en la sesión)
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c_item) {
        $cart_count += isset($c_item['cantidad']) ? (int)$c_item['cantidad'] : 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title : 'Librería Bazar Rodri' ?></title>
    
    <!-- CSS Principal -->
    <link rel="stylesheet" href="assets/css/estilos_prodyserv.css">
    <link rel="stylesheet" href="assets/css/carrito.css">
    <link rel="stylesheet" href="assets/css/producto-detalle.css">
    <link rel="stylesheet" href="assets/css/catalog.css">

    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Meta tags adicionales para SEO -->
    <meta name="description" content="Librería Bazar Rodri - Tu tienda de confianza para libros, útiles escolares y más">
    <meta name="keywords" content="librería, útiles escolares, libros, bazar, papelería">
    <meta name="author" content="Librería Bazar Rodri">
    
    <!-- Favicon (opcional) -->
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
</head>
<body>
    <header class="main-header">
        <nav class="navbar">
            <div class="nav-container">
                <a href="index.php" class="logo">
                    <i class="fas fa-book-open"></i>
                    <span>Librería Bazar Rodri</span>
                </a>
                
                <button class="menu-toggle" id="menuToggle" aria-label="Abrir menú">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
                
                <ul class="nav-menu" id="navMenu">
                    <li><a href="index.php" class="nav-link"><i class="fas fa-home"></i> Catálogo</a></li>
                    
                    <?php if (isset($_SESSION['cliente_id'])): ?>
                        <li><a href="mis_pedidos.php" class="nav-link"><i class="fas fa-clipboard-list"></i> Mis Pedidos</a></li>
                        <li>
                            <span class="user-welcome">Hola, <?= htmlspecialchars($_SESSION['cliente_nombre'] ?? 'Cliente') ?></span>
                        </li>
                        <li><a href="logout.php" class="nav-link btn-logout"><i class="fas fa-sign-out-alt"></i> Cerrar Sesión</a></li>
                    <?php else: ?>
                        <li><a href="login.php" class="nav-link"><i class="fas fa-sign-in-alt"></i> Iniciar Sesión</a></li>
                        <li><a href="registro.php" class="nav-link"><i class="fas fa-user-plus"></i> Regístrate</a></li>
                    <?php endif; ?>
                    
                    <li><a href="carrito.php" class="nav-link cart-link">
                        <i class="fas fa-shopping-cart"></i> 
                        Carrito
                        <span class="cart-badge" id="cartBadge"><?= $cart_count ?></span>
                    </a></li>
                </ul>
            </div>
        </nav>
    </header>
    <main class="main-content">