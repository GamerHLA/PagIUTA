<?php
/**
 * ==============================================================================
 * Panel de Administración - Intranet IUTA
 * Gestión de Secciones, Imágenes, Videos y Seguridad
 * ==============================================================================
 */

session_start();

if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

$mensajeExito = '';
$mensajeError = '';
$usuarioActual = $_SESSION['usuario'] ?? 'admin';

// Obtener ID de usuario actual
$usuarioId = 0;
try {
    $stmtUid = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :u");
    $stmtUid->execute([':u' => $usuarioActual]);
    $rowUid = $stmtUid->fetch();
    if ($rowUid) $usuarioId = (int)$rowUid['id'];
} catch (Exception $e) {}

// ==============================================================================
// PROCESAMIENTO DE ACCIONES (POST)
// ==============================================================================

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];

    // 1. Guardar/Actualizar sección existente
    if ($accion === 'guardar') {
        $idSec = (int)($_POST['id_seccion'] ?? 0);
        $titulo = trim($_POST['titulo'] ?? '');
        $contenido = trim($_POST['contenido'] ?? '');

        if ($idSec > 0 && !empty($titulo)) {
            try {
                $st = $pdo->prepare("UPDATE secciones_informativas SET titulo=:t, contenido=:c, fecha_actualizacion=CURRENT_TIMESTAMP WHERE id=:id");
                $st->execute([':t' => $titulo, ':c' => $contenido, ':id' => $idSec]);
                $mensajeExito = "¡Sección '" . htmlspecialchars($titulo) . "' actualizada correctamente!";
            } catch (PDOException $e) {
                $mensajeError = "Error al actualizar: " . $e->getMessage();
            }
        } else {
            $mensajeError = "El título no puede estar vacío.";
        }
    }

    // 2. Cambiar estado (habilitar / inhabilitar)
    elseif ($accion === 'cambiar_estado_seccion') {
        $idSec = (int)($_POST['id_seccion'] ?? 0);
        $nuevoEstado = (int)($_POST['nuevo_estado'] ?? 1);
        if ($idSec > 0) {
            try {
                $st = $pdo->prepare("UPDATE secciones_informativas SET activo=:a WHERE id=:id");
                $st->execute([':a' => $nuevoEstado, ':id' => $idSec]);
                $mensajeExito = $nuevoEstado === 1
                    ? "¡Sección HABILITADA — ahora es visible en la intranet!"
                    : "Sección INHABILITADA — oculta de la intranet (los datos se conservan).";
            } catch (PDOException $e) {
                $mensajeError = "Error al cambiar estado: " . $e->getMessage();
            }
        }
    }

    // 3. Eliminar sección (SOLO si está inhabilitada)
    elseif ($accion === 'eliminar_seccion') {
        $idSec = (int)($_POST['id_seccion'] ?? 0);
        if ($idSec > 0) {
            try {
                // Verificar que esté inhabilitada antes de eliminar
                $stChk = $pdo->prepare("SELECT id, titulo, activo FROM secciones_informativas WHERE id=:id");
                $stChk->execute([':id' => $idSec]);
                $secChk = $stChk->fetch();

                if (!$secChk) {
                    $mensajeError = "La sección no existe.";
                } elseif ((int)$secChk['activo'] === 1) {
                    $mensajeError = "No se puede eliminar una sección habilitada. Primero inhabilítela.";
                } else {
                    $stDel = $pdo->prepare("DELETE FROM secciones_informativas WHERE id=:id AND activo=0");
                    $stDel->execute([':id' => $idSec]);
                    $mensajeExito = "Sección '" . htmlspecialchars($secChk['titulo']) . "' eliminada permanentemente.";
                    // Redirigir al selector para no dejar id_seccion inválido
                    $_REQUEST['id_seccion'] = 0;
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al eliminar sección: " . $e->getMessage();
            }
        }
    }

    // 4. Crear nueva sección
    elseif ($accion === 'crear_seccion') {
        $nuevoTitulo = trim($_POST['nuevo_titulo'] ?? '');
        $nuevaClave = strtolower(trim($_POST['nueva_clave'] ?? ''));
        $nuevaClave = preg_replace('/[^a-z0-9_-]/', '', str_replace(' ', '-', $nuevaClave));

        if (empty($nuevoTitulo) || empty($nuevaClave)) {
            $mensajeError = "El título y la clave corta son obligatorios.";
        } else {
            try {
                $stChk = $pdo->prepare("SELECT id FROM secciones_informativas WHERE clave=:c");
                $stChk->execute([':c' => $nuevaClave]);
                if ($stChk->fetch()) {
                    $mensajeError = "La clave '" . htmlspecialchars($nuevaClave) . "' ya existe. Use otra.";
                } else {
                    $stIns = $pdo->prepare("INSERT INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (:c,:t,:cont,1,CURRENT_TIMESTAMP)");
                    $stIns->execute([':c' => $nuevaClave, ':t' => $nuevoTitulo, ':cont' => '<p>Contenido inicial de la sección. Edítelo desde este panel.</p>']);
                    $nuevoId = $pdo->lastInsertId();
                    $mensajeExito = "¡Nueva sección '" . htmlspecialchars($nuevoTitulo) . "' creada con éxito!";
                    $_REQUEST['id_seccion'] = $nuevoId;
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al crear sección: " . $e->getMessage();
            }
        }
    }

    // 5. Cambiar contraseña
    elseif ($accion === 'cambiar_password') {
        $passActual = $_POST['password_actual'] ?? '';
        $passNueva = $_POST['nueva_password'] ?? '';
        $passConfirm = $_POST['confirmar_password'] ?? '';

        if (strlen($passNueva) < 6) {
            $mensajeError = "La nueva contraseña debe tener al menos 6 caracteres.";
        } elseif ($passNueva !== $passConfirm) {
            $mensajeError = "La confirmación de la nueva contraseña no coincide.";
        } else {
            try {
                $stU = $pdo->prepare("SELECT id, password_hash FROM usuarios WHERE usuario=:u");
                $stU->execute([':u' => $usuarioActual]);
                $uData = $stU->fetch();
                if ($uData && password_verify($passActual, $uData['password_hash'])) {
                    $newHash = password_hash($passNueva, PASSWORD_DEFAULT);
                    $stUp = $pdo->prepare("UPDATE usuarios SET password_hash=:h WHERE id=:id");
                    $stUp->execute([':h' => $newHash, ':id' => $uData['id']]);
                    $mensajeExito = "¡Contraseña actualizada exitosamente!";
                } else {
                    $mensajeError = "La contraseña actual es incorrecta.";
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al cambiar contraseña: " . $e->getMessage();
            }
        }
    }

    // 6. Agregar nueva pregunta de seguridad
    elseif ($accion === 'agregar_pregunta') {
        $nuevaPregunta = trim($_POST['nueva_pregunta'] ?? '');
        $nuevaRespuesta = trim($_POST['nueva_respuesta'] ?? '');

        if (empty($nuevaPregunta) || empty($nuevaRespuesta)) {
            $mensajeError = "La pregunta y la respuesta no pueden estar vacías.";
        } elseif ($usuarioId <= 0) {
            $mensajeError = "Usuario no encontrado en la base de datos.";
        } else {
            try {
                // Verificar límite de 5 preguntas
                $stCount = $pdo->prepare("SELECT COUNT(*) AS total FROM preguntas_seguridad WHERE usuario_id=:uid");
                $stCount->execute([':uid' => $usuarioId]);
                $totalPregs = (int)$stCount->fetch()['total'];

                if ($totalPregs >= 5) {
                    $mensajeError = "Ya tiene el máximo de 5 preguntas de seguridad configuradas. Elimine una antes de agregar otra.";
                } else {
                    $respHash = password_hash(strtolower($nuevaRespuesta), PASSWORD_DEFAULT);
                    $stIns = $pdo->prepare("INSERT INTO preguntas_seguridad (usuario_id, pregunta, respuesta_hash, orden) VALUES (:uid,:preg,:resp,:ord)");
                    $stIns->execute([':uid' => $usuarioId, ':preg' => $nuevaPregunta, ':resp' => $respHash, ':ord' => $totalPregs + 1]);
                    $mensajeExito = "¡Pregunta de seguridad agregada correctamente!";
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al guardar la pregunta: " . $e->getMessage();
            }
        }
    }

    // 7. Eliminar pregunta de seguridad específica
    elseif ($accion === 'eliminar_pregunta') {
        $idPregunta = (int)($_POST['id_pregunta'] ?? 0);
        if ($idPregunta > 0 && $usuarioId > 0) {
            try {
                // No permitir eliminar si es la última pregunta
                $stCount = $pdo->prepare("SELECT COUNT(*) AS total FROM preguntas_seguridad WHERE usuario_id=:uid");
                $stCount->execute([':uid' => $usuarioId]);
                $total = (int)$stCount->fetch()['total'];

                if ($total <= 1) {
                    $mensajeError = "Debe conservar al menos una pregunta de seguridad para poder recuperar la contraseña.";
                } else {
                    $stDel = $pdo->prepare("DELETE FROM preguntas_seguridad WHERE id=:id AND usuario_id=:uid");
                    $stDel->execute([':id' => $idPregunta, ':uid' => $usuarioId]);
                    $mensajeExito = "Pregunta de seguridad eliminada.";
                }
            } catch (PDOException $e) {
                $mensajeError = "Error al eliminar la pregunta: " . $e->getMessage();
            }
        }
    }
}

// ==============================================================================
// CONSULTAS PARA RENDERIZAR LA INTERFAZ
// ==============================================================================

// Todas las secciones (activas e inhabilitadas) para el administrador
try {
    $stmtList = $pdo->query("SELECT id, clave, titulo, activo FROM secciones_informativas ORDER BY activo DESC, id ASC");
    $listaSecciones = $stmtList->fetchAll();
} catch (PDOException $e) {
    $listaSecciones = [];
}

// Preguntas de seguridad del usuario actual
$preguntasSeguridad = [];
if ($usuarioId > 0) {
    try {
        $stPregs = $pdo->prepare("SELECT id, pregunta, orden FROM preguntas_seguridad WHERE usuario_id=:uid ORDER BY orden ASC");
        $stPregs->execute([':uid' => $usuarioId]);
        $preguntasSeguridad = $stPregs->fetchAll();
    } catch (Exception $e) {}
}

// Sección seleccionada actualmente
$idSeleccionado = isset($_REQUEST['id_seccion']) ? (int)$_REQUEST['id_seccion'] : 0;
if ($idSeleccionado === 0 && !empty($listaSecciones)) {
    $idSeleccionado = (int)$listaSecciones[0]['id'];
}

$seccionActual = null;
if ($idSeleccionado > 0) {
    try {
        $stSec = $pdo->prepare("SELECT id, clave, titulo, contenido, activo, fecha_actualizacion FROM secciones_informativas WHERE id=:id");
        $stSec->execute([':id' => $idSeleccionado]);
        $seccionActual = $stSec->fetch();
    } catch (PDOException $e) {}
}

// Escanear archivos en uploads/ (imágenes y videos)
$extensionesImagen = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$extensionesVideo  = ['mp4', 'webm', 'ogg', 'ogv', 'mov'];
$mediaSubidos = [];
$dirUploads = __DIR__ . '/uploads/';

if (file_exists($dirUploads)) {
    $todos = glob($dirUploads . '*.*');
    if ($todos) {
        foreach ($todos as $arc) {
            $ext = strtolower(pathinfo($arc, PATHINFO_EXTENSION));
            $tipo = '';
            if (in_array($ext, $extensionesImagen)) $tipo = 'imagen';
            elseif (in_array($ext, $extensionesVideo)) $tipo = 'video';
            if ($tipo) {
                $mediaSubidos[] = ['url' => 'uploads/' . basename($arc), 'tipo' => $tipo, 'nombre' => basename($arc)];
            }
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
    <meta name="description" content="Gestión de Contenido del CDI Jesús Rosas Marcano IUTA">

    <!-- Fuentes locales (sin internet) -->
    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- TinyMCE local -->
    <script src="assets/tinymce/tinymce.min.js"></script>

    <!-- Lucide Icons local -->
    <script src="assets/js/lucide.min.js"></script>

    <style>
        :root {
            --azul-marino-head: #0b2545;
            --azul-marino-dark: #134074;
            --azul-intenso: #0066cc;
            --azul-claro: #eef4f8;
            --texto-principal: #1e293b;
            --texto-secundario: #475569;
            --texto-suave: #64748b;
            --bg-pagina: #f8fafc;
            --bg-card: #ffffff;
            --borde-color: #cbd5e1;
            --rojo-error: #dc2626;
            --verde-exito: #16a34a;
            --naranja-alerta: #d97706;
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            --font-header: 'Merriweather', Georgia, serif;
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

        /* ---- Top Bar ---- */
        .top-bar-admin {
            background-color: #07192e;
            color: #94a3b8;
            padding: 0.6rem 2rem;
            font-size: 0.85rem;
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }

        .top-bar-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .bar-actions { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }

        .btn-top {
            color: #fff;
            background: rgba(255,255,255,0.1);
            text-decoration: none;
            padding: 0.35rem 0.8rem;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-weight: 500;
            font-size: 0.82rem;
            cursor: pointer;
            border: none;
            font-family: var(--font-main);
            transition: background 0.2s;
        }
        .btn-top:hover { background: rgba(255,255,255,0.2); }
        .btn-logout { background: rgba(220,38,38,0.75); }
        .btn-logout:hover { background: #dc2626; }
        .btn-new-sec { background: rgba(22,163,74,0.8); }
        .btn-new-sec:hover { background: #16a34a; }
        .btn-config-sec { background: rgba(0,102,204,0.8); }
        .btn-config-sec:hover { background: #0066cc; }

        /* ---- Header ---- */
        .admin-header {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #fff;
            padding: 1.75rem 2rem;
            box-shadow: 0 4px 12px rgba(11,37,69,0.12);
        }

        .admin-header-container {
            max-width: 1300px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .header-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
        }

        .admin-titles h1 {
            font-family: var(--font-header);
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
        }
        .admin-titles p { font-size: 0.88rem; color: #cbd5e1; }

        /* ---- Layout ---- */
        .admin-container {
            max-width: 1300px;
            margin: 1.75rem auto;
            padding: 0 1.5rem;
            flex: 1;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 1.75rem;
            align-items: start;
        }

        .admin-card {
            background: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 1.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .sidebar-card {
            background: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .sidebar-title {
            font-family: var(--font-header);
            font-size: 1rem;
            color: var(--azul-marino-head);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--azul-claro);
            display: flex; align-items: center; gap: 0.5rem;
        }

        /* ---- Alertas ---- */
        .alert {
            padding: 0.9rem 1.1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            display: flex; align-items: flex-start; gap: 0.6rem;
            font-size: 0.92rem; font-weight: 500;
        }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
        .alert-danger  { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }

        /* ---- Formularios ---- */
        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-weight: 600; font-size: 0.9rem;
            color: var(--azul-marino-head); margin-bottom: 0.4rem;
        }
        .form-select, .form-input {
            width: 100%; padding: 0.7rem 0.9rem;
            border: 1px solid var(--borde-color); border-radius: 8px;
            font-family: var(--font-main); font-size: 0.92rem;
            color: var(--texto-principal); background: #fff;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-select:focus, .form-input:focus {
            outline: none;
            border-color: var(--azul-intenso);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.12);
        }
        .help-text { font-size: 0.8rem; color: var(--texto-suave); margin-top: 0.3rem; }

        /* ---- Botones ---- */
        .btn-guardar {
            background: var(--azul-marino-head);
            color: #fff; border: none; padding: 0.75rem 1.5rem;
            font-size: 0.95rem; font-weight: 600; border-radius: 8px;
            cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;
            font-family: var(--font-main); transition: background 0.2s;
        }
        .btn-guardar:hover { background: var(--azul-marino-dark); }

        .btn-toggle {
            padding: 0.7rem 1.1rem; font-size: 0.9rem; font-weight: 600;
            border-radius: 8px; cursor: pointer; display: inline-flex;
            align-items: center; gap: 0.4rem; border: none;
            font-family: var(--font-main); transition: background 0.2s;
        }
        .btn-inhabilitar { background: var(--naranja-alerta); color: #fff; }
        .btn-inhabilitar:hover { background: #b45309; }
        .btn-habilitar { background: var(--verde-exito); color: #fff; }
        .btn-habilitar:hover { background: #15803d; }
        .btn-eliminar { background: var(--rojo-error); color: #fff; }
        .btn-eliminar:hover { background: #b91c1c; }

        .action-row {
            display: flex; align-items: center; gap: 0.6rem;
            margin-top: 1.5rem; flex-wrap: wrap;
        }

        /* ---- Badges ---- */
        .badge {
            display: inline-flex; align-items: center; gap: 0.25rem;
            padding: 0.2rem 0.55rem; border-radius: 20px;
            font-size: 0.75rem; font-weight: 600;
        }
        .badge-active   { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .badge-inactive { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }

        /* ---- Galería de Medios ---- */
        .upload-box {
            background: var(--azul-claro);
            border: 2px dashed #adc4dc;
            border-radius: 8px; padding: 0.85rem;
            text-align: center; margin-bottom: 1rem;
        }
        .upload-box p { font-size: 0.8rem; color: var(--texto-secundario); margin-bottom: 0.5rem; }

        .btn-upload {
            background: var(--azul-intenso); color: #fff; border: none;
            padding: 0.4rem 0.9rem; font-size: 0.8rem; font-weight: 600;
            border-radius: 6px; cursor: pointer; display: inline-flex;
            align-items: center; gap: 0.35rem; font-family: var(--font-main);
        }
        .btn-upload:hover { background: #0052a3; }

        .filter-tabs { display: flex; gap: 0.4rem; margin-bottom: 0.75rem; }
        .filter-tab {
            flex: 1; padding: 0.3rem 0; text-align: center; font-size: 0.78rem;
            font-weight: 600; cursor: pointer; border: 1px solid var(--borde-color);
            border-radius: 6px; background: #fff; color: var(--texto-secundario);
            font-family: var(--font-main); transition: all 0.2s;
        }
        .filter-tab.active {
            background: var(--azul-marino-head); color: #fff; border-color: var(--azul-marino-head);
        }

        .gallery-grid {
            display: grid; grid-template-columns: repeat(2, 1fr);
            gap: 0.6rem; max-height: 380px; overflow-y: auto;
        }

        .media-item {
            position: relative; border: 1px solid var(--borde-color);
            border-radius: 8px; overflow: hidden; background: #000;
        }

        .media-item img, .media-item video {
            width: 100%; height: 80px; object-fit: cover; display: block;
            opacity: 0.9; transition: opacity 0.2s;
        }
        .media-item:hover img, .media-item:hover video { opacity: 1; }

        .media-badge {
            position: absolute; top: 4px; left: 4px;
            background: rgba(0,0,0,0.7); color: #fff; font-size: 0.62rem;
            font-weight: 700; padding: 0.12rem 0.35rem; border-radius: 4px;
        }

        .media-actions {
            position: absolute; bottom: 3px; left: 3px; right: 3px;
            display: flex; gap: 3px;
        }
        .btn-insert {
            flex: 1; background: rgba(11,37,69,0.85); color: #fff;
            border: none; font-size: 0.68rem; font-weight: 600;
            padding: 0.25rem 0.3rem; border-radius: 4px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            font-family: var(--font-main);
        }
        .btn-insert:hover { background: var(--azul-intenso); }
        .btn-delete-media {
            background: rgba(220,38,38,0.85); color: #fff; border: none;
            padding: 0.25rem 0.4rem; border-radius: 4px; cursor: pointer;
            display: flex; align-items: center;
        }
        .btn-delete-media:hover { background: #dc2626; }

        .empty-gallery { font-size: 0.8rem; color: var(--texto-suave); text-align: center; padding: 1rem; grid-column: span 2; }

        /* ---- Preguntas de Seguridad ---- */
        .preguntas-lista { margin-bottom: 1rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .pregunta-item {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 0.5rem; background: var(--azul-claro); border: 1px solid #dce7f1;
            border-radius: 8px; padding: 0.6rem 0.8rem; font-size: 0.88rem;
        }
        .pregunta-item span { flex: 1; color: var(--texto-principal); font-weight: 500; }
        .btn-del-preg {
            background: rgba(220,38,38,0.1); color: #dc2626; border: 1px solid #fca5a5;
            border-radius: 6px; padding: 0.2rem 0.4rem; cursor: pointer; font-size: 0.8rem;
            display: flex; align-items: center; gap: 0.2rem; flex-shrink: 0;
        }
        .btn-del-preg:hover { background: #fef2f2; }

        .pregunta-contador {
            font-size: 0.78rem; color: var(--texto-suave);
            text-align: right; margin-bottom: 0.5rem;
        }

        /* ---- Modales ---- */
        .modal-backdrop {
            position: fixed; inset: 0;
            background: rgba(15,23,42,0.7);
            backdrop-filter: blur(4px);
            display: none; align-items: center; justify-content: center;
            z-index: 999; padding: 1rem;
        }
        .modal-box {
            background: #fff; border-radius: 16px;
            width: 100%; max-width: 540px; padding: 1.75rem;
            box-shadow: 0 20px 25px rgba(0,0,0,0.25);
            max-height: 90vh; overflow-y: auto;
        }
        .modal-header {
            display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid var(--borde-color); padding-bottom: 0.85rem; margin-bottom: 1.25rem;
        }
        .modal-header h3 { font-family: var(--font-header); color: var(--azul-marino-head); font-size: 1.15rem; }
        .btn-close-modal { background: transparent; border: none; cursor: pointer; color: var(--texto-suave); }

        .modal-section-title {
            font-size: 0.9rem; font-weight: 700; color: var(--azul-marino-head);
            margin-bottom: 0.75rem; padding-bottom: 0.4rem;
            border-bottom: 1px solid var(--azul-claro);
        }

        /* ---- Footer ---- */
        .admin-footer {
            background: #07192e; color: #64748b;
            text-align: center; padding: 1.25rem; font-size: 0.82rem;
            margin-top: auto;
        }

        @media (max-width: 1000px) {
            .admin-container { grid-template-columns: 1fr; }
        }

        /* HR separadores en modal */
        .modal-hr { border: 0; border-top: 1px solid var(--borde-color); margin: 1.25rem 0; }
    </style>
</head>
<body>

<!-- Barra Superior -->
<div class="top-bar-admin">
    <div class="top-bar-container">
        <span><strong>Panel Administrativo CDI IUTA</strong> — Usuario: <em><?= htmlspecialchars($usuarioActual) ?></em></span>
        <div class="bar-actions">
            <button type="button" class="btn-top btn-new-sec" onclick="abrirModal('modalNuevaSeccion')">
                <i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Nueva Sección
            </button>
            <button type="button" class="btn-top btn-config-sec" onclick="abrirModal('modalSeguridad')">
                <i data-lucide="shield" style="width:14px;height:14px;"></i> Seguridad y Clave
            </button>
            <a href="index.php" class="btn-top">
                <i data-lucide="eye" style="width:14px;height:14px;"></i> Ver Intranet
            </a>
            <a href="logout.php" class="btn-top btn-logout">
                <i data-lucide="log-out" style="width:14px;height:14px;"></i> Cerrar Sesión
            </a>
        </div>
    </div>
</div>

<!-- Encabezado -->
<header class="admin-header">
    <div class="admin-header-container">
        <div class="header-icon"><i data-lucide="file-cog" style="width:28px;height:28px;"></i></div>
        <div class="admin-titles">
            <h1>Gestor de Contenidos del CDI</h1>
            <p>Edición de secciones, gestión de medios (imágenes y videos) y configuración de seguridad</p>
        </div>
    </div>
</header>

<!-- Contenido Principal -->
<main class="admin-container">

    <!-- Panel de Edición -->
    <div class="admin-card">

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

        <!-- Selector de sección -->
        <form method="GET" action="admin.php" class="form-group">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                <label style="margin-bottom:0;">Sección a Editar:</label>
                <button type="button" class="btn-top btn-new-sec" style="font-size:0.76rem; padding:0.22rem 0.55rem;" onclick="abrirModal('modalNuevaSeccion')">
                    <i data-lucide="plus" style="width:13px;height:13px;"></i> Agregar
                </button>
            </div>
            <select name="id_seccion" class="form-select" onchange="this.form.submit();">
                <?php foreach ($listaSecciones as $opc):
                    $esAct = (int)$opc['activo'] === 1;
                    $etiqueta = $esAct ? '[Habilitada]' : '[INHABILITADA]';
                ?>
                    <option value="<?= $opc['id'] ?>" <?= $opc['id'] == $idSeleccionado ? 'selected' : '' ?>>
                        <?= htmlspecialchars($opc['titulo']) ?> <?= $etiqueta ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="help-text">Las secciones inhabilitadas no se muestran a los usuarios en la intranet pública.</p>
        </form>

        <hr style="border:0; border-top:1px solid var(--borde-color); margin:1.25rem 0;">

        <?php if ($seccionActual):
            $estaHabilitada = (int)$seccionActual['activo'] === 1;
        ?>
            <form method="POST" action="admin.php" id="formEdicion">
                <input type="hidden" name="accion" value="guardar">
                <input type="hidden" name="id_seccion" value="<?= (int)$seccionActual['id'] ?>">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                    <label style="font-weight:700; color:var(--azul-marino-head); font-size:0.95rem;">Editar: <?= htmlspecialchars($seccionActual['titulo']) ?></label>
                    <?php if ($estaHabilitada): ?>
                        <span class="badge badge-active"><i data-lucide="check-circle" style="width:12px;height:12px;"></i> Habilitada</span>
                    <?php else: ?>
                        <span class="badge badge-inactive"><i data-lucide="eye-off" style="width:12px;height:12px;"></i> Inhabilitada</span>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label for="input_titulo">Título de la Sección:</label>
                    <input type="text" name="titulo" id="input_titulo" class="form-input" required value="<?= htmlspecialchars($seccionActual['titulo']) ?>">
                </div>

                <div class="form-group">
                    <label for="contenido">Contenido Enriquecido:</label>
                    <textarea name="contenido" id="contenido" rows="16"><?= htmlspecialchars($seccionActual['contenido']) ?></textarea>
                    <p class="help-text">💡 Use la galería lateral para insertar imágenes o videos. También puede pegarlos directamente o usar el botón de medios del editor.</p>
                </div>

                <div class="action-row">
                    <button type="submit" class="btn-guardar">
                        <i data-lucide="save" style="width:17px;height:17px;"></i> Guardar Cambios
                    </button>

                    <?php if ($estaHabilitada): ?>
                        <button type="button" class="btn-toggle btn-inhabilitar"
                            onclick="cambiarEstado(<?= (int)$seccionActual['id'] ?>, 0, '<?= htmlspecialchars($seccionActual['titulo'], ENT_QUOTES) ?>')">
                            <i data-lucide="eye-off" style="width:16px;height:16px;"></i> Inhabilitar
                        </button>
                    <?php else: ?>
                        <button type="button" class="btn-toggle btn-habilitar"
                            onclick="cambiarEstado(<?= (int)$seccionActual['id'] ?>, 1, '<?= htmlspecialchars($seccionActual['titulo'], ENT_QUOTES) ?>')">
                            <i data-lucide="eye" style="width:16px;height:16px;"></i> Habilitar
                        </button>

                        <!-- Solo visible si la sección está inhabilitada -->
                        <button type="button" class="btn-toggle btn-eliminar"
                            onclick="eliminarSeccion(<?= (int)$seccionActual['id'] ?>, '<?= htmlspecialchars($seccionActual['titulo'], ENT_QUOTES) ?>')">
                            <i data-lucide="trash-2" style="width:16px;height:16px;"></i> Eliminar Permanentemente
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align:center; padding:2rem; color:var(--texto-suave);">
                <i data-lucide="folder-open" style="width:40px;height:40px; margin-bottom:0.75rem; display:block; margin-inline:auto;"></i>
                <p>No hay secciones creadas. Haga clic en <strong>"+ Nueva Sección"</strong> para comenzar.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Sidebar: Galería de Medios -->
    <aside class="sidebar-card">
        <h3 class="sidebar-title">
            <i data-lucide="image" style="width:18px;height:18px; color:var(--azul-intenso);"></i>
            Galería de Medios
        </h3>

        <!-- Zona de Subida -->
        <div class="upload-box">
            <p>Subir imagen o video:</p>
            <input type="file" id="inputMedia" accept="image/*,video/mp4,video/webm,video/ogg,.mov" style="display:none;" onchange="subirMediaDirecto(this)">
            <button type="button" class="btn-upload" onclick="document.getElementById('inputMedia').click()">
                <i data-lucide="upload" style="width:13px;height:13px;"></i> Seleccionar Archivo
            </button>
            <p style="margin-top:0.4rem; font-size:0.72rem;">Imágenes: máx. 5 MB | Videos: máx. 100 MB</p>
        </div>

        <!-- Filtros Imagen / Video / Todos -->
        <div class="filter-tabs">
            <button class="filter-tab active" id="tab-todos" onclick="filtrarGaleria('todos')">Todos</button>
            <button class="filter-tab" id="tab-imagenes" onclick="filtrarGaleria('imagen')">Imágenes</button>
            <button class="filter-tab" id="tab-videos" onclick="filtrarGaleria('video')">Videos</button>
        </div>

        <div class="gallery-grid" id="galeriaGrid">
            <?php if (empty($mediaSubidos)): ?>
                <p class="empty-gallery">No hay archivos subidos aún.</p>
            <?php else: ?>
                <?php foreach ($mediaSubidos as $media):
                    $uid = md5($media['url']);
                ?>
                    <div class="media-item" id="item-<?= $uid ?>" data-tipo="<?= $media['tipo'] ?>">
                        <?php if ($media['tipo'] === 'imagen'): ?>
                            <img src="<?= htmlspecialchars($media['url']) ?>" alt="<?= htmlspecialchars($media['nombre']) ?>" loading="lazy">
                            <span class="media-badge">IMG</span>
                        <?php else: ?>
                            <video src="<?= htmlspecialchars($media['url']) ?>" muted preload="metadata"></video>
                            <span class="media-badge" style="background:rgba(99,38,163,0.85);">VID</span>
                        <?php endif; ?>
                        <div class="media-actions">
                            <button type="button" class="btn-insert"
                                onclick="insertarMediaEnEditor('<?= htmlspecialchars($media['url'], ENT_QUOTES) ?>', '<?= $media['tipo'] ?>')">
                                <i data-lucide="plus" style="width:11px;height:11px;"></i> Insertar
                            </button>
                            <button type="button" class="btn-delete-media" title="Eliminar"
                                onclick="eliminarMedia('<?= htmlspecialchars($media['url'], ENT_QUOTES) ?>', 'item-<?= $uid ?>')">
                                <i data-lucide="trash-2" style="width:11px;height:11px;"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>

</main>

<!-- Modal: Nueva Sección -->
<div class="modal-backdrop" id="modalNuevaSeccion">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Crear Nueva Sección Informativa</h3>
            <button type="button" class="btn-close-modal" onclick="cerrarModal('modalNuevaSeccion')">
                <i data-lucide="x"></i>
            </button>
        </div>
        <form method="POST" action="admin.php">
            <input type="hidden" name="accion" value="crear_seccion">
            <div class="form-group">
                <label for="nuevo_titulo">Título de la Sección:</label>
                <input type="text" name="nuevo_titulo" id="nuevo_titulo" class="form-input" required placeholder="Ej: Reglamentos Académicos">
            </div>
            <div class="form-group">
                <label for="nueva_clave">Clave Corta (identificador único):</label>
                <input type="text" name="nueva_clave" id="nueva_clave" class="form-input" required placeholder="Ej: reglamentos">
                <span class="help-text">Solo letras minúsculas, números y guiones. Se usará en la URL.</span>
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:1.25rem;">
                <button type="button" class="btn-top" style="background:#64748b;" onclick="cerrarModal('modalNuevaSeccion')">Cancelar</button>
                <button type="submit" class="btn-guardar">Crear Sección</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Seguridad y Contraseña -->
<div class="modal-backdrop" id="modalSeguridad">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Seguridad y Contraseña</h3>
            <button type="button" class="btn-close-modal" onclick="cerrarModal('modalSeguridad')">
                <i data-lucide="x"></i>
            </button>
        </div>

        <!-- Sección 1: Preguntas de seguridad -->
        <p class="modal-section-title">1. Preguntas de Recuperación de Contraseña</p>

        <p class="pregunta-contador">
            <?= count($preguntasSeguridad) ?> / 5 preguntas configuradas
        </p>

        <?php if (!empty($preguntasSeguridad)): ?>
            <div class="preguntas-lista">
                <?php foreach ($preguntasSeguridad as $pq): ?>
                    <div class="pregunta-item">
                        <span><?= htmlspecialchars($pq['pregunta']) ?></span>
                        <form method="POST" action="admin.php" style="display:inline;">
                            <input type="hidden" name="accion" value="eliminar_pregunta">
                            <input type="hidden" name="id_pregunta" value="<?= (int)$pq['id'] ?>">
                            <button type="submit" class="btn-del-preg" title="Eliminar esta pregunta"
                                onclick="return confirm('¿Eliminar esta pregunta de seguridad?')">
                                <i data-lucide="trash-2" style="width:13px;height:13px;"></i> Quitar
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="font-size:0.85rem; color:var(--texto-suave); margin-bottom:0.75rem;">
                No tiene preguntas de seguridad configuradas. Agregue al menos una.
            </p>
        <?php endif; ?>

        <?php if (count($preguntasSeguridad) < 5): ?>
            <form method="POST" action="admin.php">
                <input type="hidden" name="accion" value="agregar_pregunta">
                <div class="form-group">
                    <label for="nueva_pregunta">Nueva Pregunta de Seguridad:</label>
                    <input type="text" name="nueva_pregunta" id="nueva_pregunta" class="form-input"
                        required placeholder="Ej: ¿Nombre de su primera mascota?">
                </div>
                <div class="form-group">
                    <label for="nueva_respuesta">Respuesta Secreta:</label>
                    <input type="password" name="nueva_respuesta" id="nueva_respuesta" class="form-input"
                        required placeholder="Respuesta (no se muestra al recuperar, solo se verifica)">
                    <span class="help-text">La respuesta se guarda de forma encriptada. No se distinguen mayúsculas.</span>
                </div>
                <button type="submit" class="btn-guardar" style="width:100%;">
                    <i data-lucide="plus" style="width:16px;height:16px;"></i> Agregar Pregunta
                </button>
            </form>
        <?php else: ?>
            <p style="font-size:0.82rem; color:var(--texto-suave); text-align:center; padding:0.5rem;">
                Límite máximo de 5 preguntas alcanzado. Elimine una para agregar otra.
            </p>
        <?php endif; ?>

        <hr class="modal-hr">

        <!-- Sección 2: Cambiar contraseña -->
        <p class="modal-section-title">2. Cambiar Contraseña de Acceso</p>
        <form method="POST" action="admin.php">
            <input type="hidden" name="accion" value="cambiar_password">
            <div class="form-group">
                <label for="password_actual">Contraseña Actual:</label>
                <input type="password" name="password_actual" id="password_actual" class="form-input" required placeholder="Su contraseña actual">
            </div>
            <div class="form-group">
                <label for="nueva_password">Nueva Contraseña:</label>
                <input type="password" name="nueva_password" id="nueva_password" class="form-input" required placeholder="Mínimo 6 caracteres">
            </div>
            <div class="form-group">
                <label for="confirmar_password">Confirmar Nueva Contraseña:</label>
                <input type="password" name="confirmar_password" id="confirmar_password" class="form-input" required placeholder="Repita la nueva contraseña">
            </div>
            <div style="display:flex; justify-content:flex-end; gap:0.5rem; margin-top:0.75rem;">
                <button type="button" class="btn-top" style="background:#64748b;" onclick="cerrarModal('modalSeguridad')">Cerrar</button>
                <button type="submit" class="btn-guardar">Actualizar Contraseña</button>
            </div>
        </form>
    </div>
</div>

<!-- Formulario oculto para cambiar estado (habilitar/inhabilitar) -->
<form id="formEstadoSeccion" method="POST" action="admin.php">
    <input type="hidden" name="accion" value="cambiar_estado_seccion">
    <input type="hidden" name="id_seccion" id="idSeccionEstado" value="0">
    <input type="hidden" name="nuevo_estado" id="nuevoEstadoValor" value="1">
</form>

<!-- Formulario oculto para eliminar sección -->
<form id="formEliminarSeccion" method="POST" action="admin.php">
    <input type="hidden" name="accion" value="eliminar_seccion">
    <input type="hidden" name="id_seccion" id="idSeccionEliminar" value="0">
</form>

<footer class="admin-footer">
    <p>&copy; <?= date('Y') ?> CDI "Jesús Rosas Marcano" — Intranet IUTA | Panel de Administración</p>
</footer>

<script>
    // ---- TinyMCE (local) ----
    tinymce.init({
        selector: '#contenido',
        height: 440,
        language: 'es',
        base_url: '/PagIUTA-1/assets/tinymce',
        suffix: '.min',
        plugins: 'advlist autolink lists link image media charmap preview anchor visualblocks code fullscreen table wordcount',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline forecolor backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image media table | removeformat code preview fullscreen',
        content_style: 'body { font-family: Inter, Helvetica, Arial, sans-serif; font-size:15px; line-height:1.6; color:#1e293b; } img,video { max-width:100%; height:auto; border-radius:8px; margin:10px 0; display:block; }',
        branding: false,
        promotion: false,
        images_upload_url: 'upload_imagen.php',
        automatic_uploads: true,
        file_picker_types: 'image media',
        // Soporte de video en TinyMCE
        media_live_embeds: true,
        media_url_resolver: function(data, resolve) {
            resolve({ html: '<video controls style="max-width:100%;border-radius:8px;" src="' + data.url + '"></video>' });
        },
        file_picker_callback: function(cb, value, meta) {
            var input = document.createElement('input');
            input.setAttribute('type', 'file');
            if (meta.filetype === 'media' || meta.filetype === 'image') {
                input.setAttribute('accept', 'image/*,video/mp4,video/webm,video/ogg,.mov');
            }
            input.onchange = function() {
                var file = this.files[0];
                var fd = new FormData();
                fd.append('file', file);
                fetch('upload_imagen.php', { method: 'POST', body: fd })
                    .then(r => r.json())
                    .then(data => {
                        if (data.location) { cb(data.location, { title: file.name }); }
                        else { alert(data.error || 'Error al subir el archivo.'); }
                    })
                    .catch(() => alert('Error de conexión al subir el archivo.'));
            };
            input.click();
        },
        setup: function(editor) {
            editor.on('change', function() { editor.save(); });
        }
    });

    // ---- Modales ----
    function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
    function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

    // Cerrar modal al hacer clic fuera de él
    document.querySelectorAll('.modal-backdrop').forEach(function(el) {
        el.addEventListener('click', function(e) {
            if (e.target === el) el.style.display = 'none';
        });
    });

    // ---- Cambiar Estado (Habilitar / Inhabilitar) ----
    function cambiarEstado(id, nuevoEstado, titulo) {
        var accion = nuevoEstado === 0 ? 'inhabilitar (ocultar de la intranet)' : 'habilitar (hacer visible en la intranet)';
        if (confirm('¿Desea ' + accion + ' la sección "' + titulo + '"?')) {
            document.getElementById('idSeccionEstado').value = id;
            document.getElementById('nuevoEstadoValor').value = nuevoEstado;
            document.getElementById('formEstadoSeccion').submit();
        }
    }

    // ---- Eliminar Sección (solo inhabilitada) ----
    function eliminarSeccion(id, titulo) {
        var confirmacion = confirm(
            '⚠️ ADVERTENCIA: Esta acción es PERMANENTE e irreversible.\n\n' +
            'Va a eliminar la sección "' + titulo + '" y todo su contenido.\n\n' +
            '¿Está completamente seguro de que desea continuar?'
        );
        if (confirmacion) {
            document.getElementById('idSeccionEliminar').value = id;
            document.getElementById('formEliminarSeccion').submit();
        }
    }

    // ---- Filtro de Galería ----
    function filtrarGaleria(tipo) {
        var items = document.querySelectorAll('.media-item');
        items.forEach(function(item) {
            if (tipo === 'todos' || item.dataset.tipo === tipo) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
        document.querySelectorAll('.filter-tab').forEach(function(tab) {
            tab.classList.remove('active');
        });
        var mapTab = { 'todos': 'tab-todos', 'imagen': 'tab-imagenes', 'video': 'tab-videos' };
        if (mapTab[tipo]) document.getElementById(mapTab[tipo]).classList.add('active');
    }

    // ---- Insertar Imagen o Video en TinyMCE ----
    function insertarMediaEnEditor(url, tipo) {
        if (!tinymce.activeEditor) return;
        var html = '';
        if (tipo === 'video') {
            html = '<p><video controls style="max-width:100%; height:auto; border-radius:8px;" src="' + url + '"><p>Su navegador no soporta el elemento de video.</p></video></p>';
        } else {
            html = '<p><img src="' + url + '" alt="Imagen CDI IUTA" style="max-width:100%; height:auto; border-radius:8px;" /></p>';
        }
        tinymce.activeEditor.insertContent(html);
    }

    // ---- Eliminar Imagen / Video del servidor ----
    function eliminarMedia(url, elementId) {
        if (!confirm('¿Eliminar este archivo del servidor? Esta acción no se puede deshacer.')) return;
        var fd = new FormData();
        fd.append('imagen', url);
        fetch('eliminar_imagen.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.exito) {
                    var el = document.getElementById(elementId);
                    if (el) el.remove();
                } else {
                    alert(data.error || 'No se pudo eliminar el archivo.');
                }
            })
            .catch(() => alert('Error al procesar la eliminación.'));
    }

    // ---- Subida directa desde la galería lateral ----
    function subirMediaDirecto(inputEl) {
        if (!inputEl.files || !inputEl.files[0]) return;
        var file = inputEl.files[0];
        var fd = new FormData();
        fd.append('file', file);
        fetch('upload_imagen.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.location) {
                    // Insertar automáticamente en el editor y recargar galería
                    insertarMediaEnEditor(data.location, data.tipo);
                    location.reload();
                } else {
                    alert(data.error || 'Error al subir el archivo.');
                }
            })
            .catch(() => alert('Error en la transmisión del archivo.'));
    }

    // Inicializar iconos Lucide
    lucide.createIcons();
</script>
</body>
</html>
