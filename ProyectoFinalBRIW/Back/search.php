<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';
    $paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Numero de la pgina solicitada
    $itemsPorPagina = 10;  // Numero de resultados por paina
    $start = ($paginaActual - 1) * $itemsPorPagina; // Índice de inicio calculado segun page

    if (!empty($query)) {
        $baseurl = "http://localhost:8983/solr/ProyectoFinal/select";
        
        // Parámetros para Solr
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

        // Obtener el número total de resultados
        $totalItems = $resultado['response']['numFound'];
        $totalPaginas = ceil($totalItems / $itemsPorPagina); // Calcular el total de páginas

        // Procesar los resultados de la consulta
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

        // Obtener las palabras clave de los resultados facetados
        $facets = $resultado["facet_counts"]["facet_fields"]['keywords_s'];
        $recent_searches = [];
        for ($i = 0; $i < min(20, count($facets)); $i += 2) {
            $recent_searches[] = $facets[$i];
        }

        // Devolver los resultados paginados al cliente
        echo json_encode([
            "documents" => $documents,
            "recent_searches" => $recent_searches,
            "paginaActual" => $paginaActual,
            "totalPaginas" => $totalPaginas,
            "totalItems" => $totalItems
        ]);
        exit();
    }
}

// Función para hacer la llamada a Solr
function apiMensaje($url, $parametros)
{
    $url = $url . "?" . http_build_query($parametros);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($output, true);
    return $result;
}
