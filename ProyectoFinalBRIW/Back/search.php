<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';

    if (!empty($query)) {
        $baseurl = "http://localhost:8983/solr/ProyectoFinal/select";
        $rows = 30;
        $start = $_GET['start'] ?? 0;
        $mensaje = [
            "defType" => "lucene",
            "facet.field" => 'keywords_s',
            'facet.sort' => 'count',
            'facet' => 'true',

            'indent' => 'true',
            'q.op' => 'OR',
            'q' => "title:($query) OR content:($query) OR keywords_s:($query)",
            //'q' => "title:$query~ OR content:$query~ OR keywords_s:$query",
            'start' => 0,
            'rows' => $rows,
            'sort' => 'score desc',
            'sw' => 'true',
            'useParams' => ''
        ];

        $resultado = apiMensaje($baseurl, $mensaje);

        $facets = $resultado["facet_counts"]["facet_fields"]['keywords_s'];
        $recent_searches = [];
        for ($i = 0; $i < min(20, count($facets)); $i += 2) {
            $recent_searches[] = $facets[$i];
        }

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

        echo json_encode([
            "documents" => $documents,
            "recent_searches" => $recent_searches
        ]);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $query = $_GET['q'] ?? '';
        $categoria = $_GET['categoria'] ?? null; // Obtener la categoría seleccionada

        if (!empty($query)) {
            $baseurl = "http://localhost:8983/solr/ProyectoFinal/select";
            $rows = 30;
            $start = $_GET['start'] ?? 0;
            $mensaje = [
                // ... (resto del código de la consulta sin cambios)

                'q' => "title:($query) OR content:($query) OR keywords_s:($query)",
                //'q' => "title:$query~ OR content:$query~ OR keywords_s:$query",
                'start' => 0,
                'rows' => $rows,
                'sort' => 'score desc',
                'sw' => 'true',
                'useParams' => ''
            ];

            // Si se ha seleccionado una categoría, añadir el filtro a la consulta
            if ($categoria !== null) {
                $mensaje['fq'] = "keywords_s:$categoria";
            }

            $resultado = apiMensaje($baseurl, $mensaje);

            // Resto del código para procesar los resultados...

            // Devolver los resultados filtrados al cliente
            echo json_encode([
                "documents" => $documents,
                "recent_searches" => $recent_searches
            ]);
            exit();
        }
    }
}


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
