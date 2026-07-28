<?php
/**
 * ==============================================================================
 * Handler de Carga de Medios (Imágenes y Videos) - Intranet IUTA
 * Procesa la subida de imágenes desde TinyMCE o el gestor administrativo.
 * También acepta archivos de video (MP4, WEBM, OGG, MOV).
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

// Configuración
$dirUploads = __DIR__ . '/uploads/';

// Tipos de imagen aceptados
$extensionesImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$mimesImagen = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

// Tipos de video aceptados
$extensionesVideo = ['mp4', 'webm', 'ogg', 'ogv', 'mov'];
$mimesVideo = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/mpeg'];

$maxTamanoImagen = 5 * 1024 * 1024;    // 5 MB para imágenes
$maxTamanoVideo = 100 * 1024 * 1024;   // 100 MB para videos

// Crear directorio si no existe
if (!file_exists($dirUploads)) {
    mkdir($dirUploads, 0755, true);
}

// Determinar el archivo recibido (campo 'file' de TinyMCE o 'imagen'/'video' del formulario manual)
$archivo = null;
if (isset($_FILES['file'])) {
    $archivo = $_FILES['file'];
} elseif (isset($_FILES['imagen'])) {
    $archivo = $_FILES['imagen'];
} elseif (isset($_FILES['video'])) {
    $archivo = $_FILES['video'];
}

if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK) {
    $codigoError = $archivo['error'] ?? -1;
    $mensajes = [
        UPLOAD_ERR_INI_SIZE   => 'El archivo supera el tamaño máximo del servidor (upload_max_filesize).',
        UPLOAD_ERR_FORM_SIZE  => 'El archivo supera el tamaño máximo del formulario.',
        UPLOAD_ERR_PARTIAL    => 'El archivo se subió de forma incompleta. Intente nuevamente.',
        UPLOAD_ERR_NO_FILE    => 'No se recibió ningún archivo.',
        UPLOAD_ERR_NO_TMP_DIR => 'Error del servidor: no hay directorio temporal.',
        UPLOAD_ERR_CANT_WRITE => 'Error del servidor: no se pudo escribir el archivo.',
    ];
    $msgError = $mensajes[$codigoError] ?? 'Error desconocido al recibir el archivo.';
    http_response_code(400);
    echo json_encode(['error' => $msgError]);
    exit;
}

// Determinar tipo y validar
$extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
$mimeReal = mime_content_type($archivo['tmp_name']);
$esImagen = in_array($extension, $extensionesImagen) && in_array($mimeReal, $mimesImagen);
$esVideo = in_array($extension, $extensionesVideo) || in_array($mimeReal, $mimesVideo);

if (!$esImagen && !$esVideo) {
    http_response_code(400);
    echo json_encode(['error' => 'Formato no permitido. Imágenes: JPG, PNG, GIF, WEBP. Videos: MP4, WEBM, OGG, MOV.']);
    exit;
}

// Validar tamaño según tipo de archivo
$maxTamano = $esVideo ? $maxTamanoVideo : $maxTamanoImagen;
if ($archivo['size'] > $maxTamano) {
    $limite = $esVideo ? '100 MB' : '5 MB';
    http_response_code(400);
    echo json_encode(['error' => "El archivo supera el límite máximo permitido ({$limite})."]);
    exit;
}

// Normalizar extensión de video para consistencia
if ($esVideo && $extension === 'ogv') {
    $extension = 'ogg';
}

// Generar nombre único con prefijo según tipo
$prefijo = $esVideo ? 'vid_' : 'img_';
$nombreUnico = $prefijo . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
$destinoFinal = $dirUploads . $nombreUnico;
$urlRelativa = 'uploads/' . $nombreUnico;

if (move_uploaded_file($archivo['tmp_name'], $destinoFinal)) {
    // Respuesta compatible con TinyMCE 6 (campo 'location')
    echo json_encode([
        'location' => $urlRelativa,
        'tipo'     => $esVideo ? 'video' : 'imagen',
        'mensaje'  => $esVideo ? 'Video subido exitosamente.' : 'Imagen subida exitosamente.'
    ]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Ocurrió un error al guardar el archivo en el servidor. Verifique los permisos de la carpeta uploads/.']);
}
