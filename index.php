<?php
/**
 * ==============================================================================
 * Portal Informativo - Intranet IUTA
 * Centro de Documentación e Información "Jesús Rosas Marcano"
 * ==============================================================================
 */

require_once __DIR__ . '/conexion.php';

// Consultar sólo las secciones HABILITADAS (activo = 1) para los usuarios finales
try {
    $stmt = $pdo->query("SELECT id, clave, titulo, contenido, fecha_actualizacion FROM secciones_informativas WHERE activo = 1 ORDER BY id ASC");
    $secciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $secciones = [];
    $error = "Error al conectar o consultar la base de datos: " . $e->getMessage();
}

// Obtener la clave seleccionada mediante GET (por defecto muestra 'todas')
$claveSeleccionada = isset($_GET['sec']) ? trim($_GET['sec']) : 'todas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CDI "Jesús Rosas Marcano" | Intranet IUTA</title>
    <meta name="description" content="Portal informativo del Centro de Documentación e Información Jesús Rosas Marcano del IUTA.">
    
    <!-- Fuentes locales (sin internet) -->
    <link rel="stylesheet" href="assets/css/fonts.css">

    <!-- Iconos Lucide (local) -->
    <script src="assets/js/lucide.min.js"></script>

    <style>
        :root {
            --azul-marino-head: #0b2545;
            --azul-marino-dark: #134074;
            --azul-marino-accent: #1d2d44;
            --azul-intenso: #0066cc;
            --azul-claro: #eef4f8;
            --texto-principal: #1e293b;
            --texto-secundario: #475569;
            --texto-suave: #64748b;
            --bg-pagina: #f8fafc;
            --bg-card: #ffffff;
            --borde-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(11, 37, 69, 0.05);
            --shadow-md: 0 4px 12px rgba(11, 37, 69, 0.08);
            --font-main: 'Inter', system-ui, -apple-system, sans-serif;
            --font-header: 'Merriweather', serif;
            --radius-main: 12px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-pagina);
            color: var(--texto-principal);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .top-bar {
            background-color: #07192e;
            color: #94a3b8;
            padding: 0.5rem 2rem;
            font-size: 0.82rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .top-bar-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-bar-left { display: flex; align-items: center; gap: 1rem; }

        .tag-intranet {
            background: rgba(0, 102, 204, 0.2);
            color: #38bdf8;
            padding: 0.2rem 0.6rem;
            border-radius: 4px;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .admin-link {
            color: #f1f5f9;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.3rem 0.8rem;
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.2s ease;
        }

        .admin-link:hover {
            background-color: var(--azul-intenso);
            color: #ffffff;
        }

        .header-formal {
            background: linear-gradient(135deg, var(--azul-marino-head) 0%, var(--azul-marino-dark) 100%);
            color: #ffffff;
            padding: 2.5rem 2rem;
            box-shadow: var(--shadow-md);
        }

        .header-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .header-logo {
            width: 72px;
            height: 72px;
            background: #ffffff;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--azul-marino-head);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            flex-shrink: 0;
        }

        .header-titles h1 {
            font-family: var(--font-header);
            font-size: 1.75rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.3rem;
        }

        .header-titles p { font-size: 0.95rem; color: #cbd5e1; }

        .nav-sections-wrapper {
            background-color: #ffffff;
            border-bottom: 1px solid var(--borde-color);
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: var(--shadow-sm);
        }

        .nav-sections {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            position: relative;
            padding: 0 0.5rem;
        }

        .nav-scroll-btn {
            background: #ffffff;
            border: 1px solid var(--borde-color);
            color: var(--azul-marino-head);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
            transition: all 0.2s ease;
            z-index: 10;
        }

        .nav-scroll-btn:hover {
            background: var(--azul-marino-head);
            color: #ffffff;
        }

        .nav-container {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            padding: 0 0.5rem;
            scrollbar-width: none;
            scroll-behavior: smooth;
            width: 100%;
        }

        .nav-container::-webkit-scrollbar { display: none; }

        .nav-tab {
            padding: 0.9rem 1.25rem;
            color: var(--texto-secundario);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-bottom: 3px solid transparent;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
            transition: all 0.2s ease;
            flex-shrink: 0;
        }

        .nav-tab:hover {
            color: var(--azul-marino-head);
            background-color: var(--azul-claro);
        }

        .nav-tab.active {
            color: var(--azul-marino-head);
            font-weight: 700;
            border-bottom-color: var(--azul-intenso);
            background-color: rgba(238, 244, 248, 0.6);
        }

        .main-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
            flex: 1;
            width: 100%;
        }

        .content-grid { display: flex; flex-direction: column; gap: 2rem; }

        .info-card {
            background: var(--bg-card);
            border: 1px solid var(--borde-color);
            border-radius: var(--radius-main);
            padding: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid var(--azul-claro);
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }

        .card-title-group { display: flex; align-items: center; gap: 0.75rem; }

        .card-icon-box {
            width: 40px; height: 40px;
            background-color: var(--azul-claro);
            color: var(--azul-marino-head);
            border-radius: 8px;
            display: flex; align-items: center; justify-content: center;
        }

        .card-title-group h2 {
            font-family: var(--font-header);
            font-size: 1.35rem;
            color: var(--azul-marino-head);
            font-weight: 700;
        }

        .date-badge {
            font-size: 0.78rem;
            color: var(--texto-suave);
            background-color: #f1f5f9;
            padding: 0.3rem 0.7rem;
            border-radius: 20px;
            display: flex; align-items: center; gap: 0.3rem;
        }

        .card-body-html {
            color: var(--texto-principal);
            font-size: 0.98rem;
            line-height: 1.7;
        }

        .card-body-html p { margin-bottom: 1rem; }
        .card-body-html strong { color: var(--azul-marino-head); }
        .card-body-html ul { margin: 1rem 0 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; }
        .card-body-html li { color: var(--texto-secundario); }

        .card-body-html img,
        .card-body-html video {
            max-width: 100%; height: auto;
            border-radius: 10px; margin: 1rem 0;
            box-shadow: 0 4px 12px rgba(11, 37, 69, 0.1);
            display: block;
        }

        .card-body-html video {
            background: #000;
            width: 100%;
        }

        .sedes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-top: 1.25rem;
        }

        .sede-card {
            background-color: var(--azul-claro);
            border: 1px solid #dce7f1;
            border-radius: 10px;
            padding: 1.25rem;
        }

        .sede-card h3 { font-size: 1.05rem; color: var(--azul-marino-head); margin-bottom: 0.5rem; font-weight: 700; }
        .sede-card p { font-size: 0.88rem; color: var(--texto-secundario); margin-bottom: 0.4rem; }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            display: flex; align-items: center; gap: 0.75rem;
        }

        .footer-formal {
            background-color: var(--azul-marino-head);
            color: #cbd5e1;
            padding: 2rem 1.5rem;
            margin-top: auto;
            border-top: 3px solid var(--azul-intenso);
        }

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            font-size: 0.88rem;
        }

        .footer-left p { margin-bottom: 0.25rem; }

        .footer-nav { display: flex; gap: 1rem; align-items: center; }
        .footer-nav a { color: #94a3b8; text-decoration: none; }
        .footer-nav a:hover { color: #ffffff; }

        @media (max-width: 768px) {
            .header-container { flex-direction: column; text-align: center; }
            .card-header { flex-direction: column; align-items: flex-start; gap: 0.5rem; }
            .footer-container { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

    <div class="top-bar">
        <div class="top-bar-container">
            <div class="top-bar-left">
                <span class="tag-intranet">Intranet Oficial</span>
                <span>Instituto Universitario de Tecnología de Administración Industrial</span>
            </div>
            <div>
                <a href="admin.php" class="admin-link" title="Acceso exclusivo para administradores">
                    <i data-lucide="lock" style="width: 14px; height: 14px;"></i>
                    <span>Acceso Administrativo</span>
                </a>
            </div>
        </div>
    </div>

    <header class="header-formal">
        <div class="header-container">
            <div class="header-logo">
                <i data-lucide="book-open" style="width: 38px; height: 38px;"></i>
            </div>
            <div class="header-titles">
                <h1>Centro de Documentación e Información "Jesús Rosas Marcano"</h1>
                <p>Red de Bibliotecas y Gestión del Conocimiento Académico - IUTA</p>
            </div>
        </div>
    </header>

    <div class="nav-sections-wrapper">
        <nav class="nav-sections">
            <button class="nav-scroll-btn" id="scrollLeft" title="Desplazar a la izquierda">
                <i data-lucide="chevron-left" style="width: 20px; height: 20px;"></i>
            </button>

            <div class="nav-container" id="navContainer">
                <a href="index.php?sec=todas" class="nav-tab <?= $claveSeleccionada === 'todas' ? 'active' : '' ?>">
                    <i data-lucide="layers" style="width: 16px; height: 16px;"></i>
                    <span>Ver Todo</span>
                </a>
                <?php 
                $iconosMapa = [
                    'identidad' => 'info',
                    'mision' => 'target',
                    'vision' => 'compass',
                    'valores' => 'shield-check',
                    'sedes' => 'map-pin'
                ];

                foreach ($secciones as $sec): 
                    $icono = isset($iconosMapa[$sec['clave']]) ? $iconosMapa[$sec['clave']] : 'file-text';
                    $esActivo = ($claveSeleccionada === $sec['clave']);
                ?>
                    <a href="index.php?sec=<?= urlencode($sec['clave']) ?>" class="nav-tab <?= $esActivo ? 'active' : '' ?>">
                        <i data-lucide="<?= $icono ?>" style="width: 16px; height: 16px;"></i>
                        <span><?= htmlspecialchars($sec['titulo']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <button class="nav-scroll-btn" id="scrollRight" title="Desplazar a la derecha">
                <i data-lucide="chevron-right" style="width: 20px; height: 20px;"></i>
            </button>
        </nav>
    </div>

    <main class="main-container">

        <?php if (isset($error)): ?>
            <div class="alert-error">
                <i data-lucide="alert-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="content-grid">
            <?php 
            $seccionesAMostrar = [];

            if ($claveSeleccionada === 'todas') {
                $seccionesAMostrar = $secciones;
            } else {
                foreach ($secciones as $sec) {
                    if ($sec['clave'] === $claveSeleccionada) {
                        $seccionesAMostrar[] = $sec;
                        break;
                    }
                }
            }

            if (empty($seccionesAMostrar)):
            ?>
                <div class="info-card" style="text-align: center; padding: 3rem;">
                    <i data-lucide="file-question" style="width: 48px; height: 48px; color: var(--texto-suave); margin-bottom: 1rem;"></i>
                    <p style="color: var(--texto-secundario);">No se encontró información activa para la sección seleccionada.</p>
                </div>
            <?php 
            else:
                foreach ($seccionesAMostrar as $sec):
                    $icono = isset($iconosMapa[$sec['clave']]) ? $iconosMapa[$sec['clave']] : 'bookmark';
            ?>
                <article class="info-card" id="sec-<?= htmlspecialchars($sec['clave']) ?>">
                    <header class="card-header">
                        <div class="card-title-group">
                            <div class="card-icon-box">
                                <i data-lucide="<?= $icono ?>" style="width: 22px; height: 22px;"></i>
                            </div>
                            <h2><?= htmlspecialchars($sec['titulo']) ?></h2>
                        </div>
                        <div class="date-badge" title="Última fecha de actualización">
                            <i data-lucide="calendar" style="width: 13px; height: 13px;"></i>
                            <span><?= date('d/m/Y', strtotime($sec['fecha_actualizacion'])) ?></span>
                        </div>
                    </header>
                    
                    <div class="card-body-html">
                        <?= $sec['contenido'] ?>
                    </div>
                </article>
            <?php 
                endforeach;
            endif; 
            ?>
        </div>
    </main>

    <footer class="footer-formal">
        <div class="footer-container">
            <div class="footer-left">
                <p><strong>Centro de Documentación e Información "Jesús Rosas Marcano"</strong></p>
                <p>Instituto Universitario de Tecnología de Administración Industrial (IUTA)</p>
            </div>
            <div class="footer-nav">
                <span>&copy; <?= date('Y') ?> Intranet IUTA</span>
                <span>•</span>
                <a href="admin.php">Panel de Control (Admin)</a>
            </div>
        </div>
    </footer>

    <script>
        lucide.createIcons();

        const navContainer = document.getElementById('navContainer');
        const btnLeft = document.getElementById('scrollLeft');
        const btnRight = document.getElementById('scrollRight');

        if (navContainer && btnLeft && btnRight) {
            btnLeft.addEventListener('click', () => {
                navContainer.scrollBy({ left: -250, behavior: 'smooth' });
            });

            btnRight.addEventListener('click', () => {
                navContainer.scrollBy({ left: 250, behavior: 'smooth' });
            });
        }
    </script>
</body>
</html>
