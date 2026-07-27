<?php
/**
 * ==============================================================================
 * Panel de Administración - Intranet IUTA
 * Gestión de Contenido del CDI "Jesús Rosas Marcano" con Carga de Imágenes
 * ==============================================================================
 */

session_start();

// Control de Sesión: Redirigir a login.php si el usuario no está autenticado
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$mensajeExito = '';
$mensajeError = '';

// 1. Obtener la lista de todas las secciones para el desplegable <select>
try {
    $stmtList = $pdo->query("SELECT id, clave, titulo FROM secciones_informativas ORDER BY id ASC");
    $listaSecciones = $stmtList->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $listaSecciones = [];
    $mensajeError = "Error al obtener la lista de secciones: " . $e->getMessage();
}

// 2. Determinar la sección actualmente seleccionada para editar
$idSeleccionado = isset($_REQUEST['id_seccion']) ? (int)$_REQUEST['id_seccion'] : 0;

if ($idSeleccionado === 0 && !empty($listaSecciones)) {
    $idSeleccionado = (int)$listaSecciones[0]['id'];
}

// 3. Procesar el guardado (UPDATE) si se envió el formulario
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['accion']) && $_POST['accion'] === 'guardar') {
    $idActualizar = (int)$_POST['id_seccion'];
    $nuevoTitulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
    $nuevoContenido = isset($_POST['contenido']) ? trim($_POST['contenido']) : '';

    if ($idActualizar > 0 && !empty($nuevoTitulo)) {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE secciones_informativas 
                SET titulo = :titulo, contenido = :contenido, fecha_actualizacion = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
            $resultado = $stmtUpdate->execute([
                ':titulo'    => $nuevoTitulo,
                ':contenido' => $nuevoContenido,
                ':id'        => $idActualizar
            ]);

            if ($resultado) {
                $mensajeExito = "¡La sección se ha actualizado correctamente con imágenes y formato guardados!";
                $idSeleccionado = $idActualizar;
            } else {
                $mensajeError = "No se pudo actualizar la sección.";
            }
        } catch (PDOException $e) {
            $mensajeError = "Error en la base de datos al actualizar: " . $e->getMessage();
        }
    } else {
        $mensajeError = "Por favor, proporciona un título válido.";
    }
}

// 4. Cargar los datos actuales de la sección seleccionada
$seccionActual = null;
if ($idSeleccionado > 0) {
    try {
        $stmtSec = $pdo->prepare("SELECT id, clave, titulo, contenido, fecha_actualizacion FROM secciones_informativas WHERE id = :id");
        $stmtSec->execute([':id' => $idSeleccionado]);
        $seccionActual = $stmtSec->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $mensajeError = "Error al recuperar la sección seleccionada: " . $e->getMessage();
    }
}

// 5. Escanear imágenes existentes en la carpeta uploads/
$imagenesSubidas = [];
$dirUploads = __DIR__ . '/uploads/';
if (file_exists($dirUploads)) {
    $archivos = glob($dirUploads . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    if ($archivos) {
        foreach ($archivos as $arc) {
            $imagenesSubidas[] = 'uploads/' . basename($arc);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | CDI IUTA</title>
    <meta name="description" content="Gestión de Contenido Informativo e Imágenes para el CDI Jesús Rosas Marcano IUTA">
    
    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Editor TinyMCE vía CDN -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.8.2/tinymce.min.js" referrerpolicy="no-referrer"></script>

    <!-- Iconos Lucide CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        :root {
            --azul-marino-head: #0b2545;
            --azul-marino-dark: #134074;
            --azul-intenso: #0066cc;
            --azul-claro: #eef4f8;
            --texto-principal: #1e293b;
            --texto-secundario: #475569;
            --bg-pagina: #f8fafc;
            --bg-card: #ffffff;
            --borde-color: #cbd5e1;
            --rojo-error: #dc2626;
            --verde-exito: #16a34a;
            --font-main: 'Inter', system-ui, sans-serif;
            --font-header: 'Merriweather', serif;
            --radius-main: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-pagina);
            color: var(--texto-principal);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Admin Bar */
        .top-bar-admin {
            background-color: #07192e;
            color: #94a3b8;
            padding: 0.6rem 2rem;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .top-bar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bar-actions {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .btn-top {
            color: #ffffff;
            background-color: rgba(255, 255, 255, 0.12);
            text-decoration: none;
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-weight: 500;
            font-size: 0.82rem;
            transition: background 0.2s ease;
        }

        .btn-top:hover { background-color: rgba(255, 255, 255, 0.22); }
        .btn-logout { background-color: rgba(220, 38, 38, 0.8); }
        .btn-logout:hover { background-color: #dc2626; }

        /* Encabezado */
        .admin-header {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #ffffff;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(11, 37, 69, 0.12);
        }

        .admin-header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .header-icon {
            width: 56px;
            height: 56px;
            background-color: #ffffff;
            color: var(--azul-marino-head);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .admin-titles h1 {
            font-family: var(--font-header);
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }

        .admin-titles p { font-size: 0.9rem; color: #cbd5e1; }

        /* Layout Grid de Administración */
        .admin-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 340px;
            gap: 2rem;
        }

        .admin-card {
            background-color: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        /* Sidebar de Imágenes */
        .sidebar-card {
            background-color: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            height: fit-content;
        }

        .sidebar-title {
            font-family: var(--font-header);
            font-size: 1.1rem;
            color: var(--azul-marino-head);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--azul-claro);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Formulario Subida Rápida de Imagen */
        .upload-box {
            background-color: var(--azul-claro);
            border: 2px dashed #b0c4de;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1.5rem;
            transition: border-color 0.2s;
        }

        .upload-box:hover { border-color: var(--azul-intenso); }

        .btn-upload-file {
            background-color: var(--azul-intenso);
            color: #ffffff;
            border: none;
            padding: 0.5rem 1rem;
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            margin-top: 0.5rem;
        }

        .btn-upload-file:hover { background-color: #0052a3; }

        /* Galería de imágenes subidas */
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
            max-height: 380px;
            overflow-y: auto;
            padding-right: 0.2rem;
        }

        .gallery-item {
            position: relative;
            border: 1px solid var(--borde-color);
            border-radius: 8px;
            overflow: hidden;
            background-color: #000;
            group: hover;
        }

        .gallery-item img {
            width: 100%;
            height: 90px;
            object-fit: cover;
            display: block;
            opacity: 0.9;
            transition: opacity 0.2s, transform 0.2s;
        }

        .gallery-item:hover img {
            opacity: 1;
            transform: scale(1.05);
        }

        .btn-insert-img {
            position: absolute;
            bottom: 4px;
            right: 4px;
            left: 4px;
            background: rgba(11, 37, 69, 0.85);
            color: #ffffff;
            border: none;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.3rem 0.4rem;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.2rem;
            backdrop-filter: blur(4px);
        }

        .btn-insert-img:hover {
            background: var(--azul-intenso);
        }

        /* Notificaciones */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.95rem;
            font-weight: 500;
        }

        .alert-success { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-danger { background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* Formulario de Edición */
        .form-group { margin-bottom: 1.5rem; }
        .form-group label {
            display: block; font-weight: 600; margin-bottom: 0.5rem;
            color: var(--azul-marino-head); font-size: 0.95rem;
        }

        .form-select, .form-input {
            width: 100%; padding: 0.75rem 1rem;
            border: 1px solid var(--borde-color); border-radius: 8px;
            font-family: var(--font-main); font-size: 0.95rem;
            color: var(--texto-principal); background-color: #ffffff;
        }

        .help-text { font-size: 0.82rem; color: var(--texto-secundario); margin-top: 0.35rem; }

        .btn-guardar {
            background-color: var(--azul-marino-head);
            color: #ffffff; border: none; padding: 0.85rem 1.75rem;
            font-size: 1rem; font-weight: 600; border-radius: 8px;
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.6rem;
            transition: background 0.2s ease;
        }

        .btn-guardar:hover { background-color: var(--azul-marino-dark); }

        .admin-footer {
            background-color: #07192e; color: #94a3b8;
            padding: 1.5rem; text-align: center; font-size: 0.85rem; margin-top: auto;
        }

        @media (max-width: 960px) {
            .admin-container { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- Bar Superior -->
    <div class="top-bar-admin">
        <div class="top-bar-container">
            <span><strong>Panel Administrativo</strong> — Usuario: <em><?= htmlspecialchars($_SESSION['usuario'] ?? 'admin') ?></em></span>
            <div class="bar-actions">
                <a href="index.php" class="btn-top">
                    <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                    <span>Ver Intranet (index.php)</span>
                </a>
                <a href="logout.php" class="btn-top btn-logout" title="Cerrar sesión">
                    <i data-lucide="log-out" style="width: 15px; height: 15px;"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Encabezado Admin -->
    <header class="admin-header">
        <div class="admin-header-container">
            <div class="header-icon"><i data-lucide="file-cog" style="width: 32px; height: 32px;"></i></div>
            <div class="admin-titles">
                <h1>Gestor de Contenidos e Imágenes CDI</h1>
                <p>Edición enriquecida con soporte para subir imágenes y formato HTML</p>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="admin-container">
        
        <!-- Formulario de Edición Principal -->
        <div class="admin-card">

            <!-- Mensajes de Notificación -->
            <?php if (!empty($mensajeExito)): ?>
                <div class="alert alert-success">
                    <i data-lucide="check-circle-2"></i>
                    <span><?= htmlspecialchars($mensajeExito) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($mensajeError)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-triangle"></i>
                    <span><?= htmlspecialchars($mensajeError) ?></span>
                </div>
            <?php endif; ?>

            <!-- Selector de Sección -->
            <form method="GET" action="admin.php" id="formSelector" class="form-group">
                <label for="id_seccion_select">
                    <i data-lucide="list" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                    Seleccione la Sección Informativa a Editar:
                </label>
                <select name="id_seccion" id="id_seccion_select" class="form-select" onchange="this.form.submit();">
                    <?php foreach ($listaSecciones as $opc): ?>
                        <option value="<?= $opc['id'] ?>" <?= ($opc['id'] == $idSeleccionado) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($opc['titulo']) ?> (Clave: <?= htmlspecialchars($opc['clave']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="help-text">Al cambiar la opción del desplegable se cargará el título y contenido guardado.</p>
            </form>

            <hr style="border: 0; border-top: 1px solid var(--borde-color); margin: 1.5rem 0;">

            <?php if ($seccionActual): ?>
                <form method="POST" action="admin.php">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_seccion" value="<?= (int)$seccionActual['id'] ?>">

                    <div class="form-group">
                        <label for="input_titulo">Título de la Sección:</label>
                        <input type="text" name="titulo" id="input_titulo" class="form-input" required 
                               value="<?= htmlspecialchars($seccionActual['titulo']) ?>" placeholder="Ej: Misión Institucional">
                    </div>

                    <div class="form-group">
                        <label for="contenido">Contenido Enriquecido (Imágenes y HTML):</label>
                        <textarea name="contenido" id="contenido" rows="14"><?= htmlspecialchars($seccionActual['contenido']) ?></textarea>
                        <p class="help-text">💡 <strong>Tip para imágenes:</strong> Puede hacer clic en el botón de imagen <i data-lucide="image" style="width: 14px; height: 14px;"></i> para subir desde su equipo, pegar imágenes directamente o seleccionar desde la galería lateral.</p>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                        <button type="submit" class="btn-guardar">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            <span>Guardar Cambios</span>
                        </button>
                        <span style="font-size: 0.82rem; color: var(--texto-secundario);">
                            Modificado: <?= date('d/m/Y h:i A', strtotime($seccionActual['fecha_actualizacion'])) ?>
                        </span>
                    </div>
                </form>
            <?php endif; ?>

        </div>

        <!-- Sidebar Galería de Imágenes -->
        <aside class="sidebar-card">
            <h3 class="sidebar-title">
                <i data-lucide="image" style="width: 20px; height: 20px; color: var(--azul-intenso);"></i>
                <span>Galería de Imágenes</span>
            </h3>

            <!-- Caja de Subida Rápida -->
            <div class="upload-box">
                <p style="font-size: 0.85rem; color: var(--texto-secundario); margin-bottom: 0.4rem;">Subir nueva imagen:</p>
                <input type="file" id="inputImagenRapida" accept="image/*" style="display: none;" onchange="subirImagenDirecta(this)">
                <button type="button" class="btn-upload-file" onclick="document.getElementById('inputImagenRapida').click()">
                    <i data-lucide="upload" style="width: 14px; height: 14px;"></i>
                    <span>Seleccionar Archivo</span>
                </button>
            </div>

            <!-- Lista de Imágenes Subidas -->
            <p style="font-size: 0.8rem; font-weight: 600; color: var(--azul-marino-head); margin-bottom: 0.5rem;">Imágenes Disponibles:</p>
            <div class="gallery-grid" id="galeriaGrid">
                <?php if (empty($imagenesSubidas)): ?>
                    <p style="font-size: 0.8rem; color: var(--texto-secundario); grid-column: span 2;">No hay imágenes aún.</p>
                <?php else: ?>
                    <?php foreach ($imagenesSubidas as $imgUrl): ?>
                        <div class="gallery-item" title="<?= htmlspecialchars(basename($imgUrl)) ?>">
                            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="Imagen subida">
                            <button type="button" class="btn-insert-img" onclick="insertarImagenEnEditor('<?= htmlspecialchars($imgUrl) ?>')">
                                <i data-lucide="plus" style="width: 12px; height: 12px;"></i> Insertar
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </aside>

    </main>

    <footer class="admin-footer">
        <p>&copy; <?= date('Y') ?> Intranet IUTA | Sistema de Gestión del CDI "Jesús Rosas Marcano"</p>
    </footer>

    <!-- Inicialización de TinyMCE con Carga de Imágenes -->
    <script>
        tinymce.init({
            selector: '#contenido',
            height: 440,
            language: 'es',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | removeformat code preview',
            content_style: 'body { font-family: Inter, Helvetica, Arial, sans-serif; font-size:15px; line-height: 1.6; color: #1e293b; } img { max-width: 100%; height: auto; border-radius: 8px; margin: 10px 0; }',
            branding: false,
            promotion: false,
            // Handler automático de carga de imágenes en TinyMCE
            images_upload_url: 'upload_imagen.php',
            automatic_uploads: true,
            images_reuse_filename: false,
            file_picker_types: 'image',
            file_picker_callback: function (cb, value, meta) {
                var input = document.createElement('input');
                input.setAttribute('type', 'file');
                input.setAttribute('accept', 'image/*');
                input.onchange = function () {
                    var file = this.files[0];
                    var formData = new FormData();
                    formData.append('file', file);
                    
                    fetch('upload_imagen.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json())
                    .then(data => {
                        if (data.location) {
                            cb(data.location, { title: file.name });
                            setTimeout(refrescarGaleria, 500);
                        } else {
                            alert(data.error || 'Error al subir la imagen.');
                        }
                    })
                    .catch(() => alert('Error en la conexión al subir la imagen.'));
                };
                input.click();
            },
            setup: function (editor) {
                editor.on('change', function () {
                    editor.save();
                });
            }
        });

        // Función para insertar imagen directamente desde la galería lateral en el editor
        function insertarImagenEnEditor(url) {
            if (tinymce.activeEditor) {
                tinymce.activeEditor.insertContent('<p><img src="' + url + '" alt="Imagen CDI IUTA" style="max-width:100%; height:auto; border-radius:8px;" /></p>');
            } else {
                alert('El editor aún no está listo.');
            }
        }

        // Subida rápida de imagen desde la barra lateral
        function subirImagenDirecta(inputElement) {
            if (!inputElement.files || !inputElement.files[0]) return;
            
            var file = inputElement.files[0];
            var formData = new FormData();
            formData.append('file', file);

            fetch('upload_imagen.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.location) {
                    insertarImagenEnEditor(data.location);
                    location.reload(); // Recargar para reflejar en la galería lateral
                } else {
                    alert(data.error || 'Error al subir la imagen.');
                }
            })
            .catch(() => alert('Error en la transmisión de la imagen.'));
        }

        lucide.createIcons();
    </script>
</body>
</html>
