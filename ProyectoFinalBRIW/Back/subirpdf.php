<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir PDF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
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
    </style>
</head>
<body>

    <!-- Botón de regreso -->
    <a href="../Front/index.html" class="btn btn-custom position-absolute top-0 start-0 m-3">
        <i class="bi bi-arrow-left"></i> Atrás
    </a>

    <!-- Título -->
    <h2 class="text-center fw-bold" style="font-family: Arial;">Subir PDF</h2>

    <!-- Formulario -->
    <form id="upload-form" action="subirPDF.php" method="post" enctype="multipart/form-data" class="text-center mt-4">

        <!-- Contenedor de arrastre de archivos -->
        <div class="file-dropzone" id="file-dropzone">
            <div class="file-info">
                <i class="bi bi-file-earmark-pdf"></i>
                <span>Arrastre su archivo aquí o haga clic para seleccionar</span>
            </div>
            <input type="file" id="file-upload" name="archivos[]" accept="application/pdf" multiple>
        </div>

        <!-- Botón de subir archivos -->
        <input type="submit" value="Subir archivos" name="subir" class="btn btn-custom mt-2" id="submit-btn" disabled>
    </form>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Mostrar nombre de archivo seleccionado -->
    <script>
        const fileUpload = document.getElementById('file-upload');
        const fileInfo = document.querySelector('.file-info span');
        const fileIcon = document.querySelector('.file-info i');


        const fileInput = document.getElementById('file-upload');
        const submitButton = document.getElementById('submit-btn');



        fileUpload.addEventListener('change', function() {
            const files = Array.from(fileUpload.files).map(file => file.name).join(', ');
            fileIcon.classList.replace('bi-file-earmark-pdf', 'bi-file-earmark-check');
            fileInfo.innerHTML = `<i class="bi bi-file-earmark-pdf"></i> ${files}`;
        });

        // Manejadores para arrastrar y soltar archivos
        document.querySelector('.file-dropzone').addEventListener('dragover', (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.target.classList.add('dragging');
        });

        document.querySelector('.file-dropzone').addEventListener('dragleave', (event) => {
            event.preventDefault();
            event.stopPropagation();
            event.target.classList.remove('dragging');
        });

        document.querySelector('.file-dropzone').addEventListener('drop', (event) => {
            event.preventDefault();
            event.stopPropagation();
            fileUpload.files = event.dataTransfer.files;
            const files = Array.from(fileUpload.files).map(file => file.name).join(', ');
            fileIcon.classList.replace('bi-file-earmark-pdf', 'bi-file-earmark-check');
            fileInfo.innerHTML = `<i class="bi bi-file-earmark-pdf"></i> ${files}`;
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
                    alert('Archivos correctamente indexados');
                    window.location.href = '../Front/index.html';
                } else {
                    alert('Error al indexar los archivos. Intente nuevamente.');
                }
            }).catch(error => {
                alert('Error al indexar los archivos. Intente nuevamente.');
            });
        });

        fileInput.addEventListener('change', function() {
            // Si el archivo seleccionado es un PDF, habilita el botón
            if (fileInput.files.length > 0) {
                let isPdf = true;
                for (let i = 0; i < fileInput.files.length; i++) {
                    if (fileInput.files[i].type !== 'application/pdf') {
                        isPdf = false;
                        break;
                    }
                }
                submitButton.disabled = !isPdf;
            } else {
                submitButton.disabled = true; // Si no hay archivos, deshabilita el botón
            }
        });
    </script>


<?php 
if(!(isset($_FILES["archivos"]) && !empty($_FILES["archivos"]["name"][0]))){
    return;
}
//Quitar cuando se termine
/*
echo '<pre>';
echo var_dump($_FILES["archivos"]);
echo "</pre>";
*/
//--------

?>
</body>
</html>


<?php
require '../vendor/autoload.php';
if(!(isset($_FILES["archivos"]) && !empty($_FILES["archivos"]["name"][0]))){
  return;
}

$server = 'localhost/ProyectoFinal/back/';
$directorio = 'archivos/';
$archivos = guardarArchivos($directorio);

indexarArchivos($archivos, $directorio);
echo "Archivos indexados";

function guardarArchivos($directorio){
  $archivos = [];
  $cantidad = sizeof($_FILES["archivos"]["name"]);
  for($i =0; $i<$cantidad; $i++){
    $nombre = nombreArchivo( $_FILES["archivos"]["name"][$i]);
    if($_FILES["archivos"]["type"][$i]!=="application/pdf"){
      echo "No es un archivo pdf";
      return;
    }
    move_uploaded_file($_FILES["archivos"]["tmp_name"][$i],$directorio.$nombre);
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

    $url = "http://$server$directorio$archivo";
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

use voku\helper\StopWords;
use ICanBoogie\Inflector;
function palabrasClave($content, int $cantidad){
    //Limpiar texto,
    
    $sw = new StopWords();
    $resultado = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $content)); //Dejar solo letras 
    $lenguaje = lenguaje($resultado);

    $listaPV = $sw->getStopWordsFromLanguage($lenguaje);
    $resultado = preg_replace('/\b('.implode('|',$listaPV).')\b/','',$resultado);//Quitar palabras vacias
    $tokens = explode(' ', $resultado); //Dividir en palabras
    
    $normalizado= [];
    $inflector = Inflector::get($lenguaje);
    foreach($tokens as $token){//Normalizar todas las palabras, para eliminar repetidos
        $normal=  $inflector->singularize($token); 
        if(!array_key_exists($normal, $normalizado)){
            $normalizado[$normal] = 1;
        }else{
            $normalizado[$normal]+= 1;
        }
    } 
    //Obtener las mas importantes
    arsort($normalizado);
    $palabrasClave = array_slice($normalizado, 0, $cantidad);
    $palabrasClave = array_keys($palabrasClave);
    return $palabrasClave;
}
function lenguaje($contenido){
    $detector = new LanguageDetector\LanguageDetector();
    $detectedLanguage = $detector->evaluate(substr($contenido, 0, 1000))->getLanguage();
    
    // Si no se puede detectar el idioma, retornar un idioma por defecto
    if ($detectedLanguage === null || ($detectedLanguage->getCode() !== 'es' && $detectedLanguage->getCode() !== 'en')) {
        return 'es'; // Puedes cambiar esto a 'en' si prefieres un idioma diferente por defecto
    }

    echo " Lenguaje: " . $detectedLanguage->getCode();
    return $detectedLanguage->getCode();
}
function limpiar($var) {
  return strtolower(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúüÁÉÍÓÚÜñÑ\s]+/u', '', $var)));
}
?>
