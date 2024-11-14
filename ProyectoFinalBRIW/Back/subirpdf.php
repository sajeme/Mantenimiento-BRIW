<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://tribunales.prevencionamigable.com.mx/public/lib/herramientaAccesibilidad.js" defer></script> 
    <style>
    body {
        background-color: #282c34;
        color: white;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        flex-direction: column;
    }

    .btn-custom {
        color: white;
        background-color: transparent;
        border: 1px solid #ffffff;
    }

    /* Centrando el formulario */
    #upload-form {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%; /* Ocupa todo el ancho disponible en pantallas pequeñas */
    }

    .file-dropzone {
        display: flex;
        justify-content: center;
        align-items: center;
        height: 150px;
        width: 400px;
        border: 2px dashed white;
        border-radius: 5px;
        cursor: pointer;
        text-align: center;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }

    .file-dropzone input {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        opacity: 0;
        cursor: pointer;
    }

    .file-dropzone .file-info {
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        color: white;
    }

    .file-dropzone .file-info i {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    .file-dropzone .file-info span {
        word-break: break-all;
    }

    .file-dropzone:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    #alertModal .modal-content {
        min-width: 300px;
        min-height: 150px;
        background-color: #282c34;
        color: white;
    }

    /* Ajustes responsivos */
    @media (max-width: 767px) {
        .file-dropzone {
            width: 90%; /* Ancho adaptado para pantallas pequeñas */
            height: auto;
            padding: 1rem;
            margin: 0 auto; /* Centra horizontalmente */
        }
        #upload-form {
            width: 100%;
            padding: 0 1rem; /* Añade padding para centrar contenido */
        }
    }

    @media (min-width: 768px) and (max-width: 1024px) {
        .file-dropzone {
            width: 70%;
            height: auto;
            max-width: 450px;
            padding: 1rem;
            margin: 0 auto; /* Centra horizontalmente */
        }
        #upload-form {
            width: 100%;
            padding: 0 2rem; /* Ajusta el padding para centrar en tablets */
        }
    }

    @media (min-width: 1025px) {
        .file-dropzone {
            width: 400px;
            height: 150px;
        }
    }
</style>
</head>
<body>

    <!-- Botón de regreso -->
    <a href="../Front/index.html" class="btn btn-custom position-absolute top-0 start-0 m-3" aria-label="menu-inicio">
        <i class="bi bi-arrow-left" role="button"></i> Atrás
    </a>

    <!-- Título -->
    <h2 class="text-center fw-bold" role="title" aria-label="subir" style="font-family: Arial;">Subir PDF</h2>

    <!-- Formulario -->
    <form id="upload-form" action="subirPDF.php" method="post" enctype="multipart/form-data" class="text-center mt-4">

        <!-- Contenedor de arrastre de archivos -->
        <div class="file-dropzone" id="file-dropzone">
            <div class="file-info" aria-required="true" aria-label="volver-atras" aria-requierd="true">
                <i class="bi bi-file-earmark-pdf" role="button"></i>
                <span>Arrastre su archivo aquí o haga clic para seleccionar</span>
            </div>
            <input type="file" id="file-upload" name="archivos[]" accept="application/pdf" multiple role="button">
        </div>

        <!-- Botón de subir archivos -->
        <input type="submit" value="Subir archivos" name="subir" aria-label="PDF Subir" aria-required="true" class="btn btn-custom mt-2" id="submit-btn" disabled>
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!--Accesibilidad -->
    <script src="https://tribunales.prevencionamigable.com.mx/public/lib/herramientaAccesibilidad.js" defer></script>

    <!-- Mostrar nombre de archivo seleccionado -->
    <script>
        const fileUpload = document.getElementById('file-upload');
        const fileInfo = document.querySelector('.file-info span');
        const fileIcon = document.querySelector('.file-info i');
        const submitButton = document.getElementById('submit-btn');

        // Validar que el archivo sea PDF y su tamaño no exceda 5MB
        fileUpload.addEventListener('change', function() {
            let isPdf = true;
            let isValidSize = true;

            for (let i = 0; i < fileUpload.files.length; i++) {
                const file = fileUpload.files[i];
                if (file.type !== 'application/pdf') {
                    isPdf = false;
                    showModal('Error', 'Uno o más archivos no son PDF.');
                    break;
                }
                if (file.size > 5 * 1024 * 1024) { // 5 MB
                    isValidSize = false;
                    showModal('Error', 'Uno o más archivos superan el tamaño permitido de 5 MB.');
                    break;
                }
            }

            // Habilitar o deshabilitar el botón según las validaciones
            submitButton.disabled = !(isPdf && isValidSize);

            // Mostrar los nombres de los archivos seleccionados
            if (isPdf && isValidSize) {
                const files = Array.from(fileUpload.files).map(file => file.name).join(', ');
                fileIcon.classList.replace('bi-file-earmark-pdf', 'bi-file-earmark-check');
                fileInfo.innerHTML = `<i class="bi bi-file-earmark-check" role="button"></i> ${files}`;
            }
        });

        // Manejo del envío del formulario
        document.getElementById('upload-form').addEventListener('submit', function(event) {
            event.preventDefault(); // Evita el envío estándar del formulario
            
            const formData = new FormData(this);

            fetch('subirPDF.php', {
                method: 'POST',
                body: formData
            }).then(response => {
                if (response.ok) {
                    showModal('Éxito', 'Archivos correctamente indexados', true);
                } else {
                    showModal('Error', 'Error al indexar los archivos. Intente nuevamente.');
                }
            }).catch(error => {
                showModal('Error', 'Error al indexar los archivos. Intente nuevamente.');
            });
        });

        // Función para mostrar modal de alerta
        function showModal(title, message, success = false) {
            const modalTitle = document.getElementById('modalTitle');
            const modalBody = document.getElementById('modalBody');
            const modal = new bootstrap.Modal(document.getElementById('alertModal'));

            modalTitle.textContent = title;
            modalBody.textContent = message;
            
            if (success) {
                // Redirige al usuario después de cerrar el modal de éxito
                document.getElementById('modalCloseBtn').addEventListener('click', () => {
                    window.location.href = '../Front/index.html';
                });
            }

            modal.show();
        }
    </script>

<!-- Modal de alerta -->
<div class="modal fade" id="alertModal" tabindex="-1" aria-labelledby="alertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="background-color: #282c34; color: white;"> <!-- Color de fondo y texto -->
            <div class="modal-header" style="border-bottom: 1px solid white;"> <!-- Borde blanco en el header -->
                <h5 class="modal-title" id="modalTitle" style="color: white;"></h5> <!-- Texto blanco -->
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button> <!-- Cerrar en blanco -->
            </div>
            <div class="modal-body" id="modalBody">
                <!-- El mensaje se mostrará aquí -->
            </div>
            <div class="modal-footer" style="border-top: 1px solid white;"> <!-- Borde blanco en el footer -->
                <button type="button" class="btn btn-light" id="modalCloseBtn" data-bs-dismiss="modal">Cerrar</button> <!-- Botón claro -->
            </div>
        </div>
    </div>
</div>

<?php 
if(!(isset($_FILES["archivos"]) && !empty($_FILES["archivos"]["name"][0]))){
    return;
}

?>

</body>
</html>

<?php 
if(!(isset($_FILES["archivos"]) && !empty($_FILES["archivos"]["name"][0]))){
    return;
}

?>

</body>
</html>

<?php
require '../vendor/autoload.php';

if (!(isset($_FILES["archivos"]) && !empty($_FILES["archivos"]["name"][0]))) {
    return;
}

// Verificar tipo y tamaño de archivos en el servidor
$maxFileSize = 5 * 1024 * 1024; // 5 MB
$directorio = '../Back/archivos/';
$archivos = guardarArchivos($directorio);

if ($archivos !== false) {
    indexarArchivos($archivos, $directorio);
    echo "Archivos indexados correctamente.";
} else {
    echo "Error al guardar los archivos.";
}

function guardarArchivos($directorio) {
    $archivos = [];
    $cantidad = sizeof($_FILES["archivos"]["name"]);
    for ($i = 0; $i < $cantidad; $i++) {
        $nombre = nombreArchivo($_FILES["archivos"]["name"][$i]);
        
        // Validar que el archivo sea PDF
        if ($_FILES["archivos"]["type"][$i] !== "application/pdf") {
            echo "Error: Solo se permiten archivos PDF.";
            return false;
        }

        // Validar que el tamaño del archivo no exceda 5 MB
        if ($_FILES["archivos"]["size"][$i] > 5 * 1024 * 1024) {
            echo "Error: El archivo " . $_FILES["archivos"]["name"][$i] . " excede el límite de 5 MB.";
            return false;
        }

        move_uploaded_file($_FILES["archivos"]["tmp_name"][$i], $directorio . $nombre);
        $archivos[] = $nombre;
    }
    return $archivos;
}

function indexarArchivos($archivos){
  //$invertedIndex = [];
  global $server;
  global $directorio;
  foreach($archivos as $archivo){

    $parser = new Smalot\PdfParser\Parser();
    $pdf = $parser->parseFile($directorio.$archivo);
    $contenido = $pdf->getText();
    

    if($contenido === false) die('Unable to read file: ' . $archivo);
    $contenido  = limpiar($contenido);
    echo $contenido;


    $url = "$server$directorio$archivo";
    $nombre = $archivo;
    $datos= [
        'id' => uniqid(),
        'title'=> $nombre,
        'content'=> $contenido,
        'url' => $url,
        'keywords_s'=> palabrasClave($contenido, 20),
        'language'=> lenguaje($contenido)
    ];
    indexarPDF($datos);
  }
}

include("../Back/http.php");
include("../Back/parse.php");
include("../Back/addresses.php");
include("../Back/httpCodes.php");
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

function indexarPDF($datos)
{
    $solrUrl = 'http://localhost:8983/solr/ProyectoFinal/update/?commit=true';
    echo "<pre>";
    var_dump($datos);
    echo "</pre>";
    // Conexión y envío de datos a Solr
    $client = new Client();
    try {
        $response = $client->request('POST', $solrUrl, [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode([$datos]),
        ]);
        $statusCode = $response->getStatusCode();
        if ($statusCode === 200) {
            return 'Datos indexados correctamente en Solr.';
        } else {
            return 'Error al indexar datos en Solr: ' . $response->getReasonPhrase();
        }
    } catch (RequestException $e) {
        return 'Error al indexar datos en Solr: ' . $e->getMessage();
    }
}

function nombreArchivo(string $nombre){
  $actual = 0;
  $archivo = explode('.',$nombre);
  $devolver = $archivo[0];

  while(file_exists($GLOBALS['directorio'].$devolver.'.'.$archivo[1])){
    $devolver=$archivo[0].$actual;
    $actual ++;
  };
  return $devolver.'.'.$archivo[1];
}

use ICanBoogie\Inflector;
use Kaiju\Stopwords\Stopwords as StopwordFilter;
use Text_LanguageDetect\LanguageDetect;

function palabrasClave($content, int $cantidad) {
    $resultado = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $content));

    $lenguaje = lenguaje($resultado);
    $stopwords = new StopwordFilter();
    $stopwords->load($lenguaje === 'es' ? 'spanish' : 'english');
    
    // Eliminar stopwords
    $resultado = $stopwords->clean($resultado);
    // Dividir el contenido filtrado en tokens (palabras)
    $tokens = explode(' ', $resultado);

    // Filtrar tokens no válidos (palabras de menos de 3 caracteres o no alfabéticas)
    $tokensFiltrados = array_filter($tokens, function($token) {
        return (strlen($token) > 2 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚ]+$/', $token));
    });

    // Array para almacenar las palabras normalizadas y sus frecuencias
    $normalizado = [];
    $inflector = Inflector::get($lenguaje);

    // Normalizar las palabras (singularización) y contar la frecuencia de cada una
    foreach ($tokensFiltrados as $token) {
        $normal = $inflector->singularize($token);

        // Contar las palabras normalizadas
        if (!array_key_exists($normal, $normalizado)) {
            $normalizado[$normal] = 1;
        } else {
            $normalizado[$normal] += 1;
        }
    }

    // Ordenar las palabras por frecuencia en orden descendente
    arsort($normalizado);
    
    // Obtener primeras palabras clave
    $palabrasClave = array_slice($normalizado, 0, $cantidad);
    
    return array_keys($palabrasClave);
}

function lenguaje($contenido) {
    $contenidoLimpio = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $contenido);

    $ld = new Text_LanguageDetect();
    $ld->setNameMode(2); // obtener nombres idiomas

    $detectedLanguage = $ld->detectSimple(substr($contenidoLimpio, 0, 3000));

    // Si no se detecta un idioma válido, intentar analizar si es más inglés o español
    if ($detectedLanguage === null || !in_array($detectedLanguage, ['spanish', 'english'])) {
        $englishWords = ['the', 'and', 'for', 'with', 'you'];
        $spanishWords = ['el', 'y', 'para', 'con', 'tu'];

        $englishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $englishWords));
        $spanishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $spanishWords));

        // Determinar el idioma basado en la cantidad de palabras comunes
        if ($englishCount > $spanishCount) {
            return 'en';
        } elseif ($spanishCount > $englishCount) {
            return 'es';
        } else {
            return 'es';
        }
    }

    echo "Lenguaje detectado: " . $detectedLanguage;
    
    return ($detectedLanguage === 'spanish') ? 'es' : 'en';
}


function limpiar($var) {
  return strtolower(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúüÁÉÍÓÚÜñÑ\s]+/u', '', $var)));
}
?>
