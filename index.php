<?php
// index.php - Catálogo Mejorado con Filtros Laterales, Paginación, Imágenes Dinámicas y NOVEDADES DESTACADAS
// ✅ ACTUALIZADO: Usa disponibilidad_venta_fh para control de visibilidad
// ✅ Solo muestra productos con disponibilidad_venta_fh = NULL (disponibles)
// ✅ Oculta productos con timestamp en disponibilidad_venta_fh (bloqueados desde POS)

$page_title = "Librería Bazar Rodri";
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

// Obtener marcas únicas (solo de productos disponibles)
$marcas = [];
try {
    $stmt = $db->pdo->query("
        SELECT DISTINCT marca 
        FROM articulo 
        WHERE marca IS NOT NULL 
        AND TRIM(marca) != '' 
        AND disponibilidad_venta_fh IS NULL
        AND deleted_at IS NULL 
        ORDER BY marca
    ");
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
// OBTENER PRODUCTOS DESTACADOS/NOVEDADES
// ✅ ACTUALIZADO: Usa disponibilidad_venta_fh
// Solo muestra cuando disponibilidad_venta_fh es NULL (disponible)
// Oculta cuando tiene timestamp (bloqueado desde POS)
// ============================================
$productos_destacados = [];
$mostrar_destacados = ($mostrar === 'articulos' && empty($filtro_busqueda) && empty($filtro_categoria) && empty($filtro_marca) && empty($filtro_precio_min) && empty($filtro_precio_max));

if ($mostrar_destacados) {
    try {
        $sql_destacados = "
            SELECT 
                a.id, a.nombre, a.precio_venta, a.stock, a.marca, a.categoria_id,
                a.json_url_img, a.es_novedad, a.fecha_novedad,
                c.descripcion as categoria_nombre 
            FROM articulo a
            LEFT JOIN categoria c ON a.categoria_id = c.id
            WHERE a.es_novedad = TRUE 
            AND a.stock > 0 
            AND a.disponibilidad_venta_fh IS NULL
            AND a.deleted_at IS NULL
            ORDER BY a.orden_destacado DESC, a.fecha_novedad DESC
            LIMIT 6
        ";
        
        $stmt_destacados = $db->pdo->query($sql_destacados);
        $productos_destacados = $stmt_destacados->fetchAll();
    } catch (PDOException $e) {
        $productos_destacados = [];
    }
}

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
// ✅ ACTUALIZADO: Usa disponibilidad_venta_fh
// Solo muestra cuando disponibilidad_venta_fh es NULL (disponible)
// Oculta cuando tiene timestamp (bloqueado desde POS)
// ============================================
$articulos = [];
$total_articulos = 0;
if ($mostrar === 'articulos') {
    try {
        // Construir consulta base para contar
        $sql_count = "
            SELECT COUNT(*) 
            FROM articulo a
            WHERE a.stock > 0 
            AND a.disponibilidad_venta_fh IS NULL
            AND a.deleted_at IS NULL
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
            WHERE a.stock > 0 
            AND a.disponibilidad_venta_fh IS NULL
            AND a.deleted_at IS NULL
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

<style>
/* ============================================
   SECCIÓN DE PRODUCTOS DESTACADOS/NOVEDADES
   ============================================ */
.featured-section {
    margin: 2rem 0 3rem;
    padding: 2.5rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
    position: relative;
    overflow: hidden;
}

.featured-section::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
    animation: pulse-bg 4s ease-in-out infinite;
}

@keyframes pulse-bg {
    0%, 100% { transform: scale(1); opacity: 0.5; }
    50% { transform: scale(1.1); opacity: 0.3; }
}

.featured-header {
    text-align: center;
    margin-bottom: 2.5rem;
    position: relative;
    z-index: 1;
}

.featured-title-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1rem;
    margin-bottom: 0.8rem;
    flex-wrap: wrap;
}

.featured-badge {
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    padding: 0.5rem 1.2rem;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: bold;
    animation: bounce-badge 2s infinite;
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
}

@keyframes bounce-badge {
    0%, 100% { transform: translateY(0) scale(1); }
    50% { transform: translateY(-5px) scale(1.05); }
}

.featured-header h2 {
    color: white;
    margin: 0;
    font-size: 2.2rem;
    font-weight: 700;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.featured-subtitle {
    color: rgba(255, 255, 255, 0.95);
    font-size: 1.15rem;
    margin: 0;
    font-weight: 300;
}

.featured-products-carousel {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 1.5rem;
    position: relative;
    z-index: 1;
}

.featured-product-card {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    position: relative;
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.featured-product-card:hover {
    transform: translateY(-12px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.25);
}

.featured-label {
    position: absolute;
    top: 12px;
    left: 12px;
    background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
    color: white;
    padding: 0.4rem 1rem;
    border-radius: 25px;
    font-size: 0.75rem;
    font-weight: 700;
    z-index: 2;
    box-shadow: 0 3px 12px rgba(255, 107, 107, 0.5);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    animation: pulse-label 2s infinite;
}

@keyframes pulse-label {
    0%, 100% { box-shadow: 0 3px 12px rgba(255, 107, 107, 0.5); }
    50% { box-shadow: 0 3px 20px rgba(255, 107, 107, 0.8); }
}

.featured-image-link {
    display: block;
    position: relative;
    overflow: hidden;
}

.featured-product-image {
    width: 100%;
    height: 280px;
    overflow: hidden;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    position: relative;
}

.featured-product-image::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(180deg, transparent 0%, rgba(0,0,0,0.05) 100%);
    pointer-events: none;
}

.featured-product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.featured-product-card:hover .featured-product-image img {
    transform: scale(1.15) rotate(2deg);
}

.featured-product-info {
    padding: 1.5rem;
}

.featured-title-link {
    text-decoration: none;
    color: inherit;
    transition: color 0.3s ease;
}

.featured-product-info h3 {
    color: #2c3e50;
    font-size: 1.1rem;
    margin: 0 0 0.8rem;
    font-weight: 600;
    min-height: 2.6rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.3;
}

.featured-title-link:hover h3 {
    color: #667eea;
}

.featured-category {
    display: inline-block;
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    color: #495057;
    padding: 0.35rem 0.9rem;
    border-radius: 15px;
    font-size: 0.8rem;
    margin-bottom: 1rem;
    font-weight: 500;
}

.featured-price-section {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 1.2rem 0;
    padding: 0.8rem 0;
    border-top: 1px solid #e9ecef;
    border-bottom: 1px solid #e9ecef;
}

.featured-price {
    font-size: 1.6rem;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.featured-stock {
    font-size: 0.85rem;
    color: #6c757d;
    display: flex;
    align-items: center;
    gap: 0.4rem;
    background: #f8f9fa;
    padding: 0.3rem 0.8rem;
    border-radius: 12px;
}

.btn-add-cart-featured {
    width: 100%;
    padding: 0.9rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.btn-add-cart-featured:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.5);
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

.btn-add-cart-featured:active {
    transform: translateY(-1px);
}

/* Responsive */
@media (max-width: 992px) {
    .featured-products-carousel {
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    }
}

@media (max-width: 768px) {
    .featured-section {
        padding: 1.8rem;
        border-radius: 15px;
        margin: 1.5rem 0 2rem;
    }
    
    .featured-header h2 {
        font-size: 1.7rem;
    }
    
    .featured-subtitle {
        font-size: 1rem;
    }
    
    .featured-products-carousel {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 1.2rem;
    }
    
    .featured-product-image {
        height: 220px;
    }
    
    .featured-product-info {
        padding: 1.2rem;
    }
}

@media (max-width: 480px) {
    .featured-section {
        padding: 1.2rem;
    }
    
    .featured-header {
        margin-bottom: 1.5rem;
    }
    
    .featured-header h2 {
        font-size: 1.4rem;
    }
    
    .featured-products-carousel {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .featured-product-image {
        height: 250px;
    }
}

/* ============================================
   LOADING SCREEN - LIBRERÍA BAZAR RODRI
   Se muestra solo la primera vez que el usuario visita la web.
   Usa localStorage para recordar si ya fue mostrado.
   ============================================ */
#loading-screen {
    /* Cubre toda la pantalla */
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    /* Fondo con gradiente azul de la marca */
    background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #1e40af 100%);
    z-index: 999999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    /* Animación de salida al ocultarse */
    transition: opacity 0.6s ease, visibility 0.6s ease;
    opacity: 1;
    visibility: visible;
}

/* Estado oculto: se aplica antes de remover el elemento del DOM */
#loading-screen.hidden {
    opacity: 0;
    visibility: hidden;
}

/* Contenedor principal del loading */
.loading-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2rem;
    text-align: center;
    padding: 2rem;
}

/* ── Círculo con el logo ── */
.loading-logo-wrapper {
    position: relative;
    width: 130px;
    height: 130px;
}

/* Anillo giratorio exterior */
.loading-logo-wrapper::before {
    content: '';
    position: absolute;
    inset: -6px; /* un poco más grande que el círculo */
    border-radius: 50%;
    border: 3px solid transparent;
    border-top-color: rgba(255, 255, 255, 0.9);
    border-right-color: rgba(255, 255, 255, 0.4);
    animation: spin 1.2s linear infinite;
}

/* Segundo anillo giratorio (sentido contrario, más lento) */
.loading-logo-wrapper::after {
    content: '';
    position: absolute;
    inset: -14px;
    border-radius: 50%;
    border: 2px solid transparent;
    border-bottom-color: rgba(255, 255, 255, 0.5);
    border-left-color: rgba(255, 255, 255, 0.2);
    animation: spin-reverse 2s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes spin-reverse {
    to { transform: rotate(-360deg); }
}

/* Círculo de fondo del logo */
.loading-logo-circle {
    width: 130px;
    height: 130px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.25);
    display: flex;
    align-items: center;
    justify-content: center;
    /* Pulso suave */
    animation: logo-pulse 2s ease-in-out infinite;
    backdrop-filter: blur(10px);
    overflow: hidden;
}

@keyframes logo-pulse {
    0%, 100% { transform: scale(1);     box-shadow: 0 0 0 0 rgba(255,255,255,0.15); }
    50%       { transform: scale(1.04); box-shadow: 0 0 0 12px rgba(255,255,255,0); }
}

/* Logo de la librería */
.loading-logo-circle img {
    width: 85px;
    height: 85px;
    object-fit: contain;
    /* Flotación suave */
    animation: logo-float 3s ease-in-out infinite;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.3));
}

@keyframes logo-float {
    0%, 100% { transform: translateY(0);   }
    50%       { transform: translateY(-6px); }
}

/* ── Texto del loading ── */
.loading-brand {
    color: #ffffff;
    font-size: 1.6rem;
    font-weight: 700;
    letter-spacing: 1px;
    margin: 0;
    /* Aparición con fade-in */
    animation: fade-in-up 0.8s ease 0.3s both;
    text-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.loading-slogan {
    color: rgba(255, 255, 255, 0.80);
    font-size: 0.95rem;
    font-weight: 400;
    margin: 0;
    font-style: italic;
    animation: fade-in-up 0.8s ease 0.5s both;
}

@keyframes fade-in-up {
    from { opacity: 0; transform: translateY(14px); }
    to   { opacity: 1; transform: translateY(0);    }
}

/* ── Barra de progreso ── */
.loading-bar-wrapper {
    width: 200px;
    height: 4px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    overflow: hidden;
    animation: fade-in-up 0.8s ease 0.7s both;
}

.loading-bar-fill {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, rgba(255,255,255,0.6), #ffffff);
    border-radius: 10px;
    /* La barra se llena en ~1.8s, coordinado con el JS que oculta el loader */
    animation: fill-bar 1.8s ease-in-out 0.5s forwards;
}

@keyframes fill-bar {
    0%   { width: 0%; }
    60%  { width: 75%; }
    100% { width: 100%; }
}

/* ── Responsive ── */
@media (max-width: 480px) {
    .loading-logo-wrapper,
    .loading-logo-circle {
        width: 110px;
        height: 110px;
    }
    .loading-logo-circle img {
        width: 70px;
        height: 70px;
    }
    .loading-brand {
        font-size: 1.3rem;
    }
    .loading-bar-wrapper {
        width: 160px;
    }
}
</style>

<!-- ============================================
     LOADING SCREEN - PRIMERA VISITA
     Se controla completamente desde JavaScript.
     Si el usuario ya visitó la web (localStorage),
     este elemento se oculta inmediatamente sin
     mostrar ninguna animación.
     ============================================ -->
<div id="loading-screen" aria-hidden="true">
    <div class="loading-content">

        <!-- Logo con anillos giratorios -->
        <div class="loading-logo-wrapper">
            <div class="loading-logo-circle">
                <img src="assets/img/logo.png" alt="Librería Bazar Rodri">
            </div>
        </div>

        <!-- Nombre y slogan -->
        <div>
            <p class="loading-brand">Librería Bazar Rodri</p>
            <p class="loading-slogan">Más que útiles, acompañamos tu aprendizaje</p>
        </div>

        <!-- Barra de progreso decorativa -->
        <div class="loading-bar-wrapper">
            <div class="loading-bar-fill"></div>
        </div>

    </div>
</div>

<!-- ============================================
     MODAL DE ANIVERSARIO - COMENTADO
     Se reemplazó por el loading screen de arriba.
     Se mantiene aquí por si se desea restaurar
     en el futuro.
     ============================================

<style>
.anniversary-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(30, 58, 95, 0.85);
    z-index: 99999;
    backdrop-filter: blur(8px);
    animation: fadeInOverlay 0.4s ease;
}

.anniversary-modal-overlay.active {
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 1rem;
}

@keyframes fadeInOverlay {
    from { opacity: 0; }
    to { opacity: 1; }
}

.anniversary-modal {
    background: white;
    border-radius: 25px;
    max-width: 550px;
    width: 100%;
    position: relative;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.5);
    animation: modalPopIn 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
    overflow: hidden;
}

... (resto del CSS del modal de aniversario omitido para brevedad)
</style>

<div class="anniversary-modal-overlay" id="anniversaryModalOverlay">
    <div class="confetti"></div>
    ... (10 elementos confetti)

    <div class="anniversary-modal">
        <div class="anniversary-modal-content">
            <div class="anniversary-logo">
                <img src="assets/img/logo.png" alt="Librería Bazar Rodri">
            </div>
            <span class="anniversary-badge">🎉 Celebrando 3 Años</span>
            <h2 class="anniversary-title">¡3 años acompañando tu aprendizaje!</h2>
            <p class="anniversary-subtitle">Más que útiles, acompañamos tu aprendizaje</p>
            <p class="anniversary-description">
                Gracias por ser parte de nuestra historia. Durante estos 3 años 
                hemos crecido juntos, ofreciéndote los mejores productos para tu 
                educación. ¡Explorá nuestro catálogo renovado!
            </p>
            <button class="anniversary-cta" onclick="cerrarModalAniversario()">
                Continuar
            </button>
        </div>
    </div>
</div>

<script>
function mostrarModalAniversario() { ... }
function cerrarModalAniversario() { ... }
</script>

============================================ -->

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
        
        <!-- ============================================
             SECCIÓN DE NOVEDADES DESTACADAS
             ============================================ -->
        <?php if (!empty($productos_destacados) && $mostrar_destacados): ?>
        <div class="featured-section">
            <div class="featured-header">
                <div class="featured-title-wrapper">
                    <span class="featured-badge">🔥 NUEVO</span>
                    <h2>Productos Destacados</h2>
                </div>
                <p class="featured-subtitle">¡Descubre nuestras últimas novedades y productos más populares!</p>
            </div>
            
            <div class="featured-products-carousel">
                <?php foreach ($productos_destacados as $articulo): 
                    $imagenes = procesarImagenesArticulo($articulo['json_url_img']);
                    $primera_imagen = !empty($imagenes) ? $imagenes[0]['url'] : 'assets/img/productos/placeholder.jpg';
                ?>
                    <div class="featured-product-card">
                        <span class="featured-label">NOVEDAD</span>
                        
                        <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="featured-image-link">
                            <div class="featured-product-image">
                                <img src="<?= htmlspecialchars($primera_imagen) ?>" 
                                     alt="<?= htmlspecialchars($articulo['nombre']) ?>"
                                     loading="lazy"
                                     onerror="this.src='assets/img/no-image.png'">
                            </div>
                        </a>
                        
                        <div class="featured-product-info">
                            <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="featured-title-link">
                                <h3><?= htmlspecialchars($articulo['nombre']) ?></h3>
                            </a>
                            
                            <?php if (!empty($articulo['categoria_nombre'])): ?>
                                <span class="featured-category">
                                    <?= htmlspecialchars($articulo['categoria_nombre']) ?>
                                </span>
                            <?php endif; ?>
                            
                            <div class="featured-price-section">
                                <span class="featured-price">S/. <?= number_format($articulo['precio_venta'], 2) ?></span>
                                <span class="featured-stock">
                                    <i class="fas fa-box"></i> Stock: <?= $articulo['stock'] ?>
                                </span>
                            </div>
                            
                            <button class="btn-add-cart-featured" data-id="<?= $articulo['id'] ?>" data-tipo="articulo">
                                <i class="fas fa-cart-plus"></i> Añadir al carrito
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
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

<script>
document.addEventListener('DOMContentLoaded', function() {

    // ============================================
    // LOADING SCREEN - PRIMERA VISITA
    // Clave usada en localStorage para recordar
    // si el usuario ya vio el loading screen.
    // Cambiar la clave (ej: 'loadingSeen_v2') fuerza
    // que todos los usuarios vean el loader de nuevo
    // (útil ante rediseños importantes).
    // ============================================
    const loadingScreen = document.getElementById('loading-screen');

    if (loadingScreen) {
        const INACTIVITY_LIMIT = 30 * 60 * 1000; // 30 minutos
        const sessionActive = sessionStorage.getItem('rodri_session_active');

        if (!sessionActive) {
            // Nueva pestaña o nueva sesión → revisar si hubo inactividad
            const lastExit = localStorage.getItem('rodri_last_exit');
            const now = Date.now();
            const mostrarLoading = !lastExit || (now - parseInt(lastExit)) > INACTIVITY_LIMIT;

            // Marcar sesión como activa (paginación ya no mostrará loading)
            sessionStorage.setItem('rodri_session_active', 'true');

            if (mostrarLoading) {
                setTimeout(function () {
                    loadingScreen.classList.add('hidden');
                    setTimeout(function () { loadingScreen.remove(); }, 650);
                }, 4000);
            } else {
                loadingScreen.style.display = 'none';
            }

        } else {
            // Sesión activa: paginación u otra página → no mostrar
            loadingScreen.style.display = 'none';
        }

        // Guardar timestamp exacto cuando el usuario sale o cambia de pestaña
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) {
                localStorage.setItem('rodri_last_exit', Date.now().toString());
            }
        });

        window.addEventListener('pagehide', function () {
            localStorage.setItem('rodri_last_exit', Date.now().toString());
        });
    }

    // ============================================
    // BADGE DEL CARRITO FLOTANTE
    // ============================================
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
    
    updateFloatingCartBadge();
    
    const mainBadge = document.getElementById('cartBadge');
    if (mainBadge) {
        const observer = new MutationObserver(updateFloatingCartBadge);
        observer.observe(mainBadge, { childList: true, characterData: true, subtree: true });
    }
    
    // ============================================
    // MOSTRAR/OCULTAR FILTROS DE ARTÍCULOS
    // ============================================
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
    
    if (toggleFiltersBtn) toggleFiltersBtn.addEventListener('click', openSidebar);
    if (closeSidebarBtn)  closeSidebarBtn.addEventListener('click', closeSidebar);
    if (sidebarOverlay)   sidebarOverlay.addEventListener('click', closeSidebar);
    
    // ============================================
    // CARRUSEL DE IMÁGENES
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
            
            images.forEach((img, i) => img.classList.toggle('active', i === currentIndex));
            thumbnails.forEach((thumb, i) => thumb.classList.toggle('active', i === currentIndex));
            
            if (counter) counter.textContent = currentIndex + 1;
            
            if (thumbnails[currentIndex]) {
                thumbnails[currentIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            }
        }
        
        if (prevBtn) prevBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); showImage(currentIndex - 1); });
        if (nextBtn) nextBtn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); showImage(currentIndex + 1); });
        
        thumbnails.forEach((thumbnail, index) => {
            thumbnail.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); showImage(index); });
        });
        
        // Soporte táctil
        let touchStartX = 0;
        carousel.addEventListener('touchstart', (e) => { touchStartX = e.changedTouches[0].screenX; }, { passive: true });
        carousel.addEventListener('touchend', (e) => {
            const diff = touchStartX - e.changedTouches[0].screenX;
            if (Math.abs(diff) > 50) showImage(diff > 0 ? currentIndex + 1 : currentIndex - 1);
        }, { passive: true });
    });
    
    // ============================================
    // BOTONES "AÑADIR AL CARRITO" - PRODUCTOS DESTACADOS
    // ============================================
    document.querySelectorAll('.btn-add-cart-featured').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const id = this.dataset.id;
            const tipo = this.dataset.tipo;
            
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Agregando...';
            this.disabled = true;
            
            setTimeout(() => {
                this.innerHTML = '<i class="fas fa-check"></i> ¡Agregado!';
                this.style.background = 'linear-gradient(135deg, #51cf66 0%, #37b24d 100%)';
                
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.style.background = '';
                    this.disabled = false;
                }, 2000);
                
                updateFloatingCartBadge();
            }, 500);
            
            console.log(`Añadiendo ${tipo} con ID ${id} al carrito desde destacados`);
        });
    });
    
    // ============================================
    // FALLBACK DE IMÁGENES (GOOGLE DRIVE)
    // ============================================
    function setupImageFallback() {
        const images = document.querySelectorAll('.carousel-image, .preview-image, .product-image img, .featured-product-image img');
        
        images.forEach(img => {
            if (img.dataset.fallbackProcessed) return;
            img.dataset.fallbackProcessed = 'true';
            
            const originalSrc = img.src;
            let attemptCount = 0;
            
            img.addEventListener('error', function() {
                attemptCount++;
                const driveIdMatch = originalSrc.match(/[\/=]([a-zA-Z0-9_-]{25,})/);
                
                if (driveIdMatch && attemptCount <= 3) {
                    const fileId = driveIdMatch[1];
                    const alternativeFormats = [
                        `https://lh3.googleusercontent.com/d/${fileId}`,
                        `https://drive.google.com/thumbnail?id=${fileId}&sz=w1000`,
                        `https://drive.google.com/uc?export=download&id=${fileId}`,
                        'assets/img/no-image.png'
                    ];
                    this.src = alternativeFormats[attemptCount - 1] || 'assets/img/no-image.png';
                } else {
                    this.src = 'assets/img/no-image.png';
                }
            });
        });
    }
    
    setupImageFallback();
    
    const productsContainer = document.querySelector('.products-grid');
    if (productsContainer) {
        new MutationObserver(() => setupImageFallback())
            .observe(productsContainer, { childList: true, subtree: true });
    }

    console.log('✅ Catálogo listo — Loading screen activado (solo primera visita)');
});
</script>

<?php
require_once 'includes/footer.php';
?>