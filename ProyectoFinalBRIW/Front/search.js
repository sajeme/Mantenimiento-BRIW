

$(document).ready(function () {
  const suggestionsContainer = $("#suggestionsContainer");

  const hideDropdownIfFocusedOutside = (event) => {
    const focusedElement = event.relatedTarget;
    if (focusedElement == null || !(focusedElement.classList.contains('dropdown-item') || focusedElement.id === 'queryInput')) {
      suggestionsContainer.addClass('hidden');
    }
  };

  function mostrarSugerencias(sugerencias) {
    if (sugerencias?.length > 0) {
      suggestionsContainer.removeClass('hidden').empty();
      sugerencias.forEach(sugerencia => {
        const sugerenciaElemento = $("<button>")
          .addClass('dropdown-item')
          .text(sugerencia)
          .on('focusout', hideDropdownIfFocusedOutside)
          .click(() => {
            obtenerResultados(sugerencia);
            obtenerPalabrasRelacionadas(sugerencia);
            $("#queryInput").val(sugerencia);
            suggestionsContainer.addClass('hidden').empty();
            $("#correctionContainer").empty();
          });
        suggestionsContainer.append(sugerenciaElemento);
      });
    } else {
      suggestionsContainer.addClass('hidden').empty();
    }
  }

  $("#queryInput")
    .on('input', function () {
      const palabraConsulta = $(this).val();
      obtenerSugerencias(palabraConsulta);
    })
    .on('focus', () => suggestionsContainer.removeClass('hidden'))
    .on('focusout', hideDropdownIfFocusedOutside);

  function obtenerSugerencias(palabra) {
    fetch(`https://inputtools.google.com/request?text=${palabra}&itc=es-t-i0-und&num=5`)
      .then(response => response.json())
      .then(data => {
        const sugerencias = data?.[1]?.[0]?.[1] || [];
        mostrarSugerencias(sugerencias);
        if (sugerencias && sugerencias.length > 0) {
          const correccion = sugerencias[0];
          mostrarCorreccion(correccion);
        }
      })
      .catch(error => console.error('Error al obtener sugerencias:', error));
  }

  function mostrarCorreccion(correccion) {
    const correctionContainer = $("#correctionContainer");
    correctionContainer.empty();
    if (correccion && correccion !== $("#queryInput").val()) {
      correctionContainer
        .append('Quizás quisiste decir:  ')
        .append(
          $('<button>')
            .addClass('text')
            .text(correccion)
            .click(() => {
              obtenerResultados(correccion);
              obtenerPalabrasRelacionadas(correccion);
              $("#queryInput").val(correccion);
              $("#correctionContainer").empty();
            })
        );
    } else {
      correctionContainer.empty();
    }
  }
  function intersperseItems(items, separator) {
    const result = [];
    for (let i = 0; i < items.length; i++) {
      result.push(items[i]);
      if (i < items.length - 1) {
        result.push(separator);
      }
    }
    return result;
  }
  
  function obtenerPalabrasRelacionadas(consulta) {
    const relatedWordsContainer = $("#relatedWords");
    if (consulta === '') {
      return;
    }
    fetch(`https://api.datamuse.com/words?ml=${consulta}&max=5`)
      .then(respuesta => respuesta.json())
      .then(palabras => {
        relatedWordsContainer.empty();
        if (palabras.length === 0) {
          return;
        }
        relatedWordsContainer.append('Palabras relacionadas: ');
        const relatedWordsItems = palabras.map(({ word }) =>
          $('<button>')
            .addClass('text')
            .text(word)
            .click(() => {
              obtenerResultados(word);
              obtenerPalabrasRelacionadas(word);
              $("#queryInput").val(word);
              $("#correctionContainer").empty();
            })
        );
        intersperseItems(relatedWordsItems, ', ').forEach(element => {
          relatedWordsContainer.append(element);
        });        
      })
      .catch(error => console.error('Error al obtener palabras relacionadas:', error));
  }

  // Click en el botón de ejecutar consulta
  $("#executeButton").click(function () {
    const consulta = $("#queryInput").val();
    obtenerResultados(consulta);
    obtenerPalabrasRelacionadas(consulta);
  });

  // Manejador de eventos para enlaces de paginación
  $(document).on('click', '.pagination-link', function (e) {
    e.preventDefault();
    const page = $(this).data('page');
    const query = $("#queryInput").val();
    obtenerResultados(query, page);
  });
});


function obtenerResultados(consulta, pagina = 1) {
  const errorMessageContainer = $('#errorMessage');
  const queryInput = $("#queryInput").val().trim();

  // Si no hay nada escrito
  if (queryInput === '') {
    errorMessageContainer.text('Por favor, ingresa una consulta de búsqueda.').removeClass('hidden');
    $("#suggestionsContainer").empty();
    $("#relatedWords").empty();
    $("#searchResults").empty();
    $("#paginationContainer").empty(); // Paginador en 0
    return;
  } else {
    errorMessageContainer.addClass('hidden'); 
  }

  // Realizar la solicitud AJAX a search.php con la paginación
  $.ajax({
    url: "../Back/search.php",
    data: {
      q: consulta,
      pagina: pagina
    },
    dataType: 'json', // Asegura que espera una respuesta en JSON
    success: function (respuesta) {
      console.log("Llamando a mostrarPaginacion con", consulta, respuesta.paginaActual, respuesta.totalPaginas);
      mostrarResultados(consulta, respuesta);
      mostrarPaginacion(consulta, respuesta.paginaActual, respuesta.totalPaginas);
    },
    error: function (xhr, estado, error) {
      console.error("Error al buscar en Solr desde PHP:", error);
      console.log("Respuesta recibida:", xhr.responseText); // Verifica si la respuesta es HTML o tiene un error de PHP
      $("#searchResults").empty().append("<p>Error al buscar en Solr desde PHP</p>");
    }
  });
  
}


function mostrarPaginacion(consulta, paginaActual, totalPaginas) {
  const paginacionContainer = $("#paginationContainer");
  paginacionContainer.empty();

  console.log(`Generando paginación para la página ${paginaActual} de ${totalPaginas}`);

  const maxPagesToShow = 10;
  let startPage = Math.max(1, paginaActual - Math.floor(maxPagesToShow / 2));
  let endPage = Math.min(totalPaginas, startPage + maxPagesToShow - 1);

  if (paginaActual > 1) {
    paginacionContainer.append(`<a href="#" class="pagination-link" data-page="${paginaActual - 1}">Anterior</a> `);
  }

  for (let i = startPage; i <= endPage; i++) {
    if (i === paginaActual) {
      paginacionContainer.append(`<strong>${i}</strong> `);
    } else {
      paginacionContainer.append(`<a href="#" class="pagination-link" data-page="${i}">${i}</a> `);
    }
  }

  if (paginaActual < totalPaginas) {
    paginacionContainer.append(`<a href="#" class="pagination-link" data-page="${paginaActual + 1}">Siguiente</a>`);
  }

  console.log("HTML generado para paginación:", paginacionContainer.html());
}

function mostrarResultados(consulta, respuesta) {
  const resultsContainer = document.getElementById("searchResults");

  if (respuesta.documents.length === 0) {
      resultsContainer.innerHTML = `<h2>No se encontraron resultados para <span class="bold">${consulta}</span> :(</h2>`;
      return;
  }

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
      snippetP.textContent = snippet;  // Renderizamos el snippet directamente

      resultDiv.appendChild(link);
      resultDiv.appendChild(snippetP);

      resultsContainer.appendChild(resultDiv);
  });
}
