-- ==============================================================================
-- Script de Base de Datos SQL / SQLite
-- Centro de Documentación e Información "Jesús Rosas Marcano" - IUTA
-- ==============================================================================

-- 1. Tabla: secciones_informativas
CREATE TABLE IF NOT EXISTS secciones_informativas (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    clave VARCHAR(50) NOT NULL UNIQUE,
    titulo VARCHAR(150) NOT NULL,
    contenido TEXT NOT NULL,
    activo INTEGER DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_secciones_clave ON secciones_informativas(clave);

-- 2. Tabla: usuarios (Gestión de Autenticación)
CREATE TABLE IF NOT EXISTS usuarios (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    -- Mantenido por compatibilidad con versiones anteriores; usar tabla preguntas_seguridad
    pregunta_recuperacion VARCHAR(255) DEFAULT '¿Nombre de la institución?',
    respuesta_hash VARCHAR(255) NOT NULL,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Tabla: preguntas_seguridad (Soporte de múltiples preguntas por usuario)
CREATE TABLE IF NOT EXISTS preguntas_seguridad (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL,
    pregunta VARCHAR(255) NOT NULL,
    respuesta_hash VARCHAR(255) NOT NULL,
    orden INTEGER DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_preguntas_usuario ON preguntas_seguridad(usuario_id);

-- ==============================================================================
-- Inserción de Registros Iniciales por Defecto
-- ==============================================================================

-- 1. Identidad Institucional
INSERT OR IGNORE INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (
    'identidad',
    'Centro de Documentación e Información "Jesús Rosas Marcano"',
    '<div class="cdi-identidad">
        <p>El <strong>Centro de Documentación e Información "Jesús Rosas Marcano" (CDI)</strong> del Instituto Universitario de Tecnología de Administración Industrial (IUTA) es una unidad estratégica orientada a la gestión, preservación y difusión del conocimiento académico, científico y tecnológico de nuestra comunidad universitaria.</p>
        <p>En honor al ilustre docente, periodista y poeta venezolano <em>Jesús Rosas Marcano</em>, nuestro centro reafirma su compromiso con la formación integral de los estudiantes, facilitando recursos bibliográficos, digitales y de investigación de alta calidad.</p>
    </div>',
    1,
    CURRENT_TIMESTAMP
);

-- 2. Misión
INSERT OR IGNORE INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (
    'mision',
    'Misión Institucional',
    '<div class="cdi-mision">
        <p>Proporcionar servicios de información documental oportunos, actualizados y eficientes a la comunidad académica e investigadora del IUTA, apoyando activamente los procesos de docencia, investigación y extensión mediante el uso de recursos bibliográficos físicos y digitales de vanguardia.</p>
    </div>',
    1,
    CURRENT_TIMESTAMP
);

-- 3. Visión
INSERT OR IGNORE INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (
    'vision',
    'Visión Institucional',
    '<div class="cdi-vision">
        <p>Ser reconocido a nivel nacional como un centro de documentación e información universitario de excelencia, líder en la gestión del conocimiento tecnológico y administrativo, caracterizado por la innovación en sus servicios digitales y la accesibilidad para toda la comunidad iutense.</p>
    </div>',
    1,
    CURRENT_TIMESTAMP
);

-- 4. Valores
INSERT OR IGNORE INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (
    'valores',
    'Valores Institucionales',
    '<div class="cdi-valores">
        <ul>
            <li><strong>Excelencia:</strong> Calidad técnica y humana en la atención y gestión de la información.</li>
            <li><strong>Responsabilidad Social:</strong> Facilitar el acceso equitativo al conocimiento académico.</li>
            <li><strong>Innovación:</strong> Integración continua de nuevas tecnologías de información y comunicación.</li>
            <li><strong>Compromiso Ético:</strong> Fomento de la integridad en la investigación académica y respeto al derecho de autor.</li>
            <li><strong>Trabajo en Equipo:</strong> Cooperación y servicio enfocado en las necesidades de la comunidad estudiantil.</li>
        </ul>
    </div>',
    1,
    CURRENT_TIMESTAMP
);

-- 5. Sedes (Central, Baralt, Jesuitas, Guarenas y Altos Mirandinos)
INSERT OR IGNORE INTO secciones_informativas (clave, titulo, contenido, activo, fecha_actualizacion) VALUES (
    'sedes',
    'Sedes del CDI IUTA',
    '<div class="cdi-sedes">
        <p>El Centro de Documentación e Información "Jesús Rosas Marcano" cuenta con atención y salas de consulta en las distintas sedes del IUTA:</p>
        <div class="sedes-grid">
            <div class="sede-card">
                <h3>Sede Central (Caracas)</h3>
                <p><strong>Ubicación:</strong> Av. Roosevelt, Los Rosales, Caracas.</p>
                <p><strong>Servicios:</strong> Sala de lectura principal, catálogo general, préstamos circulantes y área de cómputo.</p>
            </div>
            <div class="sede-card">
                <h3>Sede Baralt</h3>
                <p><strong>Ubicación:</strong> Av. Baralt, Centro de Caracas.</p>
                <p><strong>Servicios:</strong> Consulta de prensa, área de tesis de grado e investigación rápida.</p>
            </div>
            <div class="sede-card">
                <h3>Sede Jesuitas</h3>
                <p><strong>Ubicación:</strong> Esquina de Jesuitas a Tienda Honda, Caracas.</p>
                <p><strong>Servicios:</strong> Colección especializada en administración, informática y ciencias sociales.</p>
            </div>
            <div class="sede-card">
                <h3>Sede Guarenas</h3>
                <p><strong>Ubicación:</strong> Sector Nueva Casarapa / Zona Industrial, Guarenas, Edo. Miranda.</p>
                <p><strong>Servicios:</strong> Biblioteca técnica, terminales de consulta digital y asesoría documental.</p>
            </div>
            <div class="sede-card">
                <h3>Sede Altos Mirandinos (San Antonio)</h3>
                <p><strong>Ubicación:</strong> San Antonio de los Altos, Edo. Miranda.</p>
                <p><strong>Servicios:</strong> Sala interactiva de estudio grupal, repositorio de trabajos de grado y apoyo de investigación.</p>
            </div>
        </div>
    </div>',
    1,
    CURRENT_TIMESTAMP
);
