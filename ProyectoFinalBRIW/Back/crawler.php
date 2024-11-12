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
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class WebCrawler
{
    private $client;
    private $baseDomain;
    private $maxDepth;
    private $visitedUrls;
    private $urlsToCrawl;

    public function __construct($startUrl, $maxDepth = 15)
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
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8')); // Asegura UTF-8 en carga HTML
        
        $tags = ['title', 'h1', 'h2'];
        foreach ($tags as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            if ($elements->length > 0) {
                return mb_convert_encoding($elements->item(0)->nodeValue, 'UTF-8', 'auto');
            }
        }
        
        $header = $dom->getElementById('firstHeading');
        if ($header) {
            return mb_convert_encoding($header->textContent, 'UTF-8', 'auto');
        }
        
        return null;
    }

    private function getMainContent($content)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        
        // Eliminar elementos <script> y <style>
        $xpath = new DOMXPath($dom);
        foreach ($xpath->query('//script|//style') as $e) {
            $e->parentNode->removeChild($e);
        }
    
        // Eliminar elementos que contienen fechas y nombres de autores
        $elementsToRemove = $xpath->query("//*[contains(@class, 'date') or contains(@class, 'fecha') or contains(@class, 'author') or contains(@class, 'autor') or contains(@id, 'date') or contains(@id, 'fecha') or contains(@id, 'author') or contains(@id, 'autor')]");
        foreach ($elementsToRemove as $e) {
            $e->parentNode->removeChild($e);
        }
    
        // Intentar extraer contenido del <article>
        $article = $dom->getElementsByTagName('article');
        if ($article->length > 0) {
            return $this->cleanText(mb_convert_encoding($article->item(0)->textContent, 'UTF-8', 'auto'));
        }
    
        // Buscar otros contenedores comunes de contenido
        $contentNodes = $xpath->query("//*[contains(@class, 'content') or contains(@id, 'content') or contains(@class, 'post') or contains(@class, 'article')]");
        if ($contentNodes->length > 0) {
            $contentText = '';
            foreach ($contentNodes as $node) {
                $contentText .= ' ' . mb_convert_encoding($node->textContent, 'UTF-8', 'auto');
            }
            return $this->cleanText(trim($contentText));
        }
    
        // Extraer texto de los párrafos como último recurso
        $paragraphs = $dom->getElementsByTagName('p');
        $contentText = '';
        foreach ($paragraphs as $paragraph) {
            $contentText .= ' ' . mb_convert_encoding($paragraph->textContent, 'UTF-8', 'auto');
        }
        return $this->cleanText(trim($contentText));
    }
    
    
    private function cleanText($text)
{
    // Eliminar comentarios HTML
    $text = preg_replace('/<!--.*?-->/s', '', $text);

    // Eliminar código JavaScript y CSS residual
    $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $text);
    $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $text);

    // Eliminar formatos comunes de fechas (e.g., "2024-11-11", "11 de Noviembre de 2024")
    $text = preg_replace('/\b\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4}\b/', '', $text); // Formatos como "11/11/2024" o "11-11-2024"
    $text = preg_replace('/\b\d{1,2}\s+de\s+(Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)\s+de\s+\d{4}\b/i', '', $text); // "11 de Noviembre de 2024"
    $text = preg_replace('/\b(?:Enero|Febrero|Marzo|Abril|Mayo|Junio|Julio|Agosto|Septiembre|Octubre|Noviembre|Diciembre)\s+\d{1,2},\s+\d{4}\b/i', '', $text); // "Noviembre 11, 2024"

    // Eliminar atribuciones de autor (e.g., "Por Juan Pérez", "Escrito por María López")
    $text = preg_replace('/\b(Por|Escrito por|Autor:)\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+(\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+)*\b/u', '', $text);

    // Eliminar frases como "Leer más", "0 comentarios", etc.
    $text = preg_replace('/\b(Leer más|Leer más »|0 comentarios|Sin comentarios|Facebook|Twitter|Flipboard|E-mail)\b/i', '', $text);

    // Eliminar etiquetas HTML restantes
    $text = strip_tags($text);

    // Eliminar espacios y líneas en blanco adicionales
    $text = preg_replace('/\s+/', ' ', $text);

    return trim($text);
}

    
    
    

      

    private function indexContentToSolr($content, $url)
    {
        $solrUrl = 'http://localhost:8983/solr/ProyectoFinal/update/?commit=true';
        $title = $this->get_title($content);
        $mainContent = $this->getMainContent($content);
        $mainContent = $this->cleanText($mainContent);
        // Asegurar que el título y el contenido están en UTF-8 antes de enviar a Solr
        $mainContent = mb_convert_encoding($mainContent, 'UTF-8', 'auto');
        $title = mb_convert_encoding($title, 'UTF-8', 'auto');

        $data_to_index = [
            'id' => uniqid(),
            'title' => $title,
            'content' => substr($mainContent, 0, 1000),
            'url' => $url,
            'keywords_s' => palabrasClave($mainContent, 20),
            'language' => lenguaje($mainContent)
        ];

        echo "<pre>";
        var_dump($data_to_index);
        echo "</pre>";

        try {
            $response = $this->client->request('POST', $solrUrl, [
                'headers' => ['Content-Type' => 'application/json; charset=UTF-8'],
                'body' => json_encode([$data_to_index], JSON_UNESCAPED_UNICODE),
            ]);
            
            return $response->getStatusCode() === 200 ? 'Datos indexados correctamente en Solr.' : 'Error al indexar datos en Solr: ' . $response->getReasonPhrase();
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

                $indexingResult = $this->indexContentToSolr($body, $url);
                echo "Resultado de indexación en Solr: $indexingResult<br>";

                $this->extractAndQueueLinks($body, $url);
            }
        } catch (RequestException $e) {
            echo "Error al obtener la URL $url: " . $e->getMessage();
        }
    }

    private function extractAndQueueLinks($content, $baseUrl)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        $links = $dom->getElementsByTagName('a');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');


            // Ignorar enlaces vacíos o con fragmentos
            if (empty($href) || strpos($href, '#') === 0) {
                continue;
            }
    
            // Resolver la URL (si es relativa, la convierte en absoluta con respecto a $baseUrl)
            $absoluteUrl = UriResolver::resolve(new Uri($baseUrl), new Uri($href))->__toString();
    
            // Validar la URL para asegurarse de que pertenece al mismo dominio y no fue visitada
            if ($this->isValidUrl($absoluteUrl) && !$this->urlAlreadyQueued($absoluteUrl)) {
                $this->urlsToCrawl[] = [$absoluteUrl, $this->getCurrentDepth($baseUrl) + 1];
            }
        }
    }

    private function resolveUrl($href, $baseUrl)
    {
        $baseUri = new Uri($baseUrl);
        $relativeUri = new Uri($href);
        return UriResolver::resolve($baseUri, $relativeUri)->__toString();
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
    $resultado = contenido($content);
    $resultado = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+/u', '', $resultado);

    $lenguaje = lenguaje($resultado);
    $stopwords = new StopwordFilter();
    $stopwords->load($lenguaje === 'es' ? 'spanish' : 'english');
    $resultado = $stopwords->clean($resultado);

    $tokens = explode(' ', $resultado);

    $tokensFiltrados = array_filter($tokens, function($token) {
        return (strlen($token) > 2 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚ]+$/', $token) && !preg_match('/http|www/', $token));
    });

    $normalizado = [];
    $inflector = Inflector::get($lenguaje);

    foreach ($tokensFiltrados as $token) {

        $normal = mb_strtolower($inflector->singularize($token)); // Singularizar cada palabra
        //$normal =$inflector->singularize($token);
        // Contar las palabras normalizadas

        if (!array_key_exists($normal, $normalizado)) {
            $normalizado[$normal] = 1;
        } else {
            $normalizado[$normal] += 1;
        }
    }

    arsort($normalizado);
    //array_unique(array_slice($normalizado, 0, $cantidad));
    $palabrasClave = array_slice($normalizado, 0, $cantidad);
    $palabrasClaveCapitalizadas = array_map(function($palabra) {
        return mb_convert_case($palabra, MB_CASE_TITLE, "UTF-8"); // Convierte la primera letra a mayúscula
    }, array_keys($palabrasClave));

    return $palabrasClaveCapitalizadas;
}

function contenido($content){
    $html = new \Html2Text\Html2Text($content);
    $contenido = $html->getText();
    $contenido = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+/u', '', $contenido);
    return $contenido;
}

use Text_LanguageDetect;

function lenguaje($contenido) {
    $contenidoLimpio = preg_replace('/[^a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+/u', '', $contenido);

    $ld = new Text_LanguageDetect();
    $ld->setNameMode(2);

    $detectedLanguage = $ld->detectSimple(substr($contenidoLimpio, 0, 3000));

    if ($detectedLanguage === null || !in_array($detectedLanguage, ['spanish', 'english'])) {
        $englishWords = ['the', 'and', 'for', 'with', 'you'];
        $spanishWords = ['el', 'y', 'para', 'con', 'tu'];

        $englishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $englishWords));
        $spanishCount = count(array_intersect(explode(' ', strtolower($contenidoLimpio)), $spanishWords));

        return $englishCount > $spanishCount ? 'en' : 'es';
    }

    echo "Lenguaje detectado: " . $detectedLanguage;
    return ($detectedLanguage === 'spanish') ? 'es' : 'en';
}

// Uso del WebCrawler
$startUrls = [

    'https://www.xataka.com.mx',
    'https://www.inegi.org.mx',
    //'https://www.usa.gov/',
    'https://www.elpalaciodehierro.com'
];
$maxDepth = 15;

foreach ($startUrls as $startUrl) {
    $crawler = new WebCrawler($startUrl, $maxDepth);
    $crawler->startCrawling();
}