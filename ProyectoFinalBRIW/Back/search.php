<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json");

error_reporting(E_ALL);
ini_set('display_errors', 0); // Disable error display
ini_set('log_errors', 1); // Enable error logging
ini_set('error_log', '/path/to/your/log/php-error.log'); // Path to your error log file

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
                $words = str_word_count($document["content"][0], 1);
                $snippet = implode(' ', array_slice($words, 0, 10));

                return [
                    "title" => $document["title"][0],
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
            $recent_searches[] = $facets[$i];
        }

        // Return paginated results to the client
        $response = [
            "documents" => $documents,
            "recent_searches" => $recent_searches,
            "paginaActual" => $paginaActual,
            "totalPaginas" => $totalPaginas,
            "totalItems" => $totalItems
        ];

        error_log(print_r($response, true)); // Log the response for debugging

        $jsonOutput = json_encode($response);
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
    $output = curl_exec($ch);
    curl_close($ch);
    return json_decode($output, true);
}

