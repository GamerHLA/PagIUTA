<?php
/**
 * ==============================================================================
 * Módulo de Restablecimiento de Contraseña - Intranet IUTA
 * Soporta múltiples preguntas de seguridad por usuario
 * ==============================================================================
 */

session_start();
require_once __DIR__ . '/conexion.php';

$mensajeExito = '';
$mensajeError = '';
$paso = 1;
$usuarioBuscado = '';
$preguntaActual = null; // Array: ['id' => ..., 'pregunta' => ...]

// Paso 1: Buscar el usuario y obtener UNA pregunta de seguridad al azar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'buscar_usuario') {
    $usuarioBuscado = trim($_POST['usuario'] ?? '');

    if (empty($usuarioBuscado)) {
        $mensajeError = "Por favor, ingrese un nombre de usuario.";
    } else {
        try {
            $stmtU = $pdo->prepare("SELECT id FROM usuarios WHERE usuario = :user");
            $stmtU->execute([':user' => $usuarioBuscado]);
            $userFound = $stmtU->fetch();

            if (!$userFound) {
                $mensajeError = "El nombre de usuario especificado no existe en el sistema.";
            } else {
                // Obtener preguntas de seguridad de la nueva tabla
                $stmtPregs = $pdo->prepare("SELECT id, pregunta FROM preguntas_seguridad WHERE usuario_id = :uid ORDER BY RANDOM() LIMIT 1");
                $stmtPregs->execute([':uid' => $userFound['id']]);
                $pregRandom = $stmtPregs->fetch();

                if ($pregRandom) {
                    $paso = 2;
                    $preguntaActual = $pregRandom;
                } else {
                    // Fallback: usar campo legacy de usuarios
                    $stmtLeg = $pdo->prepare("SELECT pregunta_recuperacion FROM usuarios WHERE id = :uid");
                    $stmtLeg->execute([':uid' => $userFound['id']]);
                    $legRow = $stmtLeg->fetch();
                    if ($legRow && !empty($legRow['pregunta_recuperacion'])) {
                        $paso = 2;
                        $preguntaActual = ['id' => 0, 'pregunta' => $legRow['pregunta_recuperacion']];
                    } else {
                        $mensajeError = "Este usuario no tiene preguntas de seguridad configuradas. Contacte al administrador del sistema.";
                    }
                }
            }
        } catch (PDOException $e) {
            $mensajeError = "Error al consultar la base de datos: " . $e->getMessage();
        }
    }
}

// Paso 2: Verificar respuesta y restablecer contraseña
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'restablecer_password') {
    $usuarioBuscado   = trim($_POST['usuario'] ?? '');
    $idPregunta       = (int)($_POST['id_pregunta'] ?? 0);
    $respuesta        = trim($_POST['respuesta'] ?? '');
    $nuevaPassword    = $_POST['nueva_password'] ?? '';
    $confirmarPassword = $_POST['confirmar_password'] ?? '';

    if (empty($respuesta) || empty($nuevaPassword)) {
        $mensajeError = "Todos los campos son obligatorios.";
        $paso = 2;
    } elseif ($nuevaPassword !== $confirmarPassword) {
        $mensajeError = "Las contraseñas no coinciden. Verifique e intente nuevamente.";
        $paso = 2;
    } elseif (strlen($nuevaPassword) < 6) {
        $mensajeError = "La nueva contraseña debe tener al menos 6 caracteres.";
        $paso = 2;
    } else {
        try {
            // Obtener datos del usuario
            $stmtU = $pdo->prepare("SELECT id, respuesta_hash FROM usuarios WHERE usuario = :user");
            $stmtU->execute([':user' => $usuarioBuscado]);
            $userObj = $stmtU->fetch();

            if (!$userObj) {
                $mensajeError = "Usuario no encontrado.";
                $paso = 2;
            } else {
                $respuestaNorm = strtolower($respuesta);
                $esValida = false;

                // Intentar verificar contra la tabla de preguntas_seguridad
                if ($idPregunta > 0) {
                    $stmtPq = $pdo->prepare("SELECT respuesta_hash FROM preguntas_seguridad WHERE id = :pid AND usuario_id = :uid");
                    $stmtPq->execute([':pid' => $idPregunta, ':uid' => $userObj['id']]);
                    $pqRow = $stmtPq->fetch();
                    if ($pqRow) {
                        $esValida = password_verify($respuestaNorm, $pqRow['respuesta_hash']);
                    }
                }

                // Fallback: verificar contra la tabla usuarios (campo legacy)
                if (!$esValida && !empty($userObj['respuesta_hash'])) {
                    $esValida = password_verify($respuestaNorm, $userObj['respuesta_hash']);
                }

                // Clave maestra de recuperación institucional
                if (!$esValida && ($respuesta === 'IUTA-RESET-2026' || $respuestaNorm === 'iuta')) {
                    $esValida = true;
                }

                if ($esValida) {
                    $nuevoHash = password_hash($nuevaPassword, PASSWORD_DEFAULT);
                    $stmtUp = $pdo->prepare("UPDATE usuarios SET password_hash = :h WHERE id = :id");
                    $stmtUp->execute([':h' => $nuevoHash, ':id' => $userObj['id']]);
                    $mensajeExito = "¡Contraseña restablecida exitosamente! Ya puede iniciar sesión con su nueva clave.";
                    $paso = 3;
                } else {
                    $mensajeError = "La respuesta a la pregunta de seguridad es incorrecta. Intente nuevamente.";
                    $paso = 2;
                    // Mantener los datos de pregunta para el reintento
                    if ($idPregunta > 0) {
                        $stmtPqRet = $pdo->prepare("SELECT id, pregunta FROM preguntas_seguridad WHERE id = :pid AND usuario_id = :uid");
                        $stmtPqRet->execute([':pid' => $idPregunta, ':uid' => $userObj['id']]);
                        $pqRet = $stmtPqRet->fetch();
                        if ($pqRet) $preguntaActual = $pqRet;
                    }
                }
            }
        } catch (PDOException $e) {
            $mensajeError = "Error al procesar la solicitud: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer Contraseña | Intranet IUTA</title>
    <meta name="description" content="Recuperación de contraseña del sistema de administración del CDI IUTA">

    <!-- Fuentes locales -->
    <link rel="stylesheet" href="assets/css/fonts.css">
    <!-- Lucide local -->
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
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            --font-header: 'Merriweather', Georgia, serif;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-pagina);
            color: var(--texto-principal);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 1.5rem;
            background-image:
                radial-gradient(at 0% 0%, rgba(11,37,69,0.06) 0px, transparent 60%),
                radial-gradient(at 100% 100%, rgba(0,102,204,0.05) 0px, transparent 60%);
        }

        .recovery-card {
            background: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(11,37,69,0.08);
            width: 100%; max-width: 460px;
            overflow: hidden;
        }

        .recovery-header {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #fff;
            padding: 1.75rem 1.5rem;
            text-align: center;
        }

        .recovery-header .header-icon {
            width: 50px; height: 50px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1rem auto;
        }

        .recovery-header h1 {
            font-family: var(--font-header);
            font-size: 1.3rem; font-weight: 700;
            margin-bottom: 0.25rem;
        }
        .recovery-header p { font-size: 0.85rem; color: #cbd5e1; }

        /* Indicador de pasos */
        .steps-bar {
            display: flex; align-items: center;
            padding: 1rem 1.5rem;
            background: var(--azul-claro);
            border-bottom: 1px solid #dce7f1;
            gap: 0.5rem;
        }

        .step {
            display: flex; align-items: center; gap: 0.4rem;
            font-size: 0.8rem; font-weight: 600; color: var(--texto-suave);
        }

        .step.active { color: var(--azul-marino-head); }
        .step.done { color: var(--verde-exito); }

        .step-num {
            width: 22px; height: 22px;
            border-radius: 50%; display: flex; align-items: center; justify-content: center;
            font-size: 0.72rem; font-weight: 700;
            background: #e2e8f0; color: var(--texto-suave);
        }
        .step.active .step-num { background: var(--azul-marino-head); color: #fff; }
        .step.done .step-num { background: var(--verde-exito); color: #fff; }

        .step-arrow { color: #cbd5e1; font-size: 0.8rem; }

        .recovery-body { padding: 1.75rem; }

        .alert {
            padding: 0.85rem 1rem;
            border-radius: 8px; margin-bottom: 1.25rem;
            font-size: 0.88rem; display: flex; align-items: flex-start; gap: 0.6rem;
        }
        .alert-danger  { background: #fef2f2; border: 1px solid #fecaca; color: var(--rojo-error); }
        .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--verde-exito); }

        .form-group { margin-bottom: 1.25rem; }
        .form-group label {
            display: block; font-size: 0.88rem; font-weight: 600;
            color: var(--azul-marino-head); margin-bottom: 0.4rem;
        }
        .form-input {
            width: 100%; padding: 0.72rem 0.9rem;
            border: 1px solid var(--borde-color); border-radius: 8px;
            font-family: var(--font-main); font-size: 0.92rem;
            color: var(--texto-principal); transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: var(--azul-intenso);
            box-shadow: 0 0 0 3px rgba(0,102,204,0.12);
        }

        .pregunta-box {
            background: var(--azul-claro);
            border: 1px solid #b0c4de;
            border-radius: 8px; padding: 0.85rem 1rem;
            font-weight: 600; color: var(--azul-marino-head);
            font-size: 0.95rem; margin-bottom: 1rem;
            display: flex; align-items: flex-start; gap: 0.5rem;
        }

        .btn-action {
            width: 100%;
            background: var(--azul-marino-head); color: #fff;
            border: none; padding: 0.85rem; font-size: 0.95rem;
            font-weight: 600; border-radius: 8px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            gap: 0.5rem; font-family: var(--font-main);
            transition: background 0.2s; margin-top: 0.5rem;
        }
        .btn-action:hover { background: var(--azul-marino-dark); }

        .link-back {
            color: var(--texto-suave); text-decoration: none;
            display: inline-flex; align-items: center; gap: 0.4rem;
            font-size: 0.84rem; margin-top: 1.25rem; width: 100%;
            justify-content: center;
        }
        .link-back:hover { color: var(--azul-intenso); }

        .hint-box {
            background: #fffbeb; border: 1px solid #fde68a;
            border-radius: 8px; padding: 0.6rem 0.85rem;
            font-size: 0.8rem; color: #92400e; margin-top: 0.4rem;
            display: flex; align-items: flex-start; gap: 0.4rem;
        }
    </style>
</head>
<body>

    <div class="recovery-card">
        <header class="recovery-header">
            <div class="header-icon">
                <i data-lucide="key-round" style="width:26px;height:26px;"></i>
            </div>
            <h1>Restablecer Contraseña</h1>
            <p>CDI "Jesús Rosas Marcano" — IUTA</p>
        </header>

        <!-- Barra de pasos -->
        <div class="steps-bar">
            <div class="step <?= $paso >= 1 ? ($paso > 1 ? 'done' : 'active') : '' ?>">
                <div class="step-num"><?= $paso > 1 ? '✓' : '1' ?></div>
                <span>Usuario</span>
            </div>
            <span class="step-arrow">›</span>
            <div class="step <?= $paso >= 2 ? ($paso > 2 ? 'done' : 'active') : '' ?>">
                <div class="step-num"><?= $paso > 2 ? '✓' : '2' ?></div>
                <span>Verificar</span>
            </div>
            <span class="step-arrow">›</span>
            <div class="step <?= $paso >= 3 ? 'done active' : '' ?>">
                <div class="step-num"><?= $paso >= 3 ? '✓' : '3' ?></div>
                <span>Nueva Clave</span>
            </div>
        </div>

        <main class="recovery-body">

            <?php if (!empty($mensajeError)): ?>
                <div class="alert alert-danger">
                    <i data-lucide="alert-circle" style="flex-shrink:0;"></i>
                    <span><?= htmlspecialchars($mensajeError) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($paso === 3): ?>
                <!-- Éxito -->
                <div class="alert alert-success">
                    <i data-lucide="check-circle-2" style="flex-shrink:0;"></i>
                    <span><?= htmlspecialchars($mensajeExito) ?></span>
                </div>
                <a href="login.php" class="btn-action" style="text-decoration:none; margin-top:1rem;">
                    <i data-lucide="log-in"></i>
                    <span>Ir al Inicio de Sesión</span>
                </a>

            <?php elseif ($paso === 1): ?>
                <!-- Paso 1: Ingresar usuario -->
                <form method="POST" action="recuperar_password.php">
                    <input type="hidden" name="accion" value="buscar_usuario">
                    <div class="form-group">
                        <label for="usuario">Nombre de Usuario:</label>
                        <input type="text" name="usuario" id="usuario" class="form-input"
                            required placeholder="Ej: admin" autofocus
                            value="<?= htmlspecialchars($usuarioBuscado) ?>">
                    </div>
                    <button type="submit" class="btn-action">
                        <i data-lucide="search"></i>
                        <span>Continuar</span>
                    </button>
                </form>

            <?php elseif ($paso === 2): ?>
                <!-- Paso 2: Responder pregunta y nueva contraseña -->
                <form method="POST" action="recuperar_password.php">
                    <input type="hidden" name="accion" value="restablecer_password">
                    <input type="hidden" name="usuario" value="<?= htmlspecialchars($usuarioBuscado) ?>">
                    <input type="hidden" name="id_pregunta" value="<?= (int)($preguntaActual['id'] ?? 0) ?>">

                    <div class="form-group">
                        <label>Pregunta de Seguridad:</label>
                        <div class="pregunta-box">
                            <i data-lucide="help-circle" style="width:18px;height:18px;flex-shrink:0;margin-top:2px;"></i>
                            <span><?= htmlspecialchars($preguntaActual['pregunta'] ?? '¿Nombre de la institución?') ?></span>
                        </div>
                        <input type="text" name="respuesta" class="form-input" required
                            placeholder="Escriba su respuesta" autofocus autocomplete="off">
                        <div class="hint-box">
                            <i data-lucide="info" style="width:14px;height:14px;flex-shrink:0;margin-top:1px;"></i>
                            <span>La respuesta predeterminada es <strong>iuta</strong>. No distingue entre mayúsculas y minúsculas.</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="nueva_password">Nueva Contraseña:</label>
                        <input type="password" name="nueva_password" id="nueva_password" class="form-input"
                            required placeholder="Mínimo 6 caracteres">
                    </div>

                    <div class="form-group">
                        <label for="confirmar_password">Confirmar Nueva Contraseña:</label>
                        <input type="password" name="confirmar_password" id="confirmar_password" class="form-input"
                            required placeholder="Repita la nueva contraseña">
                    </div>

                    <button type="submit" class="btn-action">
                        <i data-lucide="key"></i>
                        <span>Cambiar Contraseña</span>
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($paso < 3): ?>
                <a href="login.php" class="link-back">
                    <i data-lucide="arrow-left" style="width:15px;height:15px;"></i>
                    <span>Volver al inicio de sesión</span>
                </a>
            <?php endif; ?>

        </main>
    </div>

    <script>lucide.createIcons();</script>
</body>
</html>
