const intersperse = (arr, sep) => arr.reduce((a,v)=>[...a,v,sep],[]).slice(0,-1)

$(document).ready(function () {
  const suggestionsContainer = $("#suggestionsContainer");

  const hideDropdownIfFocusedOutside = (event) => {
    const focusedElement = event.relatedTarget;
    if (focusedElement == null || !(focusedElement.classList.contains('dropdown-item') || focusedElement.id === 'queryInput')){
      suggestionsContainer.addClass('hidden');
    }
  }

  // Función para obtener sugerencias de ortografía desde la API de Google en español
  function mostrarSugerencias(sugerencias) {
    if (sugerencias?.length > 0) {
      suggestionsContainer
        .removeClass('hidden')
        .empty();

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

  // Escuchar el evento de cambio en el input
  $("#queryInput")
    .on('input', function () {
      const palabraConsulta = $(this).val();
      obtenerSugerencias(palabraConsulta);
    })
    .on('focus', () => suggestionsContainer.removeClass('hidden'))
    .on('focusout', hideDropdownIfFocusedOutside);

  // Función para obtener sugerencias de ortografía desde la API de Google en español
  function obtenerSugerencias(palabra) {
    // Llamada a la API de Google para español
    fetch(`https://inputtools.google.com/request?text=${palabra}&itc=es-t-i0-und&num=5`)
      .then(response => response.json())
      .then(data => {
        const sugerencias = data?.[1]?.[0]?.[1] || [];
        mostrarSugerencias(sugerencias);

        // Verificar si hay correcciones y mostrarlas
        if (sugerencias && sugerencias.length > 0) {
          const correccion = sugerencias[0]; // Tomar la primera sugerencia como corrección

          // Mostrar la corrección en el contenedor de correcciones
          mostrarCorreccion(correccion);
        }
      })
      .catch(error => console.error('Error al obtener sugerencias:', error));
  }

  // Función para mostrar las correcciones
  function mostrarCorreccion(correccion) {
    const correctionContainer = $("#correctionContainer");
    correctionContainer.empty(); // Limpiar el contenedor antes de mostrar la corrección

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
      correctionContainer.empty(); // Vaciar el contenedor si no hay corrección o es igual a la palabra original
    }
  }

  // Función para obtener y mostrar palabras relacionadas semánticamente
  function obtenerPalabrasRelacionadas(consulta) {
    const relatedWordsContainer = $("#relatedWords");

    // Verificar que haya una consulta escrita
    if (consulta === '') {
      return;
    }

    fetch(`https://api.datamuse.com/words?ml=${consulta}&max=5`)
      .then(respuesta => respuesta.json())
      .then(palabras => {
        // Limpiar el contenedor antes de mostrar nuevas palabras
        relatedWordsContainer.empty();

        if(palabras.length === 0) {
          return;
        }

        relatedWordsContainer.append('Palabras relacionadas: ');

        const relatedWordsItems = palabras.map(({word}) => 
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

        // Enlistar las palabras relacionadas, separadas por coma
        intersperse(relatedWordsItems, ', ')
          .forEach(element => {
            relatedWordsContainer.append(element);
          });
      })
      .catch(error => console.error('Error al obtener palabras relacionadas:', error));
  }

  function obtenerResultados(consulta) {
    const errorMessageContainer = $('#errorMessage');
    if (consulta === '') {
      errorMessageContainer.removeClass('hidden');
      $("#suggestionsContainer").empty();
      $("#relatedWords").empty();
      $("#searchResults").empty();
      return;
    }

    errorMessageContainer.addClass('hidden');

    // Realizar la solicitud AJAX a search.php para realizar la búsqueda en Solr
    $.ajax({
      url: "../Back/search.php", // Ruta a search.php desde index.html
      data: {
        q: consulta // La consulta que se enviará a search.php
      },
      success: function (respuesta) {
        console.log("Resultados de Solr desde PHP:", respuesta);
        // Mostrar los resultados en la sección searchResults
        mostrarResultados(consulta, respuesta);
      },
      error: function (xhr, estado, error) {
        console.error("Error al buscar en Solr desde PHP:", error);
        $("#searchResults").empty().append("<p>Error al buscar en Solr desde PHP</p>");
      }
    });
  }

  $("#executeButton").click(function () {
    const consulta = $("#queryInput").val(); // Obtener el valor del input
    obtenerResultados(consulta);
    obtenerPalabrasRelacionadas(consulta);
  });
});

// Función para mostrar los resultados en la lista de búsqueda
function mostrarResultados(consulta, respuesta) {
  const resultsContainer = document.getElementById("searchResults");

  if (respuesta.documents.length === 0) {
    resultsContainer.replaceChildren(
      $('<h2>')
        .append('No se encontraron resultados para ')
        .append(
          $('<span>').addClass('bold').text(consulta)
        )
        .append(' :(')
        [0]
    );
    return;
  }

  resultsContainer.replaceChildren(
    ...respuesta.documents.map(({title, url, snippet}) => (
      $('<div>')
        .addClass('search-result')
        .append(
          $('<a>')
            .addClass('result-title')
            .attr("target", "_blank")
            .attr("href", url)
            .text(title)
        ).append(
          $('<p>')
            .addClass('result-snippet')
            .text(snippet)
        )[0]
    ))
  )
}

// Función para mostrar los resultados en la lista de búsqueda
function mostrarResultados(consulta, respuesta) {
  const resultsContainer = document.getElementById("searchResults");
  const facetResultsContainer = document.getElementById("facetResults"); // Contenedor para los resultados facetados

  if (respuesta.documents.length === 0) {
    // Mostrar mensaje si no hay resultados
    resultsContainer.replaceChildren(
      $('<h2>')
        .append('No se encontraron resultados para ')
        .append(
          $('<span>').addClass('bold').text(consulta)
        )
        .append(' :(')
        [0]
    );
    return;
  }

  // Mostrar los resultados principales de la búsqueda normal
  resultsContainer.replaceChildren(
    ...respuesta.documents.map(({ title, url, snippet }) => (
      $('<div>')
        .addClass('search-result')
        .append(
          $('<a>')
            .addClass('result-title')
            .attr("target", "_blank")
            .attr("href", url)
            .text(title)
        ).append(
          $('<p>')
            .addClass('result-snippet')
            .text(snippet)
        )[0]
    ))
  );

  // Mostrar los resultados de la búsqueda facetada si están presentes
  if (respuesta.facetResults && respuesta.facetResults.length > 0) {
    facetResultsContainer.innerHTML = ''; // Limpiar los resultados previos

    // Agregar los resultados facetados al contenedor
    respuesta.facetResults.forEach(result => {
      const facetElement = document.createElement('div');
      facetElement.classList.add('facet-result');
      facetElement.textContent = result; // Puedes ajustar cómo muestras los resultados facetados según la estructura de datos
      facetResultsContainer.appendChild(facetElement);
    });
  } else {
    facetResultsContainer.innerHTML = '<p>No hay resultados de búsqueda facetada para mostrar.</p>';
  }
}