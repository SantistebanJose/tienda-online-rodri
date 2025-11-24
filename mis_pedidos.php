<?php
// mis_pedidos.php - Historial de Compras Optimizado
$page_title = "Mis Pedidos";
require_once 'includes/header.php';
require_once 'includes/functions.php'; // Para procesar imágenes

// Verificar autenticación
$user_id = isset($_SESSION['cliente_id']) ? intval($_SESSION['cliente_id']) : 0;
if ($user_id <= 0) {
    echo "<script>window.location.href='login.php?redirect=mis_pedidos.php';</script>";
    exit;
}

$db = new DB();

// 1. OPTIMIZACIÓN SQL: Traer todo en una sola consulta estructurada
$sql = "
    SELECT 
        r.id as reserva_id, r.fecha_reserva, r.estado, r.total, r.notas,
        d.id as detalle_id, d.cantidad, d.precio_unitario, d.subtotal,
        a.nombre as articulo_nombre, a.json_url_img
    FROM reserva_web r
    LEFT JOIN detalle_reserva_web d ON r.id = d.reserva_id
    LEFT JOIN articulo a ON d.articulo_id = a.id
    WHERE r.usuario_id = ?
    ORDER BY r.fecha_reserva DESC
";

$stmt = $db->pdo->prepare($sql);
$stmt->execute([$user_id]);
$raw_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Procesar datos para agrupar por pedido
$pedidos = [];
foreach ($raw_data as $row) {
    $rid = $row['reserva_id'];
    if (!isset($pedidos[$rid])) {
        $pedidos[$rid] = [
            'id' => $rid,
            'fecha' => $row['fecha_reserva'],
            'estado' => $row['estado'],
            'total' => $row['total'],
            'notas' => $row['notas'],
            'items' => []
        ];
    }
    // Agregar items si existen
    if ($row['detalle_id']) {
        $pedidos[$rid]['items'][] = [
            'nombre' => $row['articulo_nombre'],
            'cantidad' => $row['cantidad'],
            'precio' => $row['precio_unitario'],
            'img' => obtenerImagenPrincipal($row['json_url_img'])
        ];
    }
}

// Helpers para la vista
function getEstadoInfo($estado) {
    $estados = [
        'pendiente' => ['icon' => 'fa-clock', 'class' => 'status-warning', 'step' => 1],
        'confirmado' => ['icon' => 'fa-clipboard-check', 'class' => 'status-info', 'step' => 2],
        'preparando' => ['icon' => 'fa-box-open', 'class' => 'status-primary', 'step' => 3],
        'listo' => ['icon' => 'fa-check-circle', 'class' => 'status-success', 'step' => 4],
        'entregado' => ['icon' => 'fa-handshake', 'class' => 'status-success', 'step' => 5],
        'cancelado' => ['icon' => 'fa-times-circle', 'class' => 'status-danger', 'step' => 0]
    ];
    return $estados[$estado] ?? ['icon' => 'fa-circle', 'class' => 'status-secondary', 'step' => 0];
}
?>

<link rel="stylesheet" href="assets/css/mis_pedidos.css">

<section class="pedidos-section">
    <div class="container">
        <!-- Encabezado -->
        <div class="dashboard-header">
            <div class="header-title">
                <h1><i class="fas fa-shopping-bag"></i> Mis Pedidos</h1>
                <p>Historial y seguimiento de tus compras</p>
            </div>
            
            <?php if (!empty($pedidos)): ?>
            <div class="stats-mini">
                <div class="stat-box">
                    <span class="stat-num"><?= count($pedidos) ?></span>
                    <span class="stat-label">Pedidos</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (empty($pedidos)): ?>
            <div class="empty-state">
                <div class="empty-icon"><i class="fas fa-shopping-cart"></i></div>
                <h3>Aún no has realizado compras</h3>
                <p>Explora nuestro catálogo y encuentra lo que buscas.</p>
                <a href="index.php" class="btn-primary">Ir al Catálogo</a>
            </div>
        <?php else: ?>

            <div class="orders-grid">
                <?php foreach ($pedidos as $pedido): 
                    $info = getEstadoInfo($pedido['estado']);
                    $es_activo = in_array($pedido['estado'], ['pendiente', 'confirmado', 'preparando']);
                ?>
                <article class="order-card fade-in">
                    <!-- Cabecera de la tarjeta (Siempre visible) -->
                    <div class="order-summary" onclick="toggleOrder(<?= $pedido['id'] ?>)">
                        <div class="order-id-date">
                            <span class="id-badge">#<?= str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) ?></span>
                            <span class="date-text"><?= date('d M Y, h:i A', strtotime($pedido['fecha'])) ?></span>
                        </div>
                        
                        <div class="order-status-badge <?= $info['class'] ?>">
                            <i class="fas <?= $info['icon'] ?>"></i>
                            <?= ucfirst($pedido['estado']) ?>
                        </div>

                        <div class="order-price">
                            S/. <?= number_format($pedido['total'], 2) ?>
                        </div>
                        
                        <button class="btn-toggle" aria-label="Ver detalles">
                            <i class="fas fa-chevron-down"></i>
                        </button>
                    </div>

                    <!-- Detalles (Expandible) -->
                    <div class="order-details" id="order-<?= $pedido['id'] ?>">
                        
                        <!-- Barra de Progreso Visual -->
                        <?php if ($pedido['estado'] !== 'cancelado'): ?>
                        <div class="progress-track">
                            <?php 
                            $steps = ['Recibido', 'Confirmado', 'Preparando', 'Listo', 'Entregado'];
                            foreach ($steps as $index => $label): 
                                $stepNum = $index + 1;
                                $isActive = $stepNum <= $info['step'] ? 'active' : '';
                            ?>
                            <div class="step <?= $isActive ?>">
                                <div class="step-icon"></div>
                                <span class="step-label"><?= $label ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Lista de Productos -->
                        <div class="items-list">
                            <?php foreach ($pedido['items'] as $item): ?>
                            <div class="item-row">
                                <div class="item-thumb">
                                    <img src="<?= htmlspecialchars($item['img']) ?>" 
                                         alt="Producto" 
                                         loading="lazy"
                                         onerror="this.src='assets/img/productos/placeholder.jpg'">
                                    <span class="item-qty">x<?= $item['cantidad'] ?></span>
                                </div>
                                <div class="item-data">
                                    <h4><?= htmlspecialchars($item['nombre']) ?></h4>
                                    <span class="item-price">S/. <?= number_format($item['precio'], 2) ?></span>
                                </div>
                                <div class="item-total">
                                    S/. <?= number_format($item['precio'] * $item['cantidad'], 2) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Notas y Acciones -->
                        <div class="order-footer">
                            <?php if ($pedido['notas']): ?>
                                <div class="user-note">
                                    <i class="fas fa-comment-alt"></i> <strong>Nota:</strong> <?= htmlspecialchars($pedido['notas']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="actions-row">
                                <?php if ($pedido['estado'] === 'pendiente'): ?>
                                    <button class="btn-cancel" onclick="cancelarPedido(<?= $pedido['id'] ?>)">
                                        <i class="fas fa-times"></i> Cancelar Pedido
                                    </button>
                                <?php endif; ?>
                                <a href="https://wa.me/51999999999?text=Hola, consulto por mi pedido #<?= $pedido['id'] ?>" target="_blank" class="btn-whatsapp">
                                    <i class="fab fa-whatsapp"></i> Ayuda
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- Notificación Flotante -->
<div class="notification" id="notification">
    <i class="fas fa-info-circle"></i>
    <span class="message"></span>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
function toggleOrder(id) {
    const details = document.getElementById(`order-${id}`);
    const card = details.closest('.order-card');
    const icon = card.querySelector('.btn-toggle i');
    
    if (details.style.maxHeight) {
        details.style.maxHeight = null;
        card.classList.remove('expanded');
    } else {
        // Cerrar otros
        document.querySelectorAll('.order-details').forEach(el => el.style.maxHeight = null);
        document.querySelectorAll('.order-card').forEach(el => el.classList.remove('expanded'));
        
        details.style.maxHeight = details.scrollHeight + "px";
        card.classList.add('expanded');
    }
}

function cancelarPedido(id) {
    if(!confirm('¿Estás seguro de cancelar este pedido?')) return;
    
    fetch('procesar_pedido.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'cancelar_reserva', reserva_id: id})
    })
    .then(r => r.json())
    .then(data => {
        showNotif(data.success ? 'Pedido cancelado' : 'Error al cancelar', data.success ? 'success' : 'error');
        if(data.success) setTimeout(() => location.reload(), 1500);
    });
}

function showNotif(msg, type) {
    const n = document.getElementById('notification');
    n.querySelector('.message').textContent = msg;
    n.className = `notification ${type} show`;
    setTimeout(() => n.classList.remove('show'), 3000);
}
</script>
