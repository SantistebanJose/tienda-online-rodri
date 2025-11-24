<?php
// admin/upload_imagen.php
require_once '../includes/config.php';
require_once '../includes/CloudinaryHelper.php';

$db = new DB();
$cloudinary = new CloudinaryHelper();

// Procesar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tipo = $_POST['tipo'] ?? ''; // 'articulo' o 'servicio'
    $id = $_POST['item_id'] ?? 0;
    
    if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        // Validar imagen
        $validation = $cloudinary->validateImage($_FILES['imagen']);
        
        if (!$validation['valid']) {
            $error = $validation['error'];
        } else {
            // Subir a Cloudinary
            $folder = ($tipo === 'servicio') ? 'servicios' : 'productos';
            $publicId = $tipo . '_' . $id . '_' . time();
            
            $result = $cloudinary->uploadImage(
                $_FILES['imagen']['tmp_name'],
                $folder,
                $publicId
            );
            
            if ($result) {
                // Guardar en base de datos
                $tabla = ($tipo === 'servicio') ? 'movimiento' : 'articulo';
                
                $sql = "UPDATE {$tabla} SET 
                        imagen_url = :url,
                        imagen_cloudinary_id = :public_id,
                        imagen_thumbnail = :thumbnail
                        WHERE id = :id";
                
                $stmt = $db->pdo->prepare($sql);
                $stmt->execute([
                    ':url' => $result['url'],
                    ':public_id' => $result['public_id'],
                    ':thumbnail' => $result['thumbnail'],
                    ':id' => $id
                ]);
                
                $success = "Imagen subida correctamente";
            } else {
                $error = "Error al subir la imagen a Cloudinary";
            }
        }
    }
}

// Obtener artículos sin imagen
$articulos_sin_imagen = $db->pdo->query("
    SELECT id, nombre FROM articulo 
    WHERE (imagen_url IS NULL OR imagen_url = '') 
    AND deleted_at IS NULL 
    ORDER BY nombre
")->fetchAll();

// Obtener servicios sin imagen
$servicios_sin_imagen = $db->pdo->query("
    SELECT id, descripcion as nombre FROM movimiento 
    WHERE (imagen_url IS NULL OR imagen_url = '') 
    AND deleted_at IS NULL 
    ORDER BY descripcion
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Imágenes - Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        h1 {
            color: #667eea;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .upload-form {
            background: #f8f9fa;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        select, input[type="file"] {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
        }
        
        select:focus, input[type="file"]:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .file-input-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-input-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            padding: 2rem;
            background: white;
            border: 3px dashed #667eea;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input-label:hover {
            background: #f0f4ff;
            border-color: #764ba2;
        }
        
        .file-input-label i {
            font-size: 2rem;
            color: #667eea;
        }
        
        .file-name {
            margin-top: 0.5rem;
            font-size: 0.9rem;
            color: #666;
            font-style: italic;
        }
        
        .btn {
            width: 100%;
            padding: 1rem 2rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: scale(1.05);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .pendientes {
            margin-top: 2rem;
        }
        
        .pendientes h2 {
            color: #667eea;
            margin-bottom: 1rem;
        }
        
        .item-list {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .item-list p {
            padding: 0.5rem;
            margin: 0.3rem 0;
            background: white;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .preview {
            margin-top: 1rem;
            text-align: center;
        }
        
        .preview img {
            max-width: 100%;
            max-height: 300px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-cloud-upload-alt"></i> Subir Imágenes de Productos</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?= $success ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
            <div class="form-group">
                <label for="tipo">
                    <i class="fas fa-tag"></i> Tipo de Item
                </label>
                <select name="tipo" id="tipo" required onchange="updateItemList()">
                    <option value="">Seleccionar...</option>
                    <option value="articulo">Artículo</option>
                    <option value="servicio">Servicio</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="item_id">
                    <i class="fas fa-box"></i> Seleccionar Item
                </label>
                <select name="item_id" id="item_id" required disabled>
                    <option value="">Primero selecciona el tipo...</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>
                    <i class="fas fa-image"></i> Imagen (JPG, PNG, GIF, WEBP - Máx 5MB)
                </label>
                <div class="file-input-wrapper">
                    <input type="file" name="imagen" id="imagen" accept="image/*" required onchange="previewImage(this)">
                    <label for="imagen" class="file-input-label">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click para seleccionar imagen</span>
                    </label>
                </div>
                <div class="file-name" id="fileName"></div>
            </div>
            
            <div class="preview" id="preview"></div>
            
            <button type="submit" class="btn">
                <i class="fas fa-upload"></i> Subir Imagen
            </button>
        </form>
        
        <div class="pendientes">
            <h2><i class="fas fa-list"></i> Items sin Imagen</h2>
            
            <?php if (!empty($articulos_sin_imagen)): ?>
                <h3>Artículos (<?= count($articulos_sin_imagen) ?>)</h3>
                <div class="item-list">
                    <?php foreach ($articulos_sin_imagen as $art): ?>
                        <p><strong>#<?= $art['id'] ?></strong> - <?= htmlspecialchars($art['nombre']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($servicios_sin_imagen)): ?>
                <h3>Servicios (<?= count($servicios_sin_imagen) ?>)</h3>
                <div class="item-list">
                    <?php foreach ($servicios_sin_imagen as $serv): ?>
                        <p><strong>#<?= $serv['id'] ?></strong> - <?= htmlspecialchars($serv['nombre']) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const articulos = <?= json_encode($articulos_sin_imagen) ?>;
        const servicios = <?= json_encode($servicios_sin_imagen) ?>;
        
        function updateItemList() {
            const tipo = document.getElementById('tipo').value;
            const itemSelect = document.getElementById('item_id');
            
            itemSelect.innerHTML = '<option value="">Seleccionar...</option>';
            itemSelect.disabled = false;
            
            const items = tipo === 'articulo' ? articulos : servicios;
            
            items.forEach(item => {
                const option = document.createElement('option');
                option.value = item.id;
                option.textContent = `#${item.id} - ${item.nombre}`;
                itemSelect.appendChild(option);
            });
        }
        
        function previewImage(input) {
            const preview = document.getElementById('preview');
            const fileName = document.getElementById('fileName');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                
                reader.readAsDataURL(input.files[0]);
                fileName.textContent = `Archivo: ${input.files[0].name}`;
            }
        }
    </script>
</body>
</html>