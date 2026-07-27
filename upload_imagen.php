<?php
/**
 * ==============================================================================
 * Handler de Carga de Imágenes - Intranet IUTA
 * Procesa la subida de imágenes desde TinyMCE o el gestor administrativo
 * ==============================================================================
 */

session_start();

header('Content-Type: application/json; charset=utf-8');

// Verificación de autenticación
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado: debe iniciar sesión como administrador.']);
    exit;
}

// Configuración de la carpeta de destino y formatos permitidos
$dirUploads = __DIR__ . '/uploads/';
$extensionesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$mimesPermitidos = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$maxTamano = 5 * 1024 * 1024; // 5 MB máximo

// Crear el directorio de uploads si no existe
if (!file_exists($dirUploads)) {
    mkdir($dirUploads, 0755, true);
}

// Verificar si se envió un archivo a través de TinyMCE (campo 'file') o formulario
$archivo = null;
if (isset($_FILES['file'])) {
    $archivo = $_FILES['file'];
} elseif (isset($_FILES['imagen'])) {
    $archivo = $_FILES['imagen'];
}

if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ningún archivo válido o hubo un error en la transmisión.']);
    exit;
}

// Validar tamaño del archivo
if ($archivo['size'] > $maxTamano) {
    http_response_code(400);
    echo json_encode(['error' => 'El archivo supera el tamaño máximo permitido (5 MB).']);
    exit;
}

// Validar extensión y tipo MIME
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$mimeReal = mime_content_type($archivo['tmp_name']);

if (!in_array($extension, $extensionesPermitidas) || !in_array($mimeReal, $mimesPermitidos)) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato no permitido. Solo se aceptan imágenes JPG, PNG, GIF y WEBP.']);
    exit;
}

// Generar nombre de archivo único para evitar sobrescribir
$nombreUnico = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$destinoFinal = $dirUploads . $nombreUnico;
$urlRelativa = 'uploads/' . $nombreUnico;

if (move_uploaded_file($archivo['tmp_name'], $destinoFinal)) {
    // Formato de respuesta JSON requerido por TinyMCE 6/7
    echo json_encode([
        'location' => $urlRelativa,
        'mensaje'  => 'Imagen subida exitosamente.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al guardar la imagen en el servidor.']);
}
