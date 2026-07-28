<?php
/**
 * ==============================================================================
 * Eliminar Imagen - Intranet IUTA
 * Permite eliminar físicamente una imagen de la carpeta uploads/
 * ==============================================================================
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

// Control de Sesión
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado.']);
    exit;
}

$imagen = $_POST['imagen'] ?? $_GET['imagen'] ?? '';

if (empty($imagen)) {
    http_response_code(400);
    echo json_encode(['error' => 'No se especificó la imagen a eliminar.']);
    exit;
}

// Extraer sólo el nombre básico del archivo por seguridad (evita ataques de directory traversal)
$nombreArchivo = basename($imagen);
$rutaCompleta = __DIR__ . '/uploads/' . $nombreArchivo;

if (file_exists($rutaCompleta) && is_file($rutaCompleta)) {
    if (unlink($rutaCompleta)) {
        echo json_encode([
            'exito' => true,
            'mensaje' => 'Imagen eliminada correctamente.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'No se pudo borrar el archivo en el servidor.']);
    }
} else {
    http_response_code(404);
    echo json_encode(['error' => 'La imagen no existe o ya fue eliminada.']);
}
