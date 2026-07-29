# Manual Técnico - PagIUTA

## 1. Objetivo del proyecto

PagIUTA es una aplicación web sencilla desarrollada en PHP para gestionar un portal informativo del Centro de Documentación e Información “Jesús Rosas Marcano” del IUTA. El sistema permite:

- Mostrar secciones informativas al público en una intranet.
- Editar contenido desde un panel administrativo.
- Crear, habilitar o inhabilitar secciones.
- Subir imágenes y videos para usarlos dentro del contenido.
- Gestionar autenticación de administradores y recuperación de contraseñas.

Este documento está pensado para futuros programadores que necesiten comprender la estructura actual del proyecto y realizar mejoras sin romper el funcionamiento existente.

---

## 2. Tecnologías utilizadas

- PHP 7+ (con PDO)
- SQLite por defecto
- MySQL configurable (código preparado, aunque no activado por defecto)
- HTML5 + CSS + JavaScript
- TinyMCE local para edición de contenido enriquecido
- Lucide Icons local para iconografía

### Dependencias locales

La aplicación no depende de paquetes externos para funcionar en el estado actual. Los recursos visuales y editores están incluidos en la carpeta assets/.

---

## 3. Estructura del proyecto

- index.php: portal público que muestra las secciones activas.
- admin.php: panel administrativo para gestionar contenido, seguridad y media.
- login.php: formulario de acceso para administradores.
- recuperar_password.php: flujo de recuperación de contraseña.
- conexion.php: inicializa la conexión a la base de datos y aplica migraciones.
- schema.sql: esquema inicial de la base de datos.
- upload_imagen.php: recibe y guarda imágenes/videos desde el panel.
- eliminar_imagen.php: elimina físicamente archivos de uploads/.
- assets/: archivos estáticos (CSS, JS, TinyMCE, tipografías, iconos).
- uploads/: carpeta donde se almacenan los archivos subidos.
- cdi_iuta.sqlite: base de datos SQLite generada automáticamente al ejecutar el sistema por primera vez.

---

## 4. Arquitectura general

El sistema sigue un patrón muy simple:

1. El usuario accede a una página PHP.
2. El archivo PHP incluye conexion.php.
3. conexion.php abre la conexión a la base de datos mediante PDO.
4. Si la base de datos no existe, se crea usando schema.sql.
5. El contenido se lee desde la tabla secciones_informativas y se renderiza en la interfaz.

### Flujo de ejecución típico

- Portal público: index.php -> consulta las secciones con activo = 1.
- Panel administrativo: admin.php -> valida sesión y permite editar el contenido.
- Login: login.php -> valida credenciales y crea la variable de sesión admin.
- Recuperación: recuperar_password.php -> valida usuario y pregunta de seguridad.

---

## 5. Base de datos

La base de datos se gestiona con PDO y, por defecto, SQLite. El archivo principal de configuración está en conexion.php.

### Constantes relevantes

- DB_TYPE: por defecto sqlite.
- DB_FILE: apunta a cdi_iuta.sqlite.
- SQL_SCHEMA_FILE: apunta a schema.sql.

### Tablas principales

#### 5.1. secciones_informativas

Almacena las secciones que se muestran en la intranet.

Campos:

- id: identificador único.
- clave: identificador corto y único para la sección.
- titulo: título visible.
- contenido: contenido HTML editable.
- activo: define si la sección está visible (1) o no (0).
- fecha_actualizacion: fecha de última modificación.

Uso importante:

- Solo las secciones con activo = 1 se muestran en index.php.
- El panel de administración puede editar, habilitar, inhabilitar y eliminar secciones.

#### 5.2. usuarios

Guarda los usuarios administradores del sistema.

Campos relevantes:

- id
- usuario
- password_hash
- pregunta_recuperacion (compatibilidad legacy)
- respuesta_hash
- fecha_creacion

#### 5.3. preguntas_seguridad

Tabla más moderna para almacenar varias preguntas de recuperación por usuario.

Campos:

- id
- usuario_id
- pregunta
- respuesta_hash
- orden

### Inicialización automática

Al ejecutar la aplicación por primera vez:

- Si no existe la base de datos, se crea.
- Si no existe la tabla usuarios, se carga schema.sql.
- Se aplican migraciones ligeras desde conexion.php.
- Se inserta el usuario administrador predeterminado si la tabla está vacía.

---

## 6. Módulos principales y su funcionamiento

### 6.1. conexion.php

Es el corazón del sistema.

Funciones principales:

- obtenerConexion(): crea o reutiliza la conexión PDO.
- inicializarBaseDeDatos(): ejecuta schema.sql cuando el archivo de base de datos no existe.
- aplicarMigraciones(): agrega columnas o tablas si faltan.
- migrarPreguntaLegacy(): mueve preguntas antiguas desde usuarios a preguntas_seguridad.
- asegurarUsuarioAdminDefault(): crea el usuario admin por defecto si no existen usuarios.

Punto importante:

- El archivo define la conexión una sola vez y la deja disponible en la variable global $pdo.

### 6.2. index.php

Es la vista pública del sistema.

Qué hace:

- Carga las secciones activas desde la base de datos.
- Renderiza el contenido HTML dentro de tarjetas visuales.
- Permite navegar entre secciones mediante una barra de navegación horizontal.

### 6.3. login.php

Maneja la autenticación del administrador.

Comportamiento actual:

- Valida usuario y contraseña.
- Si es correcto, crea la sesión admin y redirige a admin.php.
- Si no, muestra un error.

Nota de seguridad:

- Hay un fallback para la contraseña predeterminada admin/iuta2026. Esto es útil para desarrollo, pero debe revisarse antes de producción.

### 6.4. recuperar_password.php

Implementa el proceso de recuperación de contraseña.

Flujo actual:

1. Se ingresa el nombre de usuario.
2. Se obtiene una pregunta de seguridad aleatoria.
3. El usuario responde.
4. Si la respuesta es correcta, se cambia la contraseña.

Adicionalmente, el sistema acepta una clave maestra de recuperación institucional:

- IUTA-RESET-2026
- o la respuesta iuta (sin importar mayúsculas/minúsculas)

Esto es un mecanismo práctico, pero también debe considerarse una debilidad de seguridad si se usa en producción.

### 6.5. admin.php

Es el panel de administración central.

Permite:

- Editar contenido de una sección.
- Crear nuevas secciones.
- Habilitar o inhabilitar secciones.
- Eliminar secciones solo si están inhabilitadas.
- Cambiar la contraseña del usuario actual.
- Añadir y eliminar preguntas de seguridad.
- Revisar archivos subidos al sistema.

El panel depende fuertemente de POST y de la sesión admin.

### 6.6. upload_imagen.php

Maneja la subida de archivos.

Requisitos actuales:

- Solo permite imágenes y videos.
- Imágenes admitidas: JPG, JPEG, PNG, GIF, WEBP.
- Videos admitidos: MP4, WEBM, OGG, MOV.
- Tamaño máximo: 5 MB para imágenes y 100 MB para videos.

Proceso:

- Valida sesión admin.
- Verifica tipo y tamaño del archivo.
- Genera un nombre único.
- Guarda el archivo en uploads/.
- Devuelve una respuesta JSON compatible con TinyMCE.

### 6.7. eliminar_imagen.php

Elimina físicamente un archivo del servidor desde uploads/.

Uso importante:

- Solo elimina el archivo si existe y es un archivo real.
- Usa basename() para evitar acciones de traversal de rutas.

---

## 7. Gestión de contenido

El contenido publicado no está almacenado en archivos estáticos. Se guarda en la base de datos, específicamente en la columna contenido de secciones_informativas.

Esto implica que:

- El contenido puede editarse desde el panel administrativo.
- Cada cambio queda persistido en la base de datos.
- La interfaz pública lo recupera automáticamente.

### Recomendación para modificar texto o estructura

Si se desea cambiar la información visible en la intranet, lo más adecuado es editar una sección desde admin.php o directamente la base de datos si se necesita una modificación masiva.

---

## 8. Estilos y presentación

La interfaz actual tiene estilos incrustados directamente en los archivos PHP:

- index.php contiene estilos para la vista pública.
- admin.php contiene estilos para el panel administrativo.
- login.php y recuperar_password.php contienen sus propios estilos.

Esto facilita la modificación rápida, pero no es ideal para mantenimiento a largo plazo.

### Recomendación

Para futuras mejoras, conviene mover los estilos a archivos CSS separados, por ejemplo:

- assets/css/public.css
- assets/css/admin.css
- assets/css/auth.css

---

## 9. Seguridad actual y puntos a mejorar

El sistema funciona, pero tiene varias áreas donde conviene reforzar la seguridad antes de usarlo en producción real.

### Riesgos actuales

- Credenciales por defecto visibles en el código y en README.
- Fallback de login para admin/iuta2026.
- Clave maestra de recuperación en recuperar_password.php.
- No hay protección CSRF explícita para formularios.
- No hay limitación de intentos de login.
- No hay control de roles más allá de admin.
- El contenido HTML permite insertar markup arbitrario desde el editor.

### Recomendaciones inmediatas

- Cambiar la contraseña predeterminada del administrador.
- Eliminar el fallback de login por defecto.
- Quitar o limitar la clave maestra de recuperación.
- Implementar tokens CSRF.
- Añadir protección contra fuerza bruta.
- Sanear y limitar el HTML permitido en TinyMCE.
- Mover secretos y configuraciones a variables de entorno.

---

## 10. Requisitos de ejecución

### Entorno recomendado

- Servidor web con PHP.
- Extensión PDO activa.
- Extensión SQLite (o MySQL si se activa esa opción).
- Permisos de escritura en la carpeta uploads/ y en el archivo de base de datos.

### Permisos recomendados

- uploads/: 755 o 775 dependiendo del servidor.
- cdi_iuta.sqlite: escritura habilitada para el usuario del servidor web.

### Verificación rápida

Si el sistema no carga correctamente, revisar:

- Que PHP tenga PDO habilitado.
- Que el archivo cdi_iuta.sqlite sea escribible.
- Que existe la carpeta uploads/.
- Que el servidor pueda ejecutar archivos PHP.

---

## 11. Cómo modificar o extender el sistema

### 11.1. Agregar una nueva sección informativa

La forma más sencilla es desde el panel administrativo:

- Iniciar sesión como administrador.
- Crear una nueva sección desde admin.php.
- Asignar título y clave corta.
- Guardar y habilitarla.

### 11.2. Modificar el diseño

- Editar los bloques de estilo dentro de los archivos PHP, o mejor aún, crear archivos CSS separados.
- Cambiar colores, tipografías y distribución desde los estilos inline.

### 11.3. Añadir nuevas funcionalidades

Por ejemplo:

- Añadir un nuevo tipo de contenido (galería, noticias, documentos, enlaces).
- Crear una tabla nueva en schema.sql y gestionar sus CRUD desde admin.php.
- Extender la lógica de autenticación con roles y permisos.

### 11.4. Cambiar la base de datos

Si se desea usar MySQL en lugar de SQLite:

- Editar conexion.php.
- Cambiar DB_TYPE a mysql.
- Ajustar DB_HOST, DB_NAME, DB_USER y DB_PASS.
- Asegurar que la base de datos destino exista.

---

## 12. Problemas frecuentes y solución rápida

### Error al conectar a la base de datos

Posibles causas:

- El archivo cdi_iuta.sqlite no existe o no es escribible.
- La extensión SQLite no está habilitada.
- La configuración de MySQL es incorrecta.

### No aparecen secciones en la intranet

Posibles causas:

- La sección está inactiva (activo = 0).
- La consulta a la base de datos falló.
- No existe ninguna sección cargada.

### No se suben imágenes o videos

Posibles causas:

- La carpeta uploads/ no existe o no tiene permisos.
- El archivo supera el tamaño máximo.
- El tipo de archivo no está permitido.

### No se puede iniciar sesión

Posibles causas:

- La contraseña no coincide.
- La base de datos no se inicializó correctamente.
- Se está usando la contraseña predeterminada y no se ha cambiado.

---

## 13. Recomendaciones para futuras mejoras

Se recomienda priorizar estas mejoras en el orden siguiente:

1. Separar estilos en archivos CSS externos.
2. Reemplazar la autenticación actual por una solución más robusta.
3. Implementar protección CSRF en formularios.
4. Añadir control de roles y permisos.
5. Mejorar la validación y sanitización del contenido HTML.
6. Mover las configuraciones a variables de entorno.
7. Añadir logs de auditoría para cambios administrativos.
8. Preparar la aplicación para despliegue en producción con HTTPS.

---

## 14. Resumen ejecutivo

El proyecto está bien para un sistema pequeño o mediano de contenido institucional. Su mayor fortaleza es que la edición de contenido está centralizada en una base de datos y no depende de múltiples archivos estáticos. Su mayor limitación es que la lógica de seguridad y la estructura visual están todavía muy acopladas a los archivos PHP.

Para futuros programadores, la recomendación principal es mantener el enfoque actual de base de datos y mejorar la arquitectura poco a poco, sin perder compatibilidad con el funcionamiento ya existente.
