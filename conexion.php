<?php
/**
 * ==============================================================================
 * Módulo de Conexión a Base de Datos - PDO (SQLite / MySQL)
 * Centro de Documentación e Información "Jesús Rosas Marcano" - IUTA
 * ==============================================================================
 */

define('DB_TYPE', 'sqlite'); // Opciones: 'sqlite' o 'mysql'
define('DB_FILE', __DIR__ . '/cdi_iuta.sqlite');
define('SQL_SCHEMA_FILE', __DIR__ . '/schema.sql');

// Configuración opcional para MySQL (si se decide migrar a MySQL en el futuro)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'cdi_iuta');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * Función para obtener la conexión PDO a la base de datos
 *
 * @return PDO Instancia de la conexión PDO
 * @throws PDOException Si ocurre un error durante la conexión
 */
function obtenerConexion() {
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    try {
        if (DB_TYPE === 'sqlite') {
            $dbExists = file_exists(DB_FILE);
            
            // Conexión mediante PDO SQLite
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Si el archivo no existía o está vacío, ejecutamos el script inicial schema.sql
            if (!$dbExists || filesize(DB_FILE) === 0) {
                inicializarBaseDeDatos($pdo);
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

        return $pdo;

    } catch (PDOException $e) {
        // En un entorno de producción se debería registrar en un log y no mostrar datos sensibles
        die("<strong>Error de conexión a la base de datos:</strong> " . htmlspecialchars($e->getMessage()));
    } catch (Exception $e) {
        die("<strong>Error de configuración:</strong> " . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Carga e inicializa la tabla y registros por defecto en la BD SQLite
 *
 * @param PDO $pdo Instancia de PDO activa
 */
function inicializarBaseDeDatos(PDO $pdo) {
    if (file_exists(SQL_SCHEMA_FILE)) {
        $sql = file_get_contents(SQL_SCHEMA_FILE);
        if ($sql !== false) {
            $pdo->exec($sql);
        }
    }
}

// Variable global $pdo disponible por comodidad al incluir conexion.php
$pdo = obtenerConexion();
