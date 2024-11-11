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
    
        $article = $dom->getElementsByTagName('article');
        if ($article->length > 0) {
            return $this->cleanText(mb_convert_encoding($article->item(0)->textContent, 'UTF-8', 'auto'));
        }
    
        $divs = $dom->getElementsByTagName('div');
        foreach ($divs as $div) {
            $id = $div->getAttribute('id');
            if (strpos(strtolower($id), 'content') !== false) {
                return $this->cleanText(mb_convert_encoding($div->textContent, 'UTF-8', 'auto'));
            }
        }
    
        $paragraphs = $dom->getElementsByTagName('p');
        $contentText = '';
        foreach ($paragraphs as $paragraph) {
            $contentText .= ' ' . mb_convert_encoding($paragraph->textContent, 'UTF-8', 'auto');
        }
        return $this->cleanText(trim($contentText));
    }
    
    private function cleanText($text)
    {
        $text = preg_replace('/<script\b[^>]*>(.*?)<\/script>/isu', '', $text);
        $text = preg_replace('/<style\b[^>]*>(.*?)<\/style>/isu', '', $text);
        $text = preg_replace('/\b(background|media|min-width|max-width|url|banner-div|width|height|styles|px)\b[^;]*;/iu', '', $text);
        $text = preg_replace('/\bhttps?:\/\/\S+/iu', '', $text);
        $text = preg_replace('/\b(Tablet|Mobile|Desktop|banner-div|background-image|media|min-width|max-width|px)\b.*/iu', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        $text = strip_tags($text);
        
        return trim($text);
    }    

    private function indexContentToSolr($content, $url)
    {
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
            $absoluteUrl = strpos($href, 'http') !== false ? $href : $this->resolveUrl($href, $baseUrl);
            
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
        $normal = $inflector->singularize($token);

        if (!array_key_exists($normal, $normalizado)) {
            $normalizado[$normal] = 1;
        } else {
            $normalizado[$normal] += 1;
        }
    }

    arsort($normalizado);
    $palabrasClave = array_slice($normalizado, 0, $cantidad);
    return array_keys($palabrasClave);
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
    'https://www.elpalaciodehierro.com'
];
$maxDepth = 1;

foreach ($startUrls as $startUrl) {
    $crawler = new WebCrawler($startUrl, $maxDepth);
    $crawler->startCrawling();
}