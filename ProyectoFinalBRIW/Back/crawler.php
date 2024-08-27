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

function palabrasClave($content, int $cantidad){
    //Limpiar texto,
    
    $sw = new StopWords();
    
    $resultado = contenido($content);//Quitar tags de html
    $resultado = preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚ\s]+/u', '', $resultado)); //Dejar solo letras 
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

// Uso del WebCrawler
// URLs de inicio
$startUrls = [
    'https://www.xataka.com.mx/',
    'https://es.wikipedia.org/wiki/Wikipedia',
    'https://www.elpalaciodehierro.com'
];
$maxDepth = 2;

foreach ($startUrls as $startUrl) {
    $crawler = new WebCrawler($startUrl, $maxDepth);
    $crawler->startCrawling();
}