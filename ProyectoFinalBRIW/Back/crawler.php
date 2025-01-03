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
    
        // Crear un XPath para realizar consultas más flexibles
        $xpath = new DOMXPath($dom);
    
        // Eliminar elementos que no contienen contenido relevante
        $nodesToRemove = $xpath->query('//script | //style | //noscript | //iframe | //meta | //link | //head | //object | //embed | //applet | //form | //input | //textarea | //button');
        foreach ($nodesToRemove as $node) {
            $node->parentNode->removeChild($node);
        }
    
        // Eliminar comentarios y procesamiento de instrucciones
        foreach ($xpath->query('//comment() | //processing-instruction()') as $node) {
            $node->parentNode->removeChild($node);
        }
    
        // Intentar extraer el contenido del artículo
        $contentText = '';
    
        // Lista de selectores que pueden contener el contenido principal
        $possibleContentSelectors = [
            '//main', // Etiqueta <main>
            '//article', // Etiqueta <article>
            '//section', // Etiqueta <section>
            '//div[contains(@class, "content") or contains(@class, "main") or contains(@id, "content") or contains(@id, "main")]',
            '//div[contains(@class, "wrapper") or contains(@class, "container")]',
            '//body', // Como último recurso, extraer todo el cuerpo
        ];
    
        foreach ($possibleContentSelectors as $selector) {
            $nodes = $xpath->query($selector);
            foreach ($nodes as $node) {
                $contentText .= ' ' . $node->textContent;
            }
            if (!empty(trim($contentText))) {
                break; // Si encontramos contenido, salimos del bucle
            }
        }
    
        // Si no se encuentra, concatenar todos los textos de los elementos de encabezado y párrafos
        if (empty(trim($contentText))) {
            $elements = $xpath->query('//h1 | //h2 | //h3 | //p | //li');
            foreach ($elements as $element) {
                $contentText .= ' ' . $element->textContent;
            }
        }
        if (empty(trim($contentText))) {
            error_log("No se pudo extraer contenido de la URL: " . $this->currentUrl);
            return null; // O puedes devolver una cadena vacía
        }
    
        // Limpiar el texto extraído
        return $this->cleanText($contentText);
    }
    
    
    
    
    private function cleanText($text)
    {
        // Eliminar comentarios en línea (//) y bloques (/* */)
        $text = preg_replace('#//.*#', '', $text);
        $text = preg_replace('#/\*.*?\*/#s', '', $text);
    
        // Dividir el texto en líneas para procesarlo línea por línea
        $lines = explode("\n", $text);
    
        $cleanedLines = [];
    
        foreach ($lines as $line) {
            // Eliminar espacios en blanco al inicio y final de la línea
            $line = trim($line);
    
            // Saltar líneas vacías
            if (empty($line)) {
                continue;
            }
    
            // Si la línea es demasiado corta, la omitimos
            if (strlen($line) < 20) {
                continue;
            }
    
            // Si la línea contiene patrones comunes de código, la omitimos
            if (preg_match('/[{}();<>+=\/\\\[\]|$]/', $line)) {
                continue;
            }
    
            // Si la línea contiene palabras clave de JavaScript, la omitimos
            if (preg_match('/\b(function|var|let|const|if|else|for|while|do|switch|case|break|continue|return|try|catch|throw|new|typeof|instanceof|this|class|extends|super|import|export|default|document|window|navigator|location|console|Math|Date|RegExp|Array|String|Number|Boolean|Function)\b/i', $line)) {
                continue;
            }
    
            // Si la línea tiene una alta proporción de símbolos no alfanuméricos, la omitimos
            $nonAlnumChars = preg_match_all('/[^\p{L}\p{N}\s]/u', $line);
            $totalChars = mb_strlen($line);
            if ($nonAlnumChars / $totalChars > 0.3) {
                continue;
            }
    
            // Si pasa todos los filtros, agregamos la línea a las líneas limpias
            $cleanedLines[] = $line;
        }
    
        // Unir las líneas limpias en un solo texto
        $cleanText = implode(' ', $cleanedLines);
    
        // Eliminar múltiples espacios
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);
    
        return trim($cleanText);
    }
    
    
    
    

      

    private function indexContentToSolr($content, $url)
    {
        $title = $this->get_title($content);
    $mainContent = $this->getMainContent($content);

    if (empty($title) || empty($mainContent)) {
        echo "No se encontró título o contenido para la URL: $url. Se omitirá la indexación.<br>";
        return;
    }
        $solrUrl = 'http://localhost:8983/solr/ProyectoFinal/update/?commit=true';
        $title = $this->get_title($content);
        $mainContent = $this->getMainContent($content);
        
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
        return (strlen($token) > 2 && preg_match('/^[a-zA-ZáéíóúÁÉÍÓÚñÑ]+$/u', $token));
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
    //'https://www.matematicas.uady.mx/'
    //'https://www.xbox.com/es-MX',
    'https://www.xataka.com.mx',
    //'https://www.inegi.org.mx',
    //'https://www.usa.gov/',
    //'https://www.elpalaciodehierro.com'
];
$maxDepth = 1;

foreach ($startUrls as $startUrl) {
    $crawler = new WebCrawler($startUrl, $maxDepth);
    $crawler->startCrawling();
}