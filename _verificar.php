<?php
require_once 'conexion.php';

echo "=== Tablas en la BD ===\n";
$tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tablas as $t) echo "  - $t\n";

echo "\n=== Columnas de secciones_informativas ===\n";
$cols = $pdo->query("PRAGMA table_info(secciones_informativas)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols as $c) echo "  " . $c['cid'] . " | " . $c['name'] . " | " . $c['type'] . "\n";

echo "\n=== Columnas de preguntas_seguridad ===\n";
$cols2 = $pdo->query("PRAGMA table_info(preguntas_seguridad)")->fetchAll(PDO::FETCH_ASSOC);
foreach ($cols2 as $c) echo "  " . $c['cid'] . " | " . $c['name'] . " | " . $c['type'] . "\n";

echo "\n=== Secciones (activo + clave) ===\n";
$secs = $pdo->query("SELECT id, clave, titulo, activo FROM secciones_informativas ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($secs as $s) {
    echo "  {$s['id']} | [{$s['activo']}] | {$s['clave']} | {$s['titulo']}\n";
}

echo "\n=== Preguntas de seguridad migradas ===\n";
$pregs = $pdo->query("SELECT ps.id, u.usuario, ps.pregunta FROM preguntas_seguridad ps JOIN usuarios u ON u.id = ps.usuario_id")->fetchAll(PDO::FETCH_ASSOC);
foreach ($pregs as $p) {
    echo "  ID:{$p['id']} | {$p['usuario']} | {$p['pregunta']}\n";
}

echo "\n=== Assets locales ===\n";
$checks = [
    'assets/css/fonts.css'           => 'CSS Fuentes',
    'assets/js/lucide.min.js'        => 'Lucide JS',
    'assets/tinymce/tinymce.min.js'  => 'TinyMCE',
    'assets/fonts/Inter-Regular.woff2'   => 'Inter Regular',
    'assets/fonts/Merriweather-Bold.woff2' => 'Merriweather Bold',
];
foreach ($checks as $path => $nombre) {
    $ok = file_exists($path);
    $size = $ok ? round(filesize($path)/1024) . 'KB' : '--';
    echo "  " . ($ok ? '✓' : '✗') . " $nombre: $path ($size)\n";
}

echo "\n✅ Verificación completada.\n";
