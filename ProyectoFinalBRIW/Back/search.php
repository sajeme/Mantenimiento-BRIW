<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json; charset=UTF-8");

error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error display
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', __DIR__ . '/logs/php-error.log'); // Path to your error log file

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Page number
    $itemsPorPagina = 10;  // Number of results per page
    $start = ($paginaActual - 1) * $itemsPorPagina; // Starting index

    if (!empty($query)) {
        $baseurl = "http://localhost:8983/solr/ProyectoFinal/select";
        
        // Parameters for Solr
        $mensaje = [
            "defType" => "lucene",
            "facet.field" => 'keywords_s',
            'facet.sort' => 'count',
            'facet' => 'true',
            'indent' => 'true',
            'q.op' => 'OR',
            'q' => "title:($query) OR content:($query) OR keywords_s:($query)",
            'start' => $start,
            'rows' => $itemsPorPagina,
            'sort' => 'score desc',
            'sw' => 'true',
            'useParams' => ''
        ];

        $resultado = apiMensaje($baseurl, $mensaje);

        // Get the total number of results
        $totalItems = $resultado['response']['numFound'];
        $totalPaginas = ceil($totalItems / $itemsPorPagina); // Calculate total pages

        // Process the documents in the response
        $documents = array_map(
            function ($document) {
                // Convert title and content to UTF-8 to ensure proper encoding
                $title = mb_convert_encoding($document["title"][0], 'UTF-8', 'auto');
                $content = mb_convert_encoding($document["content"][0], 'UTF-8', 'auto');

                // Create a snippet for preview by slicing the first few words
                $words = preg_split('/\s+/', $content);
                $snippet = implode(' ', array_slice($words, 0, 10));

                return [
                    "title" => $title,
                    "url" => $document["url"][0],
                    "snippet" => $snippet
                ];
            },
            $resultado["response"]['docs']
        );

        // Extract keywords from facets
        $facets = $resultado["facet_counts"]["facet_fields"]['keywords_s'];
        $recent_searches = [];
        for ($i = 0; $i < min(20, count($facets)); $i += 2) {
            $recent_searches[] = mb_convert_encoding($facets[$i], 'UTF-8', 'auto');
        }

        // Prepare response with UTF-8 encoding support in JSON
        $response = [
            "documents" => $documents,
            "recent_searches" => $recent_searches,
            "paginaActual" => $paginaActual,
            "totalPaginas" => $totalPaginas,
            "totalItems" => $totalItems
        ];

        error_log(print_r($response, true)); // Log the response for debugging

        // Encode JSON with UTF-8 support for special characters
        $jsonOutput = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($jsonOutput === false) {
            error_log('Error encoding JSON: ' . json_last_error_msg()); // Log the JSON error
            echo json_encode(['error' => 'Error generating the response']);
        } else {
            echo $jsonOutput;
        }

        exit();
    }
}

// Function to call Solr API
function apiMensaje($url, $parametros)
{
    $url = $url . "?" . http_build_query($parametros);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json; charset=UTF-8"));
    $output = curl_exec($ch);

    // Ensure UTF-8 encoding for the response from Solr
    $output = mb_convert_encoding($output, 'UTF-8', 'auto');

    curl_close($ch);
    return json_decode($output, true);
}
