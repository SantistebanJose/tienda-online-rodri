<?php
// carrito.php - VERSIÓN MEJORADA Y RESPONSIVE
require_once 'includes/header.php';
require_once 'includes/db.php';

$db = new DB();
$cart = isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? $_SESSION['cart'] : [];
$items = [];
$total = 0.0;

// Verificar si el usuario está logueado
$user_logged = isset($_SESSION['cliente_id']) && !empty($_SESSION['cliente_id']);

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT id, nombre, precio_venta, stock, json_url_img FROM articulo WHERE id IN ($placeholders)";
    
    try {
        $stmt = $db->pdo->prepare($sql);
        foreach ($ids as $i => $id) {
            $stmt->bindValue($i + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $byId = [];
        foreach ($rows as $r) {
            $byId[$r['id']] = $r;
        }

        foreach ($cart as $id => $c_item) {
            $id = intval($id);
            $cantidad = isset($c_item['cantidad']) ? intval($c_item['cantidad']) : 0;
            if ($cantidad <= 0) continue;
            if (!isset($byId[$id])) continue;
            
            $prod = $byId[$id];
            $precio = floatval($prod['precio_venta']);
            $stock = intval($prod['stock']);
            $subtotal = $precio * $cantidad;
            $total += $subtotal;
            
            // Procesar imagen principal con soporte para Drive
            $imagen_url = 'assets/img/productos/placeholder.jpg';
            
            // Incluir funciones si no están
            if (!function_exists('procesarImagenesArticulo')) {
                require_once 'includes/functions.php';
            }
            
            if (!empty($prod['json_url_img'])) {
                // Usar la función centralizada que maneja Drive
                $imagenes = procesarImagenesArticulo($prod['json_url_img']);
                if (!empty($imagenes)) {
                    $imagen_url = $imagenes[0]['url'];
                }
            }
            
            $items[] = [
                'id' => $id,
                'nombre' => $prod['nombre'],
                'precio' => $precio,
                'cantidad' => $cantidad,
                'stock' => $stock,
                'subtotal' => $subtotal,
                'imagen' => $imagen_url
            ];
        }
    } catch (PDOException $e) {
        echo '<div class="notification error show">
                <i class="fas fa-exclamation-triangle"></i>
                <span class="message">Error al cargar el carrito: ' . htmlspecialchars($e->getMessage()) . '</span>
              </div>';
    }
}
?>

<!-- Enlace al CSS del carrito -->
<link rel="stylesheet" href="assets/css/carrito.css">

<section class="carrito-section">
    <div class="container">
        <h1>
            <i class="fas fa-shopping-cart"></i>
            <span>Tu Carrito de Compras</span>
        </h1>

        <?php if (empty($items)): ?>
            <!-- CARRITO VACÍO -->
            <div class="carrito-vacio">
                <div class="vacio-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2>Tu carrito está vacío</h2>
                <p>¡Explora nuestro catálogo y encuentra los mejores productos!</p>
                <a href="index.php" class="btn-primary">
                    <i class="fas fa-store"></i>
                    <span>Ir al Catálogo</span>
                </a>
            </div>
        <?php else: ?>
            <!-- CARRITO CON PRODUCTOS -->
            <div class="carrito-layout">
                <!-- SECCIÓN PRINCIPAL DEL CARRITO -->
                <div class="carrito-principal">
                    <div class="carrito-card">
                        <div class="card-header">
                            <h2>Productos en tu carrito</h2>
                            <span class="items-count"><?= count($items) ?> producto(s)</span>
                        </div>
                        
                        <div class="carrito-items">
                            <?php foreach ($items as $it): ?>
                            <div class="carrito-item" data-id="<?= $it['id'] ?>" data-precio="<?= $it['precio'] ?>">
                                <!-- Imagen del producto -->
                                <div class="item-image">
                                    <img src="<?= htmlspecialchars($it['imagen']) ?>" 
                                         alt="<?= htmlspecialchars($it['nombre']) ?>"
                                         loading="lazy"
                                         onerror="this.src='assets/img/productos/placeholder.jpg'; if(!this.dataset.retried){this.dataset.retried=true; var original=this.src; if(original.includes('drive.google.com')){/*Fallback handled by JS script*/}}">
                                </div>
                                
                                <!-- Información y controles -->
                                <div class="item-content">
                                    <div class="item-info">
                                        <h3><?= htmlspecialchars($it['nombre']) ?></h3>
                                        <p class="item-precio">S/. <?= number_format($it['precio'], 2) ?></p>
                                        <?php if ($it['stock'] < 5): ?>
                                        <span class="stock-warning">
                                            <i class="fas fa-exclamation-circle"></i>
                                            Solo quedan <?= $it['stock'] ?> unidades
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="item-controls">
                                        <!-- Control de cantidad -->
                                        <div class="qty-control">
                                            <button class="qty-btn qty-minus" 
                                                    data-id="<?= $it['id'] ?>" 
                                                    aria-label="Disminuir cantidad">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <input type="number" 
                                                   min="1" 
                                                   max="<?= $it['stock'] ?>"
                                                   value="<?= $it['cantidad'] ?>" 
                                                   class="qty-input" 
                                                   data-id="<?= $it['id'] ?>"
                                                   aria-label="Cantidad">
                                            <button class="qty-btn qty-plus" 
                                                    data-id="<?= $it['id'] ?>" 
                                                    aria-label="Aumentar cantidad">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <!-- Subtotal -->
                                        <div class="item-subtotal">
                                            <span class="subtotal-label">Subtotal:</span>
                                            <span class="subtotal-valor">S/. <?= number_format($it['subtotal'], 2) ?></span>
                                        </div>
                                        
                                        <!-- Botón eliminar -->
                                        <button class="btn-remove" 
                                                data-id="<?= $it['id'] ?>" 
                                                aria-label="Eliminar producto">
                                            <i class="fas fa-trash-alt"></i>
                                            <span>Eliminar</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- RESUMEN DEL PEDIDO -->
                <aside class="carrito-resumen">
                    <div class="resumen-card">
                        <h3>Resumen del pedido</h3>
                        
                        <div class="resumen-detalle">
                            <div class="resumen-item">
                                <span>Subtotal (<?= count($items) ?> productos)</span>
                                <span class="resumen-subtotal">S/. <?= number_format($total, 2) ?></span>
                            </div>
                            
                            <div class="resumen-item">
                                <span>Envío</span>
                                <span class="envio-badge">
                                    <i class="fas fa-truck"></i>
                                    Gratis
                                </span>
                            </div>
                        </div>
                        
                        <div class="resumen-total">
                            <span>Total a pagar</span>
                            <span class="precio-total">S/. <?= number_format($total, 2) ?></span>
                        </div>
                        
                        <?php if (!$user_logged): ?>
                        <div class="login-required">
                            <i class="fas fa-info-circle"></i>
                            <p>Debes iniciar sesión para realizar tu pedido</p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="resumen-actions">
                            <?php if ($user_logged): ?>
                            <button id="btnRealizarPedido" class="btn-checkout">
                                <i class="fas fa-shopping-bag"></i>
                                <span>Realizar Compra</span>
                            </button>
                            <?php else: ?>
                            <a href="login.php?redirect=carrito.php" class="btn-checkout btn-login">
                                <i class="fas fa-sign-in-alt"></i>
                                <span>Iniciar Sesión</span>
                            </a>
                            <?php endif; ?>
                            
                            <button id="vaciarCarrito" class="btn-secondary">
                                <i class="fas fa-trash-alt"></i>
                                <span>Vaciar carrito</span>
                            </button>
                            
                            <a href="index.php" class="btn-tertiary">
                                <i class="fas fa-arrow-left"></i>
                                <span>Seguir comprando</span>
                            </a>
                        </div>
                        
                        <div class="garantias">
                            <div class="garantia-item">
                                <i class="fas fa-shield-alt"></i>
                                <span>Compra segura</span>
                            </div>
                            <div class="garantia-item">
                                <i class="fas fa-truck"></i>
                                <span>Envío gratis</span>
                            </div>
                            <div class="garantia-item">
                                <i class="fas fa-headset"></i>
                                <span>Soporte 24/7</span>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- MODAL DE CONFIRMACIÓN DE PEDIDO -->
<div class="modal" id="modalPedido" role="dialog" aria-labelledby="modalTitle" aria-hidden="true">
    <div class="modal-content">
        <button class="modal-close" id="closeModal" aria-label="Cerrar modal">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="modal-header">
            <i class="fas fa-clipboard-list"></i>
            <h2 id="modalTitle">Confirmar Compra</h2>
        </div>
        
        <div class="modal-body">
            <div class="pedido-resumen">
                <h3>Detalles de tu compra</h3>
                <div class="pedido-items" id="pedidoItems">
                    <!-- Se llena dinámicamente con JavaScript -->
                </div>
                <div class="pedido-total">
                    <span>Total:</span>
                    <span id="pedidoTotal">S/. 0.00</span>
                </div>
            </div>
            
            <div class="pedido-notas">
                <label for="notasPedido">
                    <i class="fas fa-comment-alt"></i>
                    Notas adicionales (opcional)
                </label>
                <textarea id="notasPedido" 
                          placeholder="Ej: Entregar en la tarde, tocar el timbre dos veces..."
                          rows="3"
                          maxlength="200"
                          aria-describedby="notasHint"></textarea>
                <small id="notasHint">0 de 200 caracteres</small>
            </div>
        </div>
        
        <div class="modal-footer">
            <button class="btn-cancel" id="btnCancelar">
                <i class="fas fa-times"></i>
                <span>Cancelar</span>
            </button>
            <button class="btn-confirm" id="btnConfirmarPedido">
                <i class="fas fa-check"></i>
                <span>Confirmar Compra</span>
            </button>
        </div>
    </div>
</div>

<!-- NOTIFICACIÓN -->
<div class="notification" id="notification" role="alert" aria-live="polite">
    <i class="fas fa-check-circle"></i>
    <span class="message"></span>
    <button class="close-btn" onclick="hideNotification()" aria-label="Cerrar notificación">×</button>
</div>

<?php require_once 'includes/footer.php'; ?>

<script>
// ============================================
// SISTEMA DE CARRITO Y PEDIDOS - MEJORADO
// ============================================
const CartManager = {
    config: {
        minQty: 1,
        maxQty: 999,
        notificationDuration: 3000,
        userLogged: <?= $user_logged ? 'true' : 'false' ?>
    },

    // NOTIFICACIONES
    notify(message, type = 'success') {
        const notif = document.getElementById('notification');
        if (!notif) return;
        
        const icon = notif.querySelector('i');
        const msg = notif.querySelector('.message');
        
        msg.textContent = message;
        notif.className = 'notification ' + type;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-exclamation-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle'
        };
        icon.className = 'fas ' + (icons[type] || icons.success);
        
        notif.classList.add('show');
        setTimeout(() => notif.classList.remove('show'), this.config.notificationDuration);
    },

    // VALIDACIÓN DE CANTIDAD
    validateQuantity(value, max = 999) {
        let qty = parseInt(value);
        if (isNaN(qty) || qty < this.config.minQty) return this.config.minQty;
        if (qty > max) {
            this.notify('Cantidad máxima disponible: ' + max, 'warning');
            return max;
        }
        return qty;
    },

    // ACTUALIZAR TOTALES
    updateTotals() {
        let subtotal = 0;
        let itemCount = 0;
        
        document.querySelectorAll('.carrito-item').forEach(row => {
            const precio = parseFloat(row.dataset.precio);
            const cantidad = parseInt(row.querySelector('.qty-input').value);
            const itemSubtotal = precio * cantidad;
            
            row.querySelector('.subtotal-valor').textContent = 'S/. ' + itemSubtotal.toFixed(2);
            subtotal += itemSubtotal;
            itemCount++;
        });
        
        const subtotalEl = document.querySelector('.resumen-subtotal');
        const totalEl = document.querySelector('.precio-total');
        const countEl = document.querySelector('.items-count');
        
        if (subtotalEl) subtotalEl.textContent = 'S/. ' + subtotal.toFixed(2);
        if (totalEl) totalEl.textContent = 'S/. ' + subtotal.toFixed(2);
        if (countEl) countEl.textContent = itemCount + ' producto(s)';
    },

    // ACTUALIZAR BADGE DEL CARRITO
    updateCartBadge(count) {
        const badge = document.getElementById('cartBadge');
        if (badge) {
            badge.textContent = count;
            badge.classList.toggle('active', count > 0);
        }
    },

    // CONTROLES DE CANTIDAD
    setupQuantityControls() {
        document.querySelectorAll('.qty-minus, .qty-plus').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                const input = document.querySelector(`.qty-input[data-id="${id}"]`);
                if (!input) return;
                
                const max = parseInt(input.max);
                let qty = parseInt(input.value);
                
                if (btn.classList.contains('qty-minus')) {
                    qty = Math.max(this.config.minQty, qty - 1);
                } else {
                    qty = Math.min(max, qty + 1);
                }
                
                input.value = qty;
                this.updateQuantity(id, qty);
            });
        });

        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', () => {
                const id = input.dataset.id;
                const max = parseInt(input.max);
                const qty = this.validateQuantity(input.value, max);
                input.value = qty;
                this.updateQuantity(id, qty);
            });
            
            // Prevenir valores inválidos al escribir
            input.addEventListener('keypress', (e) => {
                if (!/[0-9]/.test(e.key)) {
                    e.preventDefault();
                }
            });
        });
    },

    // ACTUALIZAR CANTIDAD
    updateQuantity(id, qty) {
        const item = document.querySelector(`.carrito-item[data-id="${id}"]`);
        if (item) item.classList.add('loading');
        
        updateCartItem(id, qty)
            .then(resp => {
                if (resp.success) {
                    this.updateCartBadge(resp.cart_count);
                    this.updateTotals();
                    this.notify('Cantidad actualizada', 'success');
                } else {
                    throw new Error(resp.message || 'Error al actualizar');
                }
            })
            .catch(error => {
                this.notify(error.message || 'Error al actualizar', 'error');
                location.reload();
            })
            .finally(() => {
                if (item) item.classList.remove('loading');
            });
    },

    // BOTONES ELIMINAR
    setupRemoveButtons() {
        document.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const id = btn.dataset.id;
                const item = btn.closest('.carrito-item');
                if (!item) return;
                
                if (confirm('¿Eliminar este producto del carrito?')) {
                    item.style.opacity = '0.5';
                    btn.disabled = true;
                    
                    removeFromCart(id)
                        .then(resp => {
                            if (resp.success) {
                                this.updateCartBadge(resp.cart_count);
                                this.notify('Producto eliminado', 'success');
                                setTimeout(() => location.reload(), 800);
                            } else {
                                throw new Error(resp.message || 'Error al eliminar');
                            }
                        })
                        .catch(error => {
                            item.style.opacity = '1';
                            btn.disabled = false;
                            this.notify(error.message || 'Error al eliminar', 'error');
                        });
                }
            });
        });
    },

    // VACIAR CARRITO
    setupClearCart() {
        const btn = document.getElementById('vaciarCarrito');
        if (!btn) return;
        
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            if (confirm('¿Estás seguro de vaciar todo el carrito?')) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Vaciando...</span>';
                
                clearCart()
                    .then(resp => {
                        if (resp.success) {
                            this.notify('Carrito vaciado', 'success');
                            setTimeout(() => location.reload(), 800);
                        } else {
                            throw new Error(resp.message || 'Error al vaciar');
                        }
                    })
                    .catch(error => {
                        this.notify(error.message || 'Error al vaciar', 'error');
                        btn.disabled = false;
                        btn.innerHTML = '<i class="fas fa-trash-alt"></i><span>Vaciar carrito</span>';
                    });
            }
        });
    },

    // MODAL DE PEDIDO
    setupPedidoModal() {
        const btnRealizar = document.getElementById('btnRealizarPedido');
        const modal = document.getElementById('modalPedido');
        const closeModal = document.getElementById('closeModal');
        const btnCancelar = document.getElementById('btnCancelar');
        const btnConfirmar = document.getElementById('btnConfirmarPedido');
        const notasTextarea = document.getElementById('notasPedido');
        
        if (!btnRealizar || !modal) return;
        
        // Abrir modal
        btnRealizar.addEventListener('click', () => {
            this.loadPedidoPreview();
            modal.classList.add('show');
            modal.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            closeModal.focus();
        });
        
        // Cerrar modal
        const cerrarModal = () => {
            modal.classList.remove('show');
            modal.setAttribute('aria-hidden', 'true');
            document.body.style.overflow = '';
            btnRealizar.focus();
        };
        
        if (closeModal) closeModal.addEventListener('click', cerrarModal);
        if (btnCancelar) btnCancelar.addEventListener('click', cerrarModal);
        
        modal.addEventListener('click', (e) => {
            if (e.target === modal) cerrarModal();
        });
        
        // ESC para cerrar
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && modal.classList.contains('show')) {
                cerrarModal();
            }
        });
        
        // Contador de caracteres
        if (notasTextarea) {
            notasTextarea.addEventListener('input', () => {
                const small = notasTextarea.nextElementSibling;
                if (small) {
                    small.textContent = notasTextarea.value.length + ' de 200 caracteres';
                }
            });
        }
        
        // Confirmar pedido
        if (btnConfirmar) {
            btnConfirmar.addEventListener('click', () => {
                this.confirmarPedido();
            });
        }
    },

    // CARGAR PREVIEW DEL PEDIDO
    loadPedidoPreview() {
        const container = document.getElementById('pedidoItems');
        const totalEl = document.getElementById('pedidoTotal');
        if (!container || !totalEl) return;
        
        let html = '';
        let total = 0;
        
        document.querySelectorAll('.carrito-item').forEach(item => {
            const nombre = item.querySelector('h3').textContent;
            const precio = parseFloat(item.dataset.precio);
            const cantidad = parseInt(item.querySelector('.qty-input').value);
            const subtotal = precio * cantidad;
            const imagenSrc = item.querySelector('.item-image img')?.src || '';
            total += subtotal;
            
            html += `
                <div class="pedido-item-preview">
                    ${imagenSrc ? `<img src="${imagenSrc}" alt="${nombre}" class="preview-img">` : ''}
                    <span class="item-qty">${cantidad}x</span>
                    <span class="item-name">${nombre}</span>
                    <span class="item-price">S/. ${subtotal.toFixed(2)}</span>
                </div>
            `;
        });
        
        container.innerHTML = html;
        totalEl.textContent = 'S/. ' + total.toFixed(2);
    },

    // CONFIRMAR PEDIDO
    confirmarPedido() {
        const btnConfirmar = document.getElementById('btnConfirmarPedido');
        const notas = document.getElementById('notasPedido')?.value || '';
        
        if (!btnConfirmar) return;
        
        btnConfirmar.disabled = true;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Procesando...</span>';
        
        // Obtener datos del pedido
        const items = [];
        document.querySelectorAll('.carrito-item').forEach(item => {
            items.push({
                id: item.dataset.id,
                cantidad: parseInt(item.querySelector('.qty-input').value),
                precio: parseFloat(item.dataset.precio)
            });
        });
        
        // Enviar compra a procesar_reserva.php
        fetch('procesar_reserva.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                items: items,
                notas: notas
            })
        })
        .then(response => {
            return response.json().then(data => {
                if (!response.ok) {
                    throw new Error(data.message || 'Error del servidor: ' + response.status);
                }
                return data;
            }).catch(e => {
                if (e.message && e.message !== 'Unexpected end of JSON input') throw e;
                throw new Error('Error de conexión o respuesta inválida del servidor');
            });
        })
        .then(data => {
            console.log('Respuesta del servidor:', data);
            if (data.success) {
                document.getElementById('modalPedido').classList.remove('show');
                this.updateCartBadge(0); // Limpiar badge
                this.notify('¡Compra realizada con éxito! Tu reserva #' + data.reserva_id + ' ha sido creada.', 'success');
                setTimeout(() => {
                    window.location.href = 'index.php';
                }, 2500);
            } else {
                const errorMsg = data.message || 'Error al procesar la compra';
                console.error('Error en la compra:', errorMsg);
                if (data.debug && data.debug.length > 0) {
                    console.error('Detalles del error:', data.debug);
                }
                throw new Error(errorMsg);
            }
        })
        .catch(error => {
            console.error('Error capturado:', error);
            this.notify(error.message || 'Error al procesar la compra', 'error');
            btnConfirmar.disabled = false;
            btnConfirmar.innerHTML = '<i class="fas fa-check"></i><span>Confirmar Compra</span>';
        });
    },

    // INICIALIZAR
    init() {
        this.setupQuantityControls();
        this.setupRemoveButtons();
        this.setupClearCart();
        this.setupPedidoModal();
    }
};

// FUNCIONES AUXILIARES PARA CART_ACTIONS.PHP
function updateCartItem(id, cantidad) {
    return fetch('cart_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=update&id=${id}&cantidad=${cantidad}`
    }).then(r => r.json());
}

function removeFromCart(id) {
    return fetch('cart_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=remove&id=${id}`
    }).then(r => r.json());
}

function clearCart() {
    return fetch('cart_actions.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=clear'
    }).then(r => r.json());
}

function hideNotification() {
    const notif = document.getElementById('notification');
    if (notif) notif.classList.remove('show');
}

// INICIALIZAR CUANDO EL DOM ESTÉ LISTO
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => CartManager.init());
} else {
    CartManager.init();
}

// Fallback de imágenes de Drive (similar a index.php)
document.addEventListener('DOMContentLoaded', function() {
    const images = document.querySelectorAll('.item-image img, .preview-img');
    
    images.forEach(img => {
        img.addEventListener('error', function() {
            if (this.dataset.fallbackProcessed) return;
            this.dataset.fallbackProcessed = 'true';
            
            const originalSrc = this.src;
            // Extraer file ID si es una URL de Drive
            const driveIdMatch = originalSrc.match(/[\/=]([a-zA-Z0-9_-]{25,})/);
            
            if (driveIdMatch) {
                const fileId = driveIdMatch[1];
                // Intentar formato alternativo de visualización
                this.src = `https://lh3.googleusercontent.com/d/${fileId}`;
            } else {
                this.src = 'assets/img/productos/placeholder.jpg';
            }
        });
    });
});
</script>