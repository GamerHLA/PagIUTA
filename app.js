// Esperar a que el HTML de la página esté completamente cargado por el navegador
document.addEventListener("DOMContentLoaded", () => {
    
    const contenedorVideos = document.getElementById("videos-dinamicos");
    const contenedorImagenes = document.getElementById("galeria-dinamica");
    
    // Si estamos en la página de multimedia (donde existen estos contenedores), ejecutamos la carga
    if (contenedorVideos && contenedorImagenes) {
        cargarRepositorioMultimedia(contenedorVideos, contenedorImagenes);
    }
});

/**
 * Petición asíncrona para leer el archivo de base de datos local (JSON)
 * y dibujar las tarjetas dinámicas en la interfaz del estudiante.
 */
function cargarRepositorioMultimedia(contenedorVideos, contenedorImagenes) {
    const urlDatos = "./data/multimedia.json";

    fetch(urlDatos)
        .then(respuesta => {
            if (!respuesta.ok) {
                throw new Error("No se pudo conectar con la base de datos de contenido.");
            }
            return respuesta.json();
        })
        .then(datos => {
            // Limpiar los mensajes de "Cargando..."
            contenedorVideos.innerHTML = "";
            contenedorImagenes.innerHTML = "";

            const items = Array.isArray(datos)
                ? datos
                : (datos.multimedia_items || []);

            // Separar y renderizar el contenido según su tipo (video o imagen)
            items.forEach(item => {
                if (item.tipo === "video") {
                    // Fabricar tarjeta de video responsiva
                    const tarjetaVideo = document.createElement("div");
                    tarjetaVideo.className = "tarjeta-video";
                    tarjetaVideo.innerHTML = `
                        <div class="video-responsivo">
                            <iframe src="${item.url}" title="${item.titulo}" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy"></iframe>
                        </div>
                        <div class="info-video">
                            <h4>${item.titulo}</h4>
                            <p>${item.descripcion}</p>
                        </div>
                    `;
                    contenedorVideos.appendChild(tarjetaVideo);

                } else if (item.tipo === "imagen") {
                    // Fabricar tarjeta de imagen/infografía de la galería
                    const tarjetaImagen = document.createElement("div");
                    tarjetaImagen.className = "tarjeta-imagen";
                    tarjetaImagen.innerHTML = `
                        <img src="${item.url}" alt="${item.titulo}" loading="lazy">
                        <div class="info-imagen">
                            <h4>${item.titulo}</h4>
                            <p>${item.descripcion}</p>
                        </div>
                    `;
                    contenedorImagenes.appendChild(tarjetaImagen);
                }
            });
            
            // Caso de seguridad: Si no hay elementos cargados de algún tipo
            if (contenedorVideos.innerHTML === "") {
                contenedorVideos.innerHTML = "<p>No hay videos de orientación publicados en este momento.</p>";
            }
            if (contenedorImagenes.innerHTML === "") {
                contenedorImagenes.innerHTML = "<p>No hay material visual publicado en este momento.</p>";
            }
        })
        .catch(error => {
            console.error("Error al renderizar el contenido dinámico:", error);
            contenedorVideos.innerHTML = "<p>El material se está actualizando. Por favor, intente más tarde.</p>";
            contenedorImagenes.innerHTML = "<p>El material se está actualizando. Por favor, intente más tarde.</p>";
        });
}