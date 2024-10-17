// Función para manejar el click en cada enlace <a>
let consulta = "";

function activarQuerys(event) {
    event.preventDefault();  // Evitar comportamiento por defecto de los enlaces
    consulta = event.target.textContent;  // Obtener el texto del enlace clicado
    realizarBusqueda(consulta, 1);  // Realizar la búsqueda, empezando por la página 1
}

// Función para realizar la búsqueda sin jQuery ni AJAX
function realizarBusqueda(consulta, pagina) {
    // Aquí harías la solicitud a tu archivo PHP usando fetch()
    fetch(`../Back/search.php?q=${consulta}&pagina=${pagina}`)
        .then(response => response.json())
        .then(data => {
            console.log("Resultados recibidos:", data);
            mostrarResultados(consulta, data);
            mostrarPaginacion(consulta, data.paginaActual, data.totalPaginas);
        })
        .catch(error => {
            console.error("Error al buscar en PHP:", error);
            document.getElementById("searchResults").innerHTML = "<p>Error al buscar en PHP</p>";
        });
}

// Función para mostrar la paginación
function mostrarPaginacion(consulta, paginaActual, totalPaginas) {
    const paginacionContainer = document.getElementById("paginationContainer");
    paginacionContainer.innerHTML = '';  // Limpiar el contenedor de paginación

    console.log(`Generando paginación para la página ${paginaActual} de ${totalPaginas}`);

    const maxPagesToShow = 10;
    let startPage = Math.max(1, paginaActual - Math.floor(maxPagesToShow / 2));
    let endPage = Math.min(totalPaginas, startPage + maxPagesToShow - 1);

    // Botón de "Anterior"
    if (paginaActual > 1) {
        const prevLink = document.createElement('a');
        prevLink.href = '#';
        prevLink.className = 'paginationCategories';
        prevLink.dataset.page = paginaActual - 1;
        prevLink.textContent = 'Anterior';
        prevLink.addEventListener('click', function() {
            realizarBusqueda(consulta, paginaActual - 1);
        });
        paginacionContainer.appendChild(prevLink);
    }

    // Números de páginas
    for (let i = startPage; i <= endPage; i++) {
        if (i === paginaActual) {
            const strong = document.createElement('strong');
            strong.textContent = i;
            paginacionContainer.appendChild(strong);
        } else {
            const pageLink = document.createElement('a');
            pageLink.href = '#';
            pageLink.className = 'paginationCategories';
            pageLink.dataset.page = i;
            pageLink.textContent = i;
            pageLink.addEventListener('click', function() {
                realizarBusqueda(consulta, i);
            });
            paginacionContainer.appendChild(pageLink);
        }
    }

    // Botón de "Siguiente"
    if (paginaActual < totalPaginas) {
        const nextLink = document.createElement('a');
        nextLink.href = '#';
        nextLink.className = 'paginationCategories';
        nextLink.dataset.page = paginaActual + 1;
        nextLink.textContent = 'Siguiente';
        nextLink.addEventListener('click', function() {
            realizarBusqueda(consulta, paginaActual + 1);
        });
        paginacionContainer.appendChild(nextLink);
    }

    console.log("HTML generado para paginación:", paginacionContainer.innerHTML);
}

// Función para mostrar los resultados de la búsqueda
function mostrarResultados(consulta, respuesta) {
    const resultsContainer = document.getElementById("searchResults");

    if (respuesta.documents.length === 0) {
        resultsContainer.innerHTML = `<h2>No se encontraron resultados para <span class="bold">${consulta}</span> :(</h2>`;
        return;
    }

    // Limpiar contenedor
    resultsContainer.innerHTML = '';

    respuesta.documents.forEach(({ title, url, snippet }) => {
        const resultDiv = document.createElement('div');
        resultDiv.className = 'search-result';

        const link = document.createElement('a');
        link.className = 'result-title';
        link.href = url;
        link.target = '_blank';
        link.textContent = title;

        const snippetP = document.createElement('p');
        snippetP.className = 'result-snippet';
        snippetP.textContent = snippet;

        resultDiv.appendChild(link);
        resultDiv.appendChild(snippetP);

        resultsContainer.appendChild(resultDiv);
    });
}

$(document).on('click', '.paginationCategories', function (e) {
    e.preventDefault();
    const page = $(this).data('page');
    //const query = $("#queryInput").val();
    realizarBusqueda(consulta, page);
  });
