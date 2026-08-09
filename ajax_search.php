<?php
// ajax_search.php - Búsqueda de productos en tiempo real (sin recargar página)
// Reutiliza la misma lógica de filtros que index.php, pero solo devuelve
// el fragmento de HTML de resultados (sin cabecera/pie completos).

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: text/html; charset=utf-8');

require_once 'includes/db.php';
require_once 'includes/functions.php';

$db = new DB();

$items_per_page = 40;
$current_page   = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset         = ($current_page - 1) * $items_per_page;

$filtro_categoria  = $_GET['categoria']  ?? '';
$filtro_marca      = $_GET['marca']      ?? '';
$filtro_precio_min = $_GET['precio_min'] ?? '';
$filtro_precio_max = $_GET['precio_max'] ?? '';
$filtro_busqueda   = trim($_GET['buscar'] ?? '');

$params = [];
$where_clauses = [];

if (!empty($filtro_categoria))  { $where_clauses[] = "a.categoria_id = :categoria";  $params[':categoria']  = $filtro_categoria; }
if (!empty($filtro_marca))      { $where_clauses[] = "a.marca = :marca";             $params[':marca']      = $filtro_marca; }
if (!empty($filtro_precio_min)) { $where_clauses[] = "a.precio_venta >= :precio_min"; $params[':precio_min'] = $filtro_precio_min; }
if (!empty($filtro_precio_max)) { $where_clauses[] = "a.precio_venta <= :precio_max"; $params[':precio_max'] = $filtro_precio_max; }
if (!empty($filtro_busqueda))   { $where_clauses[] = "a.nombre ILIKE :busqueda";      $params[':busqueda']   = '%' . $filtro_busqueda . '%'; }

// --- Conteo total ---
$sql_count = "
    SELECT COUNT(*)
    FROM articulo a
    WHERE a.stock > 0
    AND a.disponibilidad_venta_fh IS NULL
    AND a.deleted_at IS NULL
";
if (!empty($where_clauses)) {
    $sql_count .= " AND " . implode(" AND ", $where_clauses);
}
$stmt_count = $db->pdo->prepare($sql_count);
$stmt_count->execute($params);
$total_articulos = (int) $stmt_count->fetchColumn();

// --- Resultados ---
$sql = "
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
    $sql .= " AND " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY a.nombre ASC LIMIT :limit OFFSET :offset";

$stmt = $db->pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$articulos = $stmt->fetchAll();
?>
<div class="results-header">
    <h1>Nuestro Catálogo de Productos</h1>
    <p class="results-count">
        <i class="fas fa-box"></i>
        Mostrando <strong><?= count($articulos) ?></strong> de
        <strong><?= $total_articulos ?></strong> resultado<?= $total_articulos !== 1 ? 's' : '' ?>
    </p>
</div>

<?php if (!empty($articulos)): ?>
    <div class="products-grid articles-grid">
        <?php foreach ($articulos as $articulo):
            $imagenes = procesarImagenesArticulo($articulo['json_url_img']);
            $primera_imagen = !empty($imagenes) ? $imagenes[0]['url'] : 'assets/img/productos/placeholder.jpg';
        ?>
            <div class="product-card article-card no-entrance-animation">
                <div class="product-image-container">
                    <a href="producto_detalle.php?id=<?= $articulo['id'] ?>" class="product-image">
                        <img src="<?= htmlspecialchars($primera_imagen) ?>"
                             alt="<?= htmlspecialchars($articulo['nombre']) ?>"
                             loading="lazy"
                             onerror="this.src='assets/img/no-image.png'">
                    </a>

                    <span class="stock-badge">
                        <i class="fas fa-box"></i> Stock: <?= $articulo['stock'] ?>
                    </span>

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
<?php else: ?>
    <div class="no-results">
        <i class="fas fa-search"></i>
        <h3>No se encontraron resultados</h3>
        <p>Intenta ajustar los filtros o realizar una nueva búsqueda</p>
        <a href="index.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Ver todo el catálogo
        </a>
    </div>
<?php endif; ?>