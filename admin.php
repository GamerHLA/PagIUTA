<?php
/**
 * ==============================================================================
 * Panel de Administración - Intranet IUTA
 * Gestión de Contenido del CDI "Jesús Rosas Marcano"
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

// Si no se proporcionó un ID válido, tomar la primera sección por defecto
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
                $mensajeExito = "¡La sección se ha actualizado correctamente en la base de datos!";
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

// 4. Cargar los datos actuales de la sección seleccionada para llenar el formulario
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración | CDI IUTA</title>
    <meta name="description" content="Gestión de Contenido Informativo para el CDI Jesús Rosas Marcano IUTA">
    
    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    
    <!-- Editor TinyMCE vía CDN público -->
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
            max-width: 1100px;
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

        .btn-top:hover {
            background-color: rgba(255, 255, 255, 0.22);
        }

        .btn-logout {
            background-color: rgba(220, 38, 38, 0.8);
        }

        .btn-logout:hover {
            background-color: #dc2626;
        }

        /* Encabezado */
        .admin-header {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #ffffff;
            padding: 2rem;
            box-shadow: 0 4px 12px rgba(11, 37, 69, 0.12);
        }

        .admin-header-container {
            max-width: 1100px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-left {
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

        .admin-titles p {
            font-size: 0.9rem;
            color: #cbd5e1;
        }

        /* Panel Principal */
        .admin-container {
            max-width: 1100px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
            width: 100%;
        }

        .admin-card {
            background-color: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }

        /* Mensajes de Notificación */
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

        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #15803d;
        }

        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        /* Formulario */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--azul-marino-head);
            font-size: 0.95rem;
        }

        .form-select, .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--borde-color);
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: 0.95rem;
            color: var(--texto-principal);
            background-color: #ffffff;
            transition: border-color 0.2s;
        }

        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--azul-intenso);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15);
        }

        .help-text {
            font-size: 0.82rem;
            color: var(--texto-secundario);
            margin-top: 0.35rem;
        }

        /* Botón de envío */
        .btn-guardar {
            background-color: var(--azul-marino-head);
            color: #ffffff;
            border: none;
            padding: 0.85rem 1.75rem;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            transition: background 0.2s ease;
        }

        .btn-guardar:hover {
            background-color: var(--azul-marino-dark);
        }

        /* Footer Admin */
        .admin-footer {
            background-color: #07192e;
            color: #94a3b8;
            padding: 1.5rem;
            text-align: center;
            font-size: 0.85rem;
            margin-top: auto;
        }
    </style>
</head>
<body>

    <!-- Bar Superior -->
    <div class="top-bar-admin">
        <div class="top-bar-container">
            <span>
                <strong>Panel Administrativo</strong> — Usuario: <em><?= htmlspecialchars($_SESSION['usuario'] ?? 'admin') ?></em>
            </span>
            <div class="bar-actions">
                <a href="index.php" class="btn-top">
                    <i data-lucide="eye" style="width: 15px; height: 15px;"></i>
                    <span>Ver Intranet (index.php)</span>
                </a>
                <!-- Botón de Cerrar Sesión (logout.php) -->
                <a href="logout.php" class="btn-top btn-logout" title="Cerrar sesión de administrador">
                    <i data-lucide="log-out" style="width: 15px; height: 15px;"></i>
                    <span>Cerrar Sesión</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Encabezado Admin -->
    <header class="admin-header">
        <div class="admin-header-container">
            <div class="header-left">
                <div class="header-icon">
                    <i data-lucide="file-cog" style="width: 32px; height: 32px;"></i>
                </div>
                <div class="admin-titles">
                    <h1>Gestor de Contenidos del CDI</h1>
                    <p>Edición de Misión, Visión, Valores y Sedes del IUTA</p>
                </div>
            </div>
        </div>
    </header>

    <!-- Contenido Principal -->
    <main class="admin-container">
        
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

            <!-- Desplegable <select> de Selección de Sección -->
            <form method="GET" action="admin.php" id="formSelector" class="form-group">
                <label for="id_seccion_select">
                    <i data-lucide="list" style="width: 16px; height: 16px; vertical-align: middle; margin-right: 4px;"></i>
                    Seleccione la Sección Informativa a Editar:
                </label>
                <div class="select-wrapper">
                    <select name="id_seccion" id="id_seccion_select" class="form-select" onchange="this.form.submit();">
                        <?php foreach ($listaSecciones as $opc): ?>
                            <option value="<?= $opc['id'] ?>" <?= ($opc['id'] == $idSeleccionado) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opc['titulo']) ?> (Clave: <?= htmlspecialchars($opc['clave']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <p class="help-text">Al cambiar la opción del desplegable se cargará automáticamente la sección correspondiente.</p>
            </form>

            <hr style="border: 0; border-top: 1px solid var(--borde-color); margin: 1.5rem 0;">

            <?php if ($seccionActual): ?>
                <!-- Formulario Principal de Edición -->
                <form method="POST" action="admin.php">
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_seccion" value="<?= (int)$seccionActual['id'] ?>">

                    <div class="form-group">
                        <label for="input_titulo">Título de la Sección:</label>
                        <input type="text" name="titulo" id="input_titulo" class="form-input" required 
                               value="<?= htmlspecialchars($seccionActual['titulo']) ?>" placeholder="Ej: Misión Institucional">
                    </div>

                    <div class="form-group">
                        <label for="contenido">Contenido Enriquecido (HTML):</label>
                        <textarea name="contenido" id="contenido" rows="14"><?= htmlspecialchars($seccionActual['contenido']) ?></textarea>
                        <p class="help-text">Utilice la barra de herramientas para dar formato (Negrita, Listas, Alineación, Tablas e Imágenes).</p>
                    </div>

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                        <button type="submit" class="btn-guardar">
                            <i data-lucide="save" style="width: 18px; height: 18px;"></i>
                            <span>Guardar Cambios</span>
                        </button>
                        <span style="font-size: 0.82rem; color: var(--texto-secundario);">
                            Última modificación: <?= date('d/m/Y h:i A', strtotime($seccionActual['fecha_actualizacion'])) ?>
                        </span>
                    </div>
                </form>
            <?php else: ?>
                <p style="color: var(--texto-secundario);">Seleccione una sección válida para editar.</p>
            <?php endif; ?>

        </div>

    </main>

    <footer class="admin-footer">
        <p>&copy; <?= date('Y') ?> Intranet IUTA | Sistema de Gestión del CDI "Jesús Rosas Marcano"</p>
    </footer>

    <script>
        tinymce.init({
            selector: '#contenido',
            height: 420,
            language: 'es',
            plugins: 'advlist autolink lists link image charmap preview anchor searchreplace visualblocks code fullscreen insertdatetime media table code help wordcount',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | removeformat code preview',
            content_style: 'body { font-family: Inter, Helvetica, Arial, sans-serif; font-size:15px; line-height: 1.6; color: #1e293b; }',
            branding: false,
            promotion: false,
            setup: function (editor) {
                editor.on('change', function () {
                    editor.save();
                });
            }
        });

        lucide.createIcons();
    </script>
</body>
</html>
