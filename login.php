<?php
/**
 * ==============================================================================
 * Sistema de Autenticación - Login de Administración
 * Centro de Documentación e Información "Jesús Rosas Marcano" - IUTA
 * ==============================================================================
 */

session_start();

// Si el usuario ya está autenticado, redirigir directamente al panel admin
if (isset($_SESSION['admin']) && $_SESSION['admin'] === true) {
    header('Location: admin.php');
    exit;
}

// Definición de credenciales administrativas por defecto
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'iuta2026'); // Puede cambiarse según las necesidades de la institución

$error = '';

// Procesar el formulario cuando se envía por POST
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $usuario = isset($_POST['usuario']) ? trim($_POST['usuario']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($usuario === ADMIN_USER && $password === ADMIN_PASS) {
        // Credenciales correctas: Iniciar sesión y redirigir a admin.php
        $_SESSION['admin'] = true;
        $_SESSION['usuario'] = $usuario;
        header('Location: admin.php');
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos. Por favor intente nuevamente.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | CDI IUTA Intranet</title>
    <meta name="description" content="Acceso al Panel Administrativo del CDI Jesús Rosas Marcano">
    
    <!-- Fuentes Google -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Merriweather:wght@400;700&display=swap" rel="stylesheet">
    
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
            background-image: 
                radial-gradient(at 0% 0%, rgba(11, 37, 69, 0.08) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(0, 102, 204, 0.06) 0px, transparent 50%);
            padding: 1.5rem;
        }

        .login-card {
            background-color: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: 16px;
            box-shadow: 0 10px 25px rgba(11, 37, 69, 0.08);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #ffffff;
            padding: 2rem 1.5rem;
            text-align: center;
        }

        .login-header-icon {
            width: 52px;
            height: 52px;
            background-color: rgba(255, 255, 255, 0.15);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem auto;
            color: #ffffff;
        }

        .login-header h1 {
            font-family: var(--font-header);
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0.3rem;
        }

        .login-header p {
            font-size: 0.85rem;
            color: #cbd5e1;
        }

        .login-body {
            padding: 2rem 1.75rem;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: var(--rojo-error);
            padding: 0.85rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.25rem;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            font-size: 0.88rem;
            font-weight: 600;
            color: var(--azul-marino-head);
            margin-bottom: 0.4rem;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 0.85rem;
            color: #94a3b8;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.6rem;
            border: 1px solid var(--borde-color);
            border-radius: 8px;
            font-family: var(--font-main);
            font-size: 0.95rem;
            color: var(--texto-principal);
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--azul-intenso);
            box-shadow: 0 0 0 3px rgba(0, 102, 204, 0.15);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--azul-marino-head);
            color: #ffffff;
            border: none;
            padding: 0.85rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: background 0.2s;
            margin-top: 1.5rem;
        }

        .btn-submit:hover {
            background-color: var(--azul-marino-dark);
        }

        .login-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.25rem;
            border-top: 1px solid var(--borde-color);
            font-size: 0.85rem;
        }

        .link-volver {
            color: var(--texto-secundario);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            transition: color 0.2s;
        }

        .link-volver:hover {
            color: var(--azul-intenso);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <header class="login-header">
            <div class="login-header-icon">
                <i data-lucide="shield-lock" style="width: 28px; height: 28px;"></i>
            </div>
            <h1>Acceso Administrativo</h1>
            <p>CDI "Jesús Rosas Marcano" - IUTA</p>
        </header>

        <main class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert-error">
                    <i data-lucide="alert-circle" style="width: 18px; height: 18px; flex-shrink: 0;"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <div class="form-group">
                    <label for="usuario">Usuario:</label>
                    <div class="input-wrapper">
                        <i data-lucide="user" class="input-icon" style="width: 18px; height: 18px;"></i>
                        <input type="text" name="usuario" id="usuario" class="form-input" required placeholder="Nombre de usuario" autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Contraseña:</label>
                    <div class="input-wrapper">
                        <i data-lucide="key-round" class="input-icon" style="width: 18px; height: 18px;"></i>
                        <input type="password" name="password" id="password" class="form-input" required placeholder="Contraseña de acceso">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i data-lucide="log-in" style="width: 18px; height: 18px;"></i>
                    <span>Iniciar Sesión</span>
                </button>
            </form>

            <div class="login-footer">
                <a href="index.php" class="link-volver">
                    <i data-lucide="arrow-left" style="width: 15px; height: 15px;"></i>
                    <span>Volver al Portal Informativo (index.php)</span>
                </a>
            </div>
        </main>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
