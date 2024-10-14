<?php
include("../Back/http.php");
include("../Back/parse.php");
include("../Back/addresses.php");
include("../Back/httpCodes.php");


require '../vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use ICanBoogie\Inflector;
use voku\helper\StopWords;

class WebCrawler
{
    private $client;
    private $baseDomain;
    private $maxDepth;
    private $visitedUrls;
    private $urlsToCrawl;

    public function __construct($startUrl, $maxDepth = 5)
    {
        $this->client = new Client();
        $this->baseDomain = parse_url($startUrl, PHP_URL_HOST);
        $this->maxDepth = $maxDepth;
        $this->visitedUrls = [];
        $this->urlsToCrawl = [[$startUrl, 0]]; // Guarda la profundidad de cada URL
    }

    private function get_title($content)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($content); // Ignora los errores de HTML mal formado
        $titles = $dom->getElementsByTagName('title');
        if ($titles->length > 0) {
            return $titles->item(0)->nodeValue;
        }
        return null;
    }

    private function indexContentToSolr($content, $url)
    {
        $solrUrl = 'http://localhost:8983/solr/ProyectoFinal/update/?commit=true';

        // Obtener el título real del contenido
        $title = $this->get_title($content);

        // Datos a indexar en Solr con el título real del contenido
        $contenido = contenido($content);
        $data_to_index = [
            'id' => uniqid(), // Generar un ID único para el documento
            'title' => page_title($content),
            'content' => substr($contenido,0,1000),
            'url' => $url,
            'keywords_s'=> palabrasClave($contenido, 20),
            'language'=> lenguaje($contenido)
        ];
        echo "<pre>";
        var_dump($data_to_index);
        echo "</pre>";
        // Conexión y envío de datos a Solr
        $client = new Client();
        try {
            $response = $client->request('POST', $solrUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([$data_to_index]),
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

    public function startCrawling()
    {
        while (!empty($this->urlsToCrawl)) {
            [$url, $depth] = array_shift($this->urlsToCrawl);

            if (!in_array($url, $this->visitedUrls) && $depth <= $this->maxDepth) {
                $this->crawlUrl($url);
                $this->visitedUrls[] = $url;
            }
        }
    }

    private function crawlUrl($url)
    {
        try {
            $response = $this->client->request('GET', $url);
            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $body = $response->getBody()->getContents();
                echo "URL: $url";

                // Indexar el contenido en Solr
                $indexingResult = $this->indexContentToSolr($body, $url);
                echo "Resultado de indexación en Solr: $indexingResult<br>";

                // Continuar con la extracción de enlaces
                $this->extractAndQueueLinks($body, $url);
            }
        } catch (RequestException $e) {
            echo "Error al obtener la URL $url: " . $e->getMessage();
        }
    }

    private function extractAndQueueLinks($content, $baseUrl)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($content); // Ignora los errores de HTML mal formado
        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $absoluteUrl="";
            if (strpos($href,'http') !== false) {
                $absoluteUrl=$href;
            } else {
                $absoluteUrl = $this->resolveUrl($href, $baseUrl);
            }
            if ($this->isValidUrl($absoluteUrl) && !$this->urlAlreadyQueued($absoluteUrl)) {
                $this->urlsToCrawl[] = [$absoluteUrl, $this->getCurrentDepth($baseUrl) + 1];
            }
        }
    }

    private function resolveUrl($href, $baseUrl)
    {
        $href = trim($href);
        $baseUrl = trim($baseUrl);
        return rtrim($baseUrl, '/') . '/' . ltrim($href, '/');
    }

    private function isValidUrl($url)
    {
        $parsedUrl = parse_url($url);
        return isset($parsedUrl['host']) && $parsedUrl['host'] === $this->baseDomain;
    }

    private function urlAlreadyQueued($url)
    {
        return in_array($url, array_column($this->urlsToCrawl, 0));
    }

    private function getCurrentDepth($url)
    {
        foreach ($this->urlsToCrawl as $u) {
            if ($u[0] === $url) {
                return $u[1];
            }
        }
        return 0;
    }
}

use Kaiju\Stopwords\Stopwords as StopwordFilter;

function palabrasClave($content, int $cantidad) {
    $resultado = contenido($content); // Quitar etiquetas HTML
    $resultado = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $resultado));

    // Detectar el idioma del contenido
    $lenguaje = lenguaje($resultado);
    $stopwords = new StopwordFilter();
    $stopwords->load($lenguaje === 'es' ? 'spanish' : 'english'); // Cargar stopwords
    $resultado = $stopwords->clean($resultado); // Elimina stopwords del texto

    // Dividir el contenido filtrado en tokens (palabras)
    $tokens = explode(' ', $resultado);

    // Filtrar tokens no válidos
    $tokensFiltrados = array_filter($tokens, function($token) {
        return (strlen($token) > 2 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚ]+$/', $token));
    });

    // Array para almacenar las palabras normalizadas y sus frecuencias
    $normalizado = [];
    $inflector = Inflector::get($lenguaje); // Usar el inflector basado en el idioma detectado

    // Normalizar las palabras (singularización) y contar la frecuencia de cada una
    foreach ($tokensFiltrados as $token) {
        $normal = $inflector->singularize($token); // Singularizar cada palabra

        // Contar las palabras normalizadas
        if (!array_key_exists($normal, $normalizado)) {
            $normalizado[$normal] = 1;
        } else {
            $normalizado[$normal] += 1;
        }
    }

    // Ordenar las palabras por frecuencia en orden descendente
    arsort($normalizado);
    $palabrasClave = array_slice($normalizado, 0, $cantidad);
    return array_keys($palabrasClave);
}

function page_title($body) {
    $tags = [
        'title',
        'h1',
        'h2',
        'h3',
        'h4',
        'p',
        'div'
    ];
    $res = "";
    $i=0;
    for($i = 0; $i< sizeof($tags); $i++){
        $res = preg_match("/<$tags[$i]>(.*)<\/$tags[$i]>/siU", $body, $title_matches);
        if(!empty($res)){
            $title = preg_replace('/\s+/', ' ', $title_matches[1]);
            $title = trim($title);
            return $title;
        }
    }
        return null;
}

function contenido($content){
    $html = new \Html2Text\Html2Text($content);
    $contenido = $html->getText();
    $contenido = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $contenido));
    return $contenido; //Quitar tags de html
}

use Text_LanguageDetect;

function lenguaje($contenido) {
    // Limpiar el contenido para eliminar caracteres no alfabéticos
    $contenidoLimpio = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $contenido);

    $ld = new Text_LanguageDetect();
    $ld->setNameMode(2); // Configurar para obtener nombres completos de idiomas

    // Detectar el idioma con 3k caracteres
    $detectedLanguage = $ld->detectSimple(substr($contenidoLimpio, 0, 3000));

    // Fix para paginas en ingles, con algunas palabras en español
    if ($detectedLanguage === null || !in_array($detectedLanguage, ['spanish', 'english'])) {
        // se analiza si hay más palabras en inglés o en español
        $englishWords = ['the', 'and', 'for', 'with', 'you'];
        $spanishWords = ['el', 'y', 'para', 'con', 'tu']; 

        $englishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $englishWords));
        $spanishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $spanishWords));

        // conteo de palabras comunes que determina language
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



// Uso del WebCrawler
// URLs de inicio
$startUrls = [
    'https://www.usa.gov/',
    'https://www.xataka.com.mx/',
    'https://www.elpalaciodehierro.com'
];
$maxDepth = 2;

foreach ($startUrls as $startUrl) {
    $crawler = new WebCrawler($startUrl, $maxDepth);
    $crawler->startCrawling();
}