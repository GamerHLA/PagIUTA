<?php
/**
 * ==============================================================================
 * Módulo de Conexión a Base de Datos - PDO (SQLite / MySQL)
 * Centro de Documentación e Información "Jesús Rosas Marcano" - IUTA
 * ==============================================================================
 */

define('DB_TYPE', 'sqlite');
define('DB_FILE', __DIR__ . '/cdi_iuta.sqlite');
define('SQL_SCHEMA_FILE', __DIR__ . '/schema.sql');

// Configuración MySQL (sólo si DB_TYPE = 'mysql')
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'cdi_iuta');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna la conexión PDO activa (singleton)
 */
function obtenerConexion() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_TYPE === 'sqlite') {
            $dbExists = file_exists(DB_FILE);

            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            if (!$dbExists || filesize(DB_FILE) === 0) {
                inicializarBaseDeDatos($pdo);
            } else {
                $stmtCheck = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='usuarios'");
                if (!$stmtCheck->fetch()) {
                    inicializarBaseDeDatos($pdo);
                }
            }

        } elseif (DB_TYPE === 'mysql') {
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } else {
            throw new Exception("Tipo de base de datos no soportado: " . DB_TYPE);
        }

        // Migraciones y verificaciones post-conexión
        aplicarMigraciones($pdo);
        asegurarUsuarioAdminDefault($pdo);
        migrarPreguntaLegacy($pdo);

        return $pdo;

    } catch (PDOException $e) {
        die("<strong>Error de conexión a la base de datos:</strong> " . htmlspecialchars($e->getMessage()));
    } catch (Exception $e) {
        die("<strong>Error de configuración:</strong> " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Carga el schema SQL inicial
 */
function inicializarBaseDeDatos(PDO $pdo) {
    if (file_exists(SQL_SCHEMA_FILE)) {
        $sql = file_get_contents(SQL_SCHEMA_FILE);
        if ($sql !== false) {
            $pdo->exec($sql);
        }
    }
}

/**
 * Aplica migraciones necesarias en la BD existente
 */
function aplicarMigraciones(PDO $pdo) {
    // Migración 1: columna activo en secciones_informativas
    try {
        $pdo->exec("ALTER TABLE secciones_informativas ADD COLUMN activo INTEGER DEFAULT 1");
    } catch (PDOException $e) {
        // Ya existe, ignorar
    }

    // Migración 2: tabla preguntas_seguridad
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS preguntas_seguridad (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                usuario_id INTEGER NOT NULL,
                pregunta VARCHAR(255) NOT NULL,
                respuesta_hash VARCHAR(255) NOT NULL,
                orden INTEGER DEFAULT 0,
                FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
            )
        ");
        $pdo->exec("CREATE INDEX IF NOT EXISTS idx_preguntas_usuario ON preguntas_seguridad(usuario_id)");
    } catch (PDOException $e) {
        // Ya existe, ignorar
    }
}

/**
 * Migra la pregunta/respuesta legacy (campo único) a la tabla preguntas_seguridad
 * Solo se ejecuta si el usuario no tiene preguntas en la nueva tabla aún
 */
function migrarPreguntaLegacy(PDO $pdo) {
    try {
        // Para cada usuario que tenga pregunta legacy y no tenga preguntas en la nueva tabla
        $stmt = $pdo->query("
            SELECT u.id, u.pregunta_recuperacion, u.respuesta_hash
            FROM usuarios u
            LEFT JOIN preguntas_seguridad ps ON ps.usuario_id = u.id
            WHERE ps.id IS NULL
              AND u.pregunta_recuperacion IS NOT NULL
              AND u.respuesta_hash IS NOT NULL
              AND u.respuesta_hash != ''
        ");
        $pendientes = $stmt->fetchAll();

        foreach ($pendientes as $u) {
            $stmtIns = $pdo->prepare("
                INSERT INTO preguntas_seguridad (usuario_id, pregunta, respuesta_hash, orden)
                VALUES (:uid, :preg, :resp, 1)
            ");
            $stmtIns->execute([
                ':uid'  => $u['id'],
                ':preg' => $u['pregunta_recuperacion'] ?: '¿Nombre de la institución?',
                ':resp' => $u['respuesta_hash']
            ]);
        }
    } catch (Exception $e) {
        // Ignorar errores de migración legacy
    }
}

/**
 * Inserta el usuario admin por defecto si la tabla usuarios está vacía
 */
function asegurarUsuarioAdminDefault(PDO $pdo) {
    try {
        $stmtCount = $pdo->query("SELECT COUNT(*) AS total FROM usuarios");
        $row = $stmtCount->fetch();
        if ($row && (int)$row['total'] === 0) {
            $passHash = password_hash('iuta2026', PASSWORD_DEFAULT);
            $respHash = password_hash('iuta', PASSWORD_DEFAULT);

            $stmtInsert = $pdo->prepare("
                INSERT INTO usuarios (usuario, password_hash, pregunta_recuperacion, respuesta_hash)
                VALUES ('admin', :pass, '¿Nombre de la institución?', :resp)
            ");
            $stmtInsert->execute([
                ':pass' => $passHash,
                ':resp' => $respHash
            ]);
        }
    } catch (Exception $e) {
        // La tabla puede estar creándose aún
    }
}

// Conexión global disponible como $pdo
$pdo = obtenerConexion();
