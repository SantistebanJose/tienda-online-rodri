<?php
// includes/CloudinaryHelper.php

class CloudinaryHelper {
    private $cloudName;
    private $apiKey;
    private $apiSecret;
    private $uploadPreset; // Para upload sin firma
    
    public function __construct() {
        // Configuración - guardar en config.php o .env
        $this->cloudName = 'denlnhjyz';
        $this->apiKey = '219193872424727';
        $this->apiSecret = 'nHjC29GsnFnJmO4an9jvAzODaHI';
      //  $this->uploadPreset = 'tu-upload-preset'; // Opcional
    }
    
    /**
     * Subir imagen a Cloudinary
     * @param string $filePath Ruta del archivo temporal
     * @param string $folder Carpeta en Cloudinary (ej: 'productos', 'servicios')
     * @param string $publicId ID público personalizado (opcional)
     * @return array|false Datos de la imagen subida o false en error
     */
    public function uploadImage($filePath, $folder = 'productos', $publicId = null) {
        $timestamp = time();
        
        // Preparar parámetros
        $params = [
            'file' => new CURLFile($filePath),
            'timestamp' => $timestamp,
            'folder' => $folder,
            'transformation' => 'c_fill,h_800,w_800,q_auto:good',
        ];
        
        if ($publicId) {
            $params['public_id'] = $publicId;
        }
        
        // Generar firma
        $params['signature'] = $this->generateSignature($params);
        $params['api_key'] = $this->apiKey;
        
        // Hacer upload
        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/upload";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            return [
                'url' => $result['secure_url'],
                'public_id' => $result['public_id'],
                'thumbnail' => $this->getThumbnailUrl($result['public_id']),
                'width' => $result['width'],
                'height' => $result['height']
            ];
        }
        
        return false;
    }
    
    /**
     * Eliminar imagen de Cloudinary
     */
    public function deleteImage($publicId) {
        $timestamp = time();
        
        $params = [
            'public_id' => $publicId,
            'timestamp' => $timestamp
        ];
        
        $params['signature'] = $this->generateSignature($params);
        $params['api_key'] = $this->apiKey;
        
        $url = "https://api.cloudinary.com/v1_1/{$this->cloudName}/image/destroy";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $params,
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        return isset($result['result']) && $result['result'] === 'ok';
    }
    
    /**
     * Obtener URL de thumbnail
     */
    public function getThumbnailUrl($publicId) {
        return "https://res.cloudinary.com/{$this->cloudName}/image/upload/c_fill,h_250,w_250,q_auto:good/{$publicId}";
    }
    
    /**
     * Obtener URL optimizada
     */
    public function getOptimizedUrl($publicId, $width = 800, $height = 800) {
        return "https://res.cloudinary.com/{$this->cloudName}/image/upload/c_fill,h_{$height},w_{$width},q_auto:good/{$publicId}";
    }
    
    /**
     * Generar firma para autenticación
     */
    private function generateSignature($params) {
        unset($params['file'], $params['api_key']);
        ksort($params);
        
        $signatureString = '';
        foreach ($params as $key => $value) {
            $signatureString .= "{$key}={$value}&";
        }
        $signatureString = rtrim($signatureString, '&');
        
        return sha1($signatureString . $this->apiSecret);
    }
    
    /**
     * Validar archivo de imagen
     */
    public function validateImage($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['valid' => false, 'error' => 'Tipo de archivo no permitido. Solo JPG, PNG, GIF, WEBP'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['valid' => false, 'error' => 'El archivo es muy grande. Máximo 5MB'];
        }
        
        return ['valid' => true];
    }
}