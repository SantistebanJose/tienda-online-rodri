<?php
// index.php - Catálogo Mejorado con Filtros Laterales, Paginación e Imágenes Dinámicas

$page_title = "Catálogo de Artículos y Servicios";
require_once 'includes/header.php';
require_once 'includes/functions.php';

$db = new DB();

// ============================================
// CONFIGURACION DE PAGINACION
// ============================================
$items_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $items_per_page;

// ============================================
// OBTENER FILTROS DISPONIBLES
// ============================================

// Obtener categorías únicas
$categorias = [];
try {
    $stmt = $db->pdo->query("SELECT id, descripcion as nombre FROM categoria WHERE deleted_at IS NULL ORDER BY descripcion");
    $resultado = $stmt->fetchAll();
    // Filtrar categorías vacías o null
    $categorias = array_filter($resultado, function($cat) {
        return !empty(trim($cat['nombre'] ?? ''));
    });
} catch (PDOException $e) {
    $categorias = [];
}

// Obtener marcas únicas
$marcas = [];
try {
    $stmt = $db->pdo->query("SELECT DISTINCT marca FROM articulo WHERE marca IS NOT NULL AND TRIM(marca) != '' AND deleted_at IS NULL ORDER BY marca");
    $marcas = $stmt->fetchAll();
} catch (PDOException $e) {
    $marcas = [];
}

// ============================================
// PROCESAR FILTROS
// ============================================
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_marca = isset($_GET['marca']) ? $_GET['marca'] : '';
$filtro_precio_min = isset($_GET['precio_min']) ? $_GET['precio_min'] : '';
$filtro_precio_max = isset($_GET['precio_max']) ? $_GET['precio_max'] : '';
$filtro_busqueda = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';
// Por defecto mostrar solo artículos
$mostrar = isset($_GET['mostrar']) ? $_GET['mostrar'] : 'articulos';

// ============================================
// OBTENER SERVICIOS (CON PAGINACIÓN)
// ============================================
$servicios = [];
$total_servicios = 0;
if ($mostrar === 'servicios') {
    try {
        // Contar total de servicios
        $sql_count = "SELECT COUNT(*) FROM movimiento WHERE deleted_at IS NULL";
        if (!empty($filtro_busqueda)) {
            $sql_count .= " AND descripcion ILIKE :busqueda";
        }
        $stmt_count = $db->pdo->prepare($sql_count);
        if (!empty($filtro_busqueda)) {
            $stmt_count->bindValue(':busqueda', '%' . $filtro_busqueda . '%');
        }
        $stmt_count->execute();
        $total_servicios = $stmt_count->fetchColumn();
        
        // Obtener servicios paginados
        $sql_servicios = "SELECT id, descripcion as nombre, ruta_php, medidas FROM movimiento WHERE deleted_at IS NULL";
        if (!empty($filtro_busqueda)) {
            $sql_servicios .= " AND descripcion ILIKE :busqueda";
        }
        $sql_servicios .= " ORDER BY id ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->pdo->prepare($sql_servicios);
        if (!empty($filtro_busqueda)) {
            $stmt->bindValue(':busqueda', '%' . $filtro_busqueda . '%');
        }
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $servicios = $stmt->fetchAll();
    } catch (PDOException $e) {
        echo '<p class="error">Error al cargar servicios: ' . $e->getMessage() . '</p>';
    }
}

// ============================================
// OBTENER ARTÍCULOS CON FILTROS (CON PAGINACIÓN)
// ============================================
$articulos = [];
$total_articulos = 0;
if ($mostrar === 'articulos') {
    try {
        // Construir consulta base para contar
        $sql_count = "
            SELECT COUNT(*) 
            FROM articulo a
            WHERE a.stock > 0 AND a.deleted_at IS NULL
        ";
        
        $params = [];
        $where_clauses = [];
        
        if (!empty($filtro_categoria)) {
            $where_clauses[] = "a.categoria_id = :categoria";
            $params[':categoria'] = $filtro_categoria;
        }
        if (!empty($filtro_marca)) {
            $where_clauses[] = "a.marca = :marca";
            $params[':marca'] = $filtro_marca;
        }
        if (!empty($filtro_precio_min)) {
            $where_clauses[] = "a.precio_venta >= :precio_min";
            $params[':precio_min'] = $filtro_precio_min;
        }
        if (!empty($filtro_precio_max)) {
            $where_clauses[] = "a.precio_venta <= :precio_max";
            $params[':precio_max'] = $filtro_precio_max;
        }
        if (!empty($filtro_busqueda)) {
            $where_clauses[] = "a.nombre ILIKE :busqueda";
            $params[':busqueda'] = '%' . $filtro_busqueda . '%';
        }
        
        if (!empty($where_clauses)) {
            $sql_count .= " AND " . implode(" AND ", $where_clauses);
        }
        
        $stmt_count = $db->pdo->prepare($sql_count);
        $stmt_count->execute($params);
        $total_articulos = $stmt_count->fetchColumn();
        
        // Obtener artículos paginados
        $sql_articulos = "
            SELECT 
                a.id, a.nombre, a.precio_venta, a.stock, a.marca, a.categoria_id,
                a.json_url_img,
                c.descripcion as categoria_nombre 
            FROM articulo a
            LEFT JOIN categoria c ON a.categoria_id = c.id
            WHERE a.stock > 0 AND a.deleted_at IS NULL
        ";
        
        if (!empty($where_clauses)) {
            $sql_articulos .= " AND " . implode(" AND ", $where_clauses);
        }
        
        $sql_articulos .= " ORDER BY a.nombre ASC LIMIT :limit OFFSET :offset";
        
        $stmt = $db->pdo->prepare($sql_articulos);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $articulos = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        echo '<p class="error">Error al cargar artículos: ' . $e->getMessage() . '</p>';
    }
}

$total_resultados = ($mostrar === 'servicios') ? $total_servicios : $total_articulos;
$total_pages = ceil($total_resultados / $items_per_page);

// Contar filtros activos
$filtros_activos = 0;
if (!empty($filtro_categoria)) $filtros_activos++;
if (!empty($filtro_marca)) $filtros_activos++;
if (!empty($filtro_precio_min)) $filtros_activos++;
if (!empty($filtro_precio_max)) $filtros_activos++;
if ($mostrar !== 'articulos') $filtros_activos++;
?>

<div class="catalog-container">
    <!-- ============================================
         SIDEBAR DE FILTROS
         ============================================ -->
    <aside class="sidebar-filters" id="sidebarFilters">
        <div class="sidebar-header">
            <h2><i class="fas fa-filter"></i> Filtros</h2>
            <button class="close-sidebar" id="closeSidebar">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <form method="GET" action="index.php" class="filter-form-sidebar">
            <input type="hidden" name="buscar" value="<?= htmlspecialchars($filtro_busqueda ?? '') ?>">
            <input type="hidden" name="page" value="1">
            
            <!-- Filtro: Mostrar -->
            <div class="filter-section">
                <h3 class="filter-title">
                    <i class="fas fa-th-large"></i> Mostrar
                </h3>
                <div class="filter-options">
                    <label class="filter-option">
                        <input type="radio" name="mostrar" value="articulos" <?= $mostrar === 'articulos' ? 'checked' : '' ?>>
                        <span>Catálogo de Productos</span>
                    </label>
                    <label class="filter-option">
                        <input type="radio" name="mostrar" value="servicios" <?= $mostrar === 'servicios' ? 'checked' : '' ?>>
                        <span>Servicios</span>
                    </label>
                </div>
            </div>
            
            <!-- Filtros solo para artículos -->
            <div id="articulos-filters" style="<?= $mostrar === 'servicios' ? 'display: none;' : '' ?>">
                <!-- Filtro: Categoría -->
                <?php if (!empty($categorias)): ?>
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-tags"></i> Categoría
                    </h3>
                    <select name="categoria" class="filter-select-sidebar">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $filtro_categoria == $cat['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($cat['nombre'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Filtro: Marca -->
                <?php if (!empty($marcas)): ?>
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-copyright"></i> Marca
                    </h3>
                    <select name="marca" class="filter-select-sidebar">
                        <option value="">Todas las marcas</option>
                        <?php foreach ($marcas as $marca): ?>
                            <option value="<?= htmlspecialchars($marca['marca'] ?? '') ?>"  
                                    <?= $filtro_marca === ($marca['marca'] ?? '') ? 'selected' : '' ?>>
                                <?= htmlspecialchars(strtoupper($marca['marca'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Filtro: Precio -->
                <div class="filter-section">
                    <h3 class="filter-title">
                        <i class="fas fa-dollar-sign"></i> Rango de Precio
                    </h3>
                    <div class="price-inputs">
                        <input type="number" name="precio_min" placeholder="Mín" 
                               value="<?= htmlspecialchars($filtro_precio_min ?? '') ?>" 
                               step="0.01" min="0" class="price-input">
                         <span class="price-separator">-</span>
                         <input type="number" name="precio_max" placeholder="Máx" 
                               value="<?= htmlspecialchars($filtro_precio_max ?? '') ?>" 
                               step="0.01" min="0" class="price-input">
                    </div>
                </div>
            </div>
            
            <div class="filter-actions-sidebar">
                <button type="submit" class="btn-apply">
                    <i class="fas fa-check"></i> Aplicar
                </button>
                <button type="button" class="btn-clear" onclick="window.location.href='index.php';">
                    <i class="fas fa-redo"></i> Limpiar
                </button>
            </div>
        </form>
    </aside>
    
    <!-- ============================================
         CONTENIDO PRINCIPAL
         ============================================ -->
    <div class="main-catalog-content">
        <!-- Barra de búsqueda y controles -->
        <div class="search-bar-top">
            <button class="btn-toggle-filters" id="toggleFilters">
                <i class="fas fa-sliders-h"></i> Filtros
                <?php if ($filtros_activos > 0): ?>
                    <span class="active-filters-badge"><?= $filtros_activos ?></span>
                <?php endif; ?>
            </button>
            
            <form method="GET" action="index.php" class="search-form-top">
                <input type="hidden" name="mostrar" value="<?= htmlspecialchars($mostrar ?? '') ?>">
                <div class="search-input-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" name="buscar" placeholder="Buscar productos o servicios..." 
                           value="<?= htmlspecialchars($filtro_busqueda ?? '') ?>">
                </div>
                <button type="submit" class="btn-search-top">
                    Buscar
                </button>
            </form>
        </div>
        
        <!-- Información de resultados -->
        <div class="results-header">
            <h1><?= $mostrar === 'servicios' ? 'Nuestros Servicios' : 'Nuestro Catálogo de Productos' ?></h1>
            <p class="results-count">
                <i class="fas fa-<?= $mostrar === 'servicios' ? 'tools' : 'box' ?>"></i> 
                Mostrando <strong><?= count($servicios) + count($articulos) ?></strong> de 
                <strong><?= $total_resultados ?></strong> resultado<?= $total_resultados !== 1 ? 's' : '' ?>
            </p>
        </div>
        
        <!-- SERVICIOS -->
        <?php if (!empty($servicios) && $mostrar === 'servicios'): ?>
            <div class="products-grid services-grid">
                <?php foreach ($servicios as $servicio): ?>
                    <div class="product-card service-card">
                        <div class="service-icon">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="product-info">
                            <h3><?= htmlspecialchars($servicio['nombre']) ?></h3>
                            
                            <?php if (!empty($servicio['medidas'])): ?>
                                <p class="service-detail">
                                    <i class="fas fa-ruler-combined"></i> 
                                    <?= htmlspecialchars($servicio['medidas']) ?>
                                </p>
                            <?php endif; ?>
                            
                            <p class="service-quote">
                                <i class="fas fa-info-circle"></i> 
                                Solicitar cotización
                            </p>
                            
                            <button class="btn-add-cart" data-id="<?= $servicio['id'] ?>" data-tipo="servicio">
                                <i class="fas fa-calendar-check"></i> Solicitar
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- ARTÍCULOS -->
        <?php if (!empty($articulos) && $mostrar === 'articulos'): ?>
            <div class="products-grid articles-grid">
                <?php foreach ($articulos as $articulo): 
                    // Procesar JSON de imágenes con soporte para Drive
                    $imagenes = procesarImagenesArticulo($articulo['json_url_img']);
                    $tiene_multiples_imagenes = count($imagenes) > 1;
                ?>
                    <div class="product-card article-card">
                        <div class="product-image-container">
                        <?php if (!empty($imagenes)): ?>
                            <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="product-link-wrapper">
                                <div class="image-carousel" data-product-id="<?= $articulo['id'] ?>">
                                    <div class="carousel-images">
                                        <?php foreach ($imagenes as $idx => $img): ?>
                                            <img src="<?= htmlspecialchars($img['url']) ?>" 
                                                alt="<?= htmlspecialchars($articulo['nombre']) ?> - Imagen <?= $idx + 1 ?>"
                                                class="carousel-image <?= $idx === 0 ? 'active' : '' ?>"
                                                data-index="<?= $idx ?>"
                                                loading="lazy"
                                                onerror="this.src='assets/img/no-image.png'">
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <?php if ($tiene_multiples_imagenes): ?>
                                        <button class="carousel-control prev" data-direction="prev" aria-label="Imagen anterior" onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fas fa-chevron-left"></i>
                                        </button>
                                        <button class="carousel-control next" data-direction="next" aria-label="Imagen siguiente" onclick="event.preventDefault(); event.stopPropagation();">
                                            <i class="fas fa-chevron-right"></i>
                                        </button>
                                        
                                        <div class="image-counter">
                                            <span class="current-image">1</span>/<span class="total-images"><?= count($imagenes) ?></span>
                                        </div>
                                        
                                        <div class="carousel-thumbnails" onclick="event.preventDefault(); event.stopPropagation();">
                                            <?php foreach ($imagenes as $idx => $img): ?>
                                                <div class="thumbnail-wrapper <?= $idx === 0 ? 'active' : '' ?>" data-index="<?= $idx ?>">
                                                    <img src="<?= htmlspecialchars($img['url']) ?>" 
                                                        alt="Miniatura <?= $idx + 1 ?>"
                                                        loading="lazy"
                                                        onerror="this.src='assets/img/no-image.png'">
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php else: ?>
                            <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="product-image">
                                <img src="assets/img/productos/placeholder.jpg" 
                                    alt="<?= htmlspecialchars($articulo['nombre']) ?>"
                                    loading="lazy"
                                    onerror="this.src='assets/img/no-image.png'">
                            </a>
                        <?php endif; ?>
                        
                        <!-- Stock Badge - SIEMPRE VISIBLE -->
                        <span class="stock-badge">
                            <i class="fas fa-box"></i> Stock: <?= $articulo['stock'] ?>
                        </span>
                        
                        <!-- Categoría Badge -->
                        <?php if (!empty($articulo['categoria_nombre'])): ?>
                            <span class="category-badge">
                                <?= htmlspecialchars($articulo['categoria_nombre']) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="product-info">
                        <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="product-title-link">
                            <h3><?= htmlspecialchars($articulo['nombre']) ?></h3>
                        </a>

                        <div class="product-meta">
                            <?php if (!empty($articulo['marca'])): ?>
                                <span class="meta-tag">
                                    <i class="fas fa-copyright"></i> 
                                    <?= htmlspecialchars($articulo['marca']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="product-price">
                            S/. <?= number_format($articulo['precio_venta'], 2) ?>
                        </p>
                        
                        <button class="btn-add-cart" data-id="<?= $articulo['id'] ?>" data-tipo="articulo">
                            <i class="fas fa-cart-plus"></i> Añadir
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Sin resultados -->
        <?php if (empty($servicios) && empty($articulos)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No se encontraron resultados</h3>
                <p>Intenta ajustar los filtros o realizar una nueva búsqueda</p>
                <a href="index.php" class="btn-back">
                    <i class="fas fa-arrow-left"></i> Ver todo el catálogo
                </a>
            </div>
        <?php endif; ?>
        
        <!-- PAGINACIÓN -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                $query_params = $_GET;
                unset($query_params['page']);
                $query_string = http_build_query($query_params);
                $query_string = $query_string ? '&' . $query_string : '';
                ?>
                
                <?php if ($current_page > 1): ?>
                    <a href="?page=1<?= $query_string ?>" class="page-link">
                        <i class="fas fa-angle-double-left"></i>
                    </a>
                    <a href="?page=<?= $current_page - 1 ?><?= $query_string ?>" class="page-link">
                        <i class="fas fa-angle-left"></i>
                    </a>
                <?php endif; ?>
                
                <?php
                $start_page = max(1, $current_page - 2);
                $end_page = min($total_pages, $current_page + 2);
                
                if ($start_page > 1): ?>
                    <a href="?page=1<?= $query_string ?>" class="page-link">1</a>
                    <?php if ($start_page > 2): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?><?= $query_string ?>" 
                       class="page-link <?= $i === $current_page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                        <span class="page-dots">...</span>
                    <?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="page-link"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <?php if ($current_page < $total_pages): ?>
                    <a href="?page=<?= $current_page + 1 ?><?= $query_string ?>" class="page-link">
                        <i class="fas fa-angle-right"></i>
                    </a>
                    <a href="?page=<?= $total_pages ?><?= $query_string ?>" class="page-link">
                        <i class="fas fa-angle-double-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Overlay para sidebar en móviles -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Botón flotante de carrito (estilo chatbot) -->
<a href="carrito.php" class="floating-cart-btn" id="floatingCartBtn" title="Ver carrito">
    <i class="fas fa-shopping-cart"></i>
    <span class="floating-cart-badge" id="floatingCartBadge">0</span>
</a>

<!-- Script para funcionalidad del carrusel y filtros -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar badge del carrito flotante
    function updateFloatingCartBadge() {
        const mainBadge = document.getElementById('cartBadge');
        const floatingBadge = document.getElementById('floatingCartBadge');
        if (mainBadge && floatingBadge) {
            floatingBadge.textContent = mainBadge.textContent;
            if (parseInt(mainBadge.textContent) > 0) {
                floatingBadge.style.display = 'flex';
            } else {
                floatingBadge.style.display = 'none';
            }
        }
    }
    
    // Actualizar al cargar
    updateFloatingCartBadge();
    
    // Observar cambios en el badge principal
    const mainBadge = document.getElementById('cartBadge');
    if (mainBadge) {
        const observer = new MutationObserver(updateFloatingCartBadge);
        observer.observe(mainBadge, { childList: true, characterData: true, subtree: true });
    }
    
    // Mostrar/ocultar filtros de artículos según selección
    const radioButtons = document.querySelectorAll('input[name="mostrar"]');
    const articulosFilters = document.getElementById('articulos-filters');
    
    radioButtons.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'servicios') {
                articulosFilters.style.display = 'none';
            } else {
                articulosFilters.style.display = 'block';
            }
        });
    });
    
    // ============================================
    // FUNCIONALIDAD DEL SIDEBAR
    // ============================================
    const toggleFiltersBtn = document.getElementById('toggleFilters');
    const closeSidebarBtn = document.getElementById('closeSidebar');
    const sidebarFilters = document.getElementById('sidebarFilters');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function openSidebar() {
        sidebarFilters.classList.add('active');
        sidebarOverlay.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    
    function closeSidebar() {
        sidebarFilters.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    if (toggleFiltersBtn) {
        toggleFiltersBtn.addEventListener('click', openSidebar);
    }
    
    if (closeSidebarBtn) {
        closeSidebarBtn.addEventListener('click', closeSidebar);
    }
    
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    
    // ============================================
    // FUNCIONALIDAD DEL CARRUSEL DE IMÁGENES
    // ============================================
    const carousels = document.querySelectorAll('.image-carousel');
    
    carousels.forEach(carousel => {
        const images = carousel.querySelectorAll('.carousel-image');
        const thumbnails = carousel.querySelectorAll('.thumbnail-wrapper');
        const prevBtn = carousel.querySelector('.carousel-control.prev');
        const nextBtn = carousel.querySelector('.carousel-control.next');
        const counter = carousel.querySelector('.current-image');
        
        if (images.length <= 1) return;
        
        let currentIndex = 0;
        
        function showImage(index) {
            if (index < 0) index = images.length - 1;
            if (index >= images.length) index = 0;
            
            currentIndex = index;
            
            images.forEach((img, i) => {
                img.classList.toggle('active', i === currentIndex);
            });
            
            thumbnails.forEach((thumb, i) => {
                thumb.classList.toggle('active', i === currentIndex);
            });
            
            if (counter) {
                counter.textContent = currentIndex + 1;
            }
            
            if (thumbnails[currentIndex]) {
                thumbnails[currentIndex].scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'center'
                });
            }
        }
        
        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showImage(currentIndex - 1);
            });
        }
        
        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showImage(currentIndex + 1);
            });
        }
        
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                showImage(index);
            });
        });
        
        // Soporte táctil
        let touchStartX = 0;
        let touchEndX = 0;
        
        carousel.addEventListener('touchstart', (e) => {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        carousel.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].screenX;
            const swipeThreshold = 50;
            const diff = touchStartX - touchEndX;
            
            if (Math.abs(diff) > swipeThreshold) {
                if (diff > 0) {
                    showImage(currentIndex + 1);
                } else {
                    showImage(currentIndex - 1);
                }
            }
        }, { passive: true });
    });
});
// Agregar este script al final de index.php, antes del cierre de </body>

document.addEventListener('DOMContentLoaded', function() {
    
    // Función para manejar errores de carga de imágenes
    function setupImageFallback() {
        const images = document.querySelectorAll('.carousel-image, .preview-image, .product-image img');
        
        images.forEach(img => {
            // Solo procesar una vez
            if (img.dataset.fallbackProcessed) return;
            img.dataset.fallbackProcessed = 'true';
            
            const originalSrc = img.src;
            let attemptCount = 0;
            const maxAttempts = 3;
            
            img.addEventListener('error', function() {
                attemptCount++;
                
                // Extraer file ID si es una URL de Drive
                const driveIdMatch = originalSrc.match(/[\/=]([a-zA-Z0-9_-]{25,})/);
                
                if (driveIdMatch && attemptCount <= maxAttempts) {
                    const fileId = driveIdMatch[1];
                    
                    // Intentar formatos alternativos
                    const alternativeFormats = [
                        `https://lh3.googleusercontent.com/d/${fileId}`,
                        `https://drive.google.com/thumbnail?id=${fileId}&sz=w1000`,
                        `https://drive.google.com/uc?export=download&id=${fileId}`,
                        'assets/img/no-image.png'
                    ];
                    
                    // Intentar siguiente formato
                    if (attemptCount < alternativeFormats.length) {
                        console.log(`Intento ${attemptCount} para imagen con ID: ${fileId}`);
                        this.src = alternativeFormats[attemptCount - 1];
                    }
                } else {
                    // Usar imagen placeholder
                    this.src = 'assets/img/no-image.png';
                }
            });
            
            // Si la imagen ya está cargada correctamente
            if (img.complete && img.naturalHeight !== 0) {
                img.style.opacity = '1';
            }
        });
    }
    
    // Configurar fallback al cargar
    setupImageFallback();
    
    // También configurar para imágenes que se cargan dinámicamente
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                setupImageFallback();
            }
        });
    });
    
    // Observar cambios en el contenedor de productos
    const productsContainer = document.querySelector('.products-grid');
    if (productsContainer) {
        observer.observe(productsContainer, {
            childList: true,
            subtree: true
        });
    }
    
    // Función para forzar recarga de imágenes de Drive
    window.reloadDriveImages = function() {
        const driveImages = document.querySelectorAll('img[src*="drive.google.com"], img[src*="googleusercontent.com"]');
        driveImages.forEach(img => {
            const src = img.src;
            img.src = '';
            setTimeout(() => {
                img.src = src + (src.includes('?') ? '&' : '?') + '_t=' + Date.now();
            }, 100);
        });
    };
    
    console.log('✅ Sistema de fallback de imágenes de Drive activado');
    console.log('💡 Si las imágenes no cargan, ejecuta: reloadDriveImages()');
});
</script>

<?php
require_once 'includes/footer.php';
?>