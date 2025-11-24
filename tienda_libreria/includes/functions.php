<?php
/**
 * Extrae el ID de un archivo de Google Drive desde diferentes formatos de URL
 * 
 * @param string $url URL de Google Drive
 * @return string|null ID del archivo o null si no se encuentra
 */
function extraerIdDrive($url) {
    // Eliminar espacios
    $url = trim($url);
    
    // Patrón 1: https://drive.google.com/file/d/FILE_ID/view
    if (preg_match('/\/file\/d\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // Patrón 2: https://drive.google.com/open?id=FILE_ID
    if (preg_match('/[?&]id=([a-zA-Z0-9_-]+)/', $url, $matches)) {
        return $matches[1];
    }
    
    // Patrón 3: Ya es un ID directo (sin / ni ?)
    if (strlen($url) > 20 && strpos($url, '/') === false && strpos($url, '?') === false) {
        return $url;
    }
    
    return null;
}

/**
 * Convierte una URL de Google Drive al formato de visualización directa
 * Prueba múltiples formatos para máxima compatibilidad
 * 
 * @param string $url URL de Google Drive en cualquier formato
 * @return string|null URL convertida o null si es inválida
 */
function convertirUrlDrive($url) {
    $fileId = extraerIdDrive($url);
    
    if (!$fileId) {
        return null;
    }
    
    // Formato principal para visualización directa (más confiable)
    return "https://lh3.googleusercontent.com/d/{$fileId}";
    
    // Formatos alternativos (descomentar si el principal falla):
    // return "https://drive.google.com/uc?export=view&id={$fileId}";
    // return "https://drive.google.com/thumbnail?id={$fileId}&sz=w1000";
}

/**
 * Procesa el JSON de imágenes y convierte las URLs de Drive al formato correcto
 * 
 * @param string|null $json_url_img JSON string con las imágenes
 * @return array Array de imágenes procesadas
 */
function procesarImagenesArticulo($json_url_img) {
    $imagenes = [];
    
    if (empty($json_url_img)) {
        return $imagenes;
    }
    
    try {
        $json_decoded = json_decode($json_url_img, true);
        
        if (is_array($json_decoded)) {
            // Ordenar por índice
            usort($json_decoded, function($a, $b) {
                return ($a['index'] ?? 0) - ($b['index'] ?? 0);
            });
            
            // Procesar cada imagen
            foreach ($json_decoded as $img) {
                $url = $img['url'] ?? '';
                $source = $img['source'] ?? 'web';
                
                // Si es de Drive o detectamos que es una URL de Drive
                if ($source === 'drive' || esDrive($url)) {
                    $urlConvertida = convertirUrlDrive($url);
                    if ($urlConvertida) {
                        $img['url'] = $urlConvertida;
                        $img['url_original'] = $url; // Guardamos el original por si acaso
                    }
                }
                
                $imagenes[] = $img;
            }
        }
    } catch (Exception $e) {
        error_log("Error al procesar imágenes: " . $e->getMessage());
    }
    
    return $imagenes;
}

/**
 * Obtiene la URL de la primera imagen de un artículo (imagen principal)
 * 
 * @param string|null $json_url_img JSON string con las imágenes
 * @return string URL de la imagen principal o placeholder
 */
function obtenerImagenPrincipal($json_url_img) {
    $imagenes = procesarImagenesArticulo($json_url_img);
    
    if (!empty($imagenes) && isset($imagenes[0]['url'])) {
        return $imagenes[0]['url'];
    }
    
    return 'assets/img/productos/placeholder.jpg';
}

/**
 * Verifica si una URL es de Google Drive
 * 
 * @param string $url URL a verificar
 * @return bool
 */
function esDrive($url) {
    return strpos($url, 'drive.google.com') !== false;
}
?>