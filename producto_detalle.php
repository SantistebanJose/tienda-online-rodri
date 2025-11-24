<?php
// producto_detalle.php - Página de detalle del producto
$page_title = "Detalle del Producto";
require_once 'includes/header.php';
require_once 'includes/functions.php';

$db = new DB();

// Obtener el ID del producto desde GET
$producto_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($producto_id <= 0) {
    header('Location: index.php');
    exit;
}

// Obtener datos del producto
try {
    $stmt = $db->pdo->prepare("
        SELECT 
        a.id, a.nombre, a.precio_venta, a.stock, a.marca, a.categoria_id,
        a.json_url_img, a.descripcion,
        c.descripcion as categoria_nombre 
    FROM articulo a
    LEFT JOIN categoria c ON a.categoria_id = c.id
    WHERE a.id = :id AND a.deleted_at IS NULL
    ");
    $stmt->bindParam(':id', $producto_id, PDO::PARAM_INT);
    $stmt->execute();
    $producto = $stmt->fetch();
    
    if (!$producto) {
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    echo '<p class="error">Error al cargar el producto: ' . $e->getMessage() . '</p>';
    exit;
}

// Procesar imágenes usando la función que soporta Drive
$imagenes = procesarImagenesArticulo($producto['json_url_img']);

// Si no hay imágenes, usar placeholder
if (empty($imagenes)) {
    $imagenes = [['url' => 'assets/img/productos/placeholder.jpg', 'index' => 0, 'source' => 'web']];
}

// Obtener productos relacionados (misma categoría)
$productos_relacionados = [];
if ($producto['categoria_id']) {
    try {
        $stmt = $db->pdo->prepare("
            SELECT 
                a.id, a.nombre, a.precio_venta, a.stock, a.marca,
                a.json_url_img,
                c.descripcion as categoria_nombre 
            FROM articulo a
            LEFT JOIN categoria c ON a.categoria_id = c.id
            WHERE a.categoria_id = :categoria_id 
            AND a.id != :producto_id 
            AND a.stock > 0 
            AND a.deleted_at IS NULL
            ORDER BY RANDOM()
            LIMIT 4
        ");
        $stmt->bindParam(':categoria_id', $producto['categoria_id'], PDO::PARAM_INT);
        $stmt->bindParam(':producto_id', $producto_id, PDO::PARAM_INT);
        $stmt->execute();
        $productos_relacionados = $stmt->fetchAll();
    } catch (PDOException $e) {
        $productos_relacionados = [];
    }
}
?>

<div class="product-detail-container">
    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <a href="index.php"><i class="fas fa-home"></i> Catálogo</a>
        <span class="separator">/</span>
        <?php if (!empty($producto['categoria_nombre'])): ?>
            <a href="index.php?categoria=<?= $producto['categoria_id'] ?>"><?= htmlspecialchars($producto['categoria_nombre']) ?></a>
            <span class="separator">/</span>
        <?php endif; ?>
        <span class="current"><?= htmlspecialchars($producto['nombre']) ?></span>
    </nav>
    
    <!-- Contenido principal del producto -->
    <div class="product-detail-content">
        <!-- Galería de imágenes -->
        <div class="product-gallery">
            <div class="main-image-container">
                <img id="mainImage" 
                     src="<?= htmlspecialchars($imagenes[0]['url']) ?>" 
                     alt="<?= htmlspecialchars($producto['nombre']) ?>"
                     class="main-product-image"
                     onerror="this.src='assets/img/no-image.png'">
                
                <button class="zoom-btn" id="zoomBtn" title="Ver en tamaño completo">
                    <i class="fas fa-search-plus"></i>
                </button>
                
                <?php if ($producto['stock'] <= 5): ?>
                    <span class="stock-alert <?= $producto['stock'] <= 2 ? 'critical' : '' ?>">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= $producto['stock'] <= 2 ? '¡Últimas unidades!' : 'Stock limitado' ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if (count($imagenes) > 1): ?>
                <div class="thumbnail-gallery">
                    <?php foreach ($imagenes as $idx => $img): ?>
                        <div class="thumbnail-item <?= $idx === 0 ? 'active' : '' ?>" 
                             data-index="<?= $idx ?>"
                             onclick="changeMainImage('<?= htmlspecialchars($img['url']) ?>', this)">
                            <img src="<?= htmlspecialchars($img['url']) ?>" 
                                 alt="Imagen <?= $idx + 1 ?>"
                                 onerror="this.src='assets/img/no-image.png'">
                            <?php if (isset($img['source']) && $img['source'] === 'drive'): ?>
                                <span class="thumbnail-badge drive">
                                    <i class="fab fa-google-drive"></i>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Información del producto -->
        <div class="product-info-detail">
            <h1 class="product-title"><?= htmlspecialchars($producto['nombre']) ?></h1>
            
            <div class="product-meta-info">
                <?php if (!empty($producto['categoria_nombre'])): ?>
                    <span class="meta-badge category">
                        <i class="fas fa-tags"></i> 
                        <?= htmlspecialchars($producto['categoria_nombre']) ?>
                    </span>
                <?php endif; ?>
                
                <?php if (!empty($producto['marca'])): ?>
                    <span class="meta-badge brand">
                        <i class="fas fa-copyright"></i> 
                        <?= htmlspecialchars($producto['marca']) ?>
                    </span>
                <?php endif; ?>
                
                <span class="meta-badge stock <?= $producto['stock'] <= 5 ? 'low' : '' ?>">
                    <i class="fas fa-box"></i> 
                    Stock: <?= $producto['stock'] ?>
                </span>
            </div>
            
            <div class="price-section">
                <p class="product-price-large">
                    S/. <?= number_format($producto['precio_venta'], 2) ?>
                </p>
                <p class="price-note">
                    <i class="fas fa-info-circle"></i> 
                    Precio incluye IGV
                </p>
            </div>
            
            <?php if (!empty($producto['descripcion'])): ?>
                <div class="product-description">
                    <h3><i class="fas fa-file-alt"></i> Descripción</h3>
                    <p><?= nl2br(htmlspecialchars($producto['descripcion'])) ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Controles de cantidad y agregar al carrito -->
            <div class="purchase-section">
                <div class="quantity-control">
                    <label for="cantidad">
                        <i class="fas fa-sort-numeric-up"></i> Cantidad:
                    </label>
                    <div class="quantity-input-group">
                        <button type="button" class="qty-btn" id="decreaseQty">
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" 
                               id="cantidad" 
                               value="1" 
                               min="1" 
                               max="<?= $producto['stock'] ?>" 
                               class="quantity-input">
                        <button type="button" class="qty-btn" id="increaseQty">
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                </div>
                
                <button class="btn-add-to-cart-detail" 
                        id="addToCartBtn"
                        data-id="<?= $producto['id'] ?>" 
                        data-tipo="articulo"
                        <?= $producto['stock'] <= 0 ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-cart"></i>
                    <?= $producto['stock'] > 0 ? 'Añadir al Carrito' : 'Sin Stock' ?>
                </button>
                
                <a href="index.php" class="btn-continue-shopping">
                    <i class="fas fa-arrow-left"></i>
                    Seguir Comprando
                </a>
            </div>
            
            <!-- Información adicional -->
            <div class="additional-info">
                <div class="info-item">
                    <i class="fas fa-truck"></i>
                    <div>
                        <strong>Entrega</strong>
                        <p>Disponible en tienda</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Garantía</strong>
                        <p>Producto original</p>
                    </div>
                </div>
                <div class="info-item">
                    <i class="fas fa-headset"></i>
                    <div>
                        <strong>Atención</strong>
                        <p>+51 916 529 268</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Productos relacionados -->
    <?php if (!empty($productos_relacionados)): ?>
        <section class="related-products">
            <h2>
                <i class="fas fa-cubes"></i> 
                Productos Relacionados
            </h2>
            
            <div class="related-products-grid">
                <?php foreach ($productos_relacionados as $relacionado): 
                    // Usar la función para procesar imágenes (soporta Drive)
                    $imagenes_relacionado = procesarImagenesArticulo($relacionado['json_url_img']);
                    $img_relacionado = !empty($imagenes_relacionado) 
                        ? $imagenes_relacionado[0]['url'] 
                        : 'assets/img/productos/placeholder.jpg';
                ?>
                    <a href="producto_detalle.php?id=<?= $relacionado['id'] ?>" class="related-product-card">
                        <div class="related-product-image">
                            <img src="<?= htmlspecialchars($img_relacionado) ?>" 
                                 alt="<?= htmlspecialchars($relacionado['nombre']) ?>"
                                 onerror="this.src='assets/img/no-image.png'">
                        </div>
                        <div class="related-product-info">
                            <h3><?= htmlspecialchars($relacionado['nombre']) ?></h3>
                            <?php if (!empty($relacionado['marca'])): ?>
                                <p class="related-brand">
                                    <i class="fas fa-copyright"></i>
                                    <?= htmlspecialchars($relacionado['marca']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="related-price">
                                S/. <?= number_format($relacionado['precio_venta'], 2) ?>
                            </p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<!-- Modal de zoom -->
<div class="image-zoom-modal" id="imageZoomModal">
    <button class="close-zoom" id="closeZoom">
        <i class="fas fa-times"></i>
    </button>
    <img src="" alt="Imagen ampliada" id="zoomedImage">
</div>

<!-- Script optimizado para producto_detalle -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cantidad = document.getElementById('cantidad');
    const decreaseBtn = document.getElementById('decreaseQty');
    const increaseBtn = document.getElementById('increaseQty');
    const addToCartBtn = document.getElementById('addToCartBtn');
    const maxStock = <?= $producto['stock'] ?>;
    
    // ================================================
    // FALLBACK SIMPLE PARA IMÁGENES
    // ================================================
    document.querySelectorAll('img').forEach(img => {
        img.addEventListener('error', function() {
            this.src = 'assets/img/no-image.png';
        });
    });
    
    // ================================================
    // CONTROL DE CANTIDAD
    // ================================================
    decreaseBtn.addEventListener('click', function() {
        let val = parseInt(cantidad.value) || 1;
        if (val > 1) {
            cantidad.value = val - 1;
        }
    });
    
    increaseBtn.addEventListener('click', function() {
        let val = parseInt(cantidad.value) || 1;
        if (val < maxStock) {
            cantidad.value = val + 1;
        }
    });
    
    cantidad.addEventListener('input', function() {
        let val = parseInt(this.value) || 1;
        if (val < 1) this.value = 1;
        if (val > maxStock) this.value = maxStock;
    });
    
    // ================================================
    // AGREGAR AL CARRITO
    // ================================================
    addToCartBtn.addEventListener('click', function() {
        const itemId = this.getAttribute('data-id');
        const itemType = this.getAttribute('data-tipo');
        const cantidadVal = cantidad.value;
        
        const originalContent = this.innerHTML;
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
        this.disabled = true;
        
        fetch('cart_actions.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=add&id=${itemId}&tipo=${itemType}&cantidad=${cantidadVal}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const cartBadge = document.getElementById('cartBadge');
                const floatingBadge = document.getElementById('floatingCartBadge');
                
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count || parseInt(cartBadge.textContent) + parseInt(cantidadVal);
                }
                if (floatingBadge) {
                    floatingBadge.textContent = cartBadge.textContent;
                    floatingBadge.style.display = 'flex';
                }
                
                this.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
                this.style.background = 'linear-gradient(135deg, #27ae60 0%, #229954 100%)';
                
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
            } else {
                this.innerHTML = '<i class="fas fa-times"></i> Error';
                this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
                
                setTimeout(() => {
                    this.innerHTML = originalContent;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            this.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error de conexión';
            this.style.background = 'linear-gradient(135deg, #e74c3c 0%, #c0392b 100%)';
            
            setTimeout(() => {
                this.innerHTML = originalContent;
                this.style.background = '';
                this.disabled = false;
            }, 2000);
        });
    });
    
    // ================================================
    // ZOOM DE IMAGEN
    // ================================================
    const zoomBtn = document.getElementById('zoomBtn');
    const zoomModal = document.getElementById('imageZoomModal');
    const closeZoom = document.getElementById('closeZoom');
    const mainImage = document.getElementById('mainImage');
    const zoomedImage = document.getElementById('zoomedImage');
    
    zoomBtn.addEventListener('click', function() {
        zoomedImage.src = mainImage.src;
        zoomModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    });
    
    closeZoom.addEventListener('click', function() {
        zoomModal.classList.remove('active');
        document.body.style.overflow = '';
    });
    
    zoomModal.addEventListener('click', function(e) {
        if (e.target === zoomModal) {
            zoomModal.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

// Cambiar imagen principal al hacer clic en thumbnail
function changeMainImage(url, thumbnail) {
    const mainImage = document.getElementById('mainImage');
    mainImage.src = url;
    
    // Actualizar thumbnails activos
    document.querySelectorAll('.thumbnail-item').forEach(t => t.classList.remove('active'));
    thumbnail.classList.add('active');
}
</script>

<!-- CSS adicional para el badge de Drive en thumbnails -->
<style>
.thumbnail-badge {
    position: absolute;
    top: 3px;
    right: 3px;
    background: rgba(66, 133, 244, 0.9);
    color: white;
    padding: 2px 5px;
    border-radius: 3px;
    font-size: 9px;
    pointer-events: none;
    z-index: 10;
}

.thumbnail-badge.drive {
    background: rgba(66, 133, 244, 0.95);
}

.thumbnail-item {
    position: relative;
}
</style>

<?php
require_once 'includes/footer.php';
?>