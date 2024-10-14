<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Definir la base URL para la consulta de términos
    $baseurl = "http://localhost:8983/solr/ProyectoFinal/terms";
    
    // Parámetros de la consulta Solr (para keywords)
    $mensaje = [
        "terms.fl" => "keywords_s",
        "terms.sort" => "count",
        "terms.limit" => 20,
        "terms.mincount" => 1
    ];

    // Hacer la consulta a Solr
    $resultado = apiMensaje($baseurl, $mensaje);

    // Procesar los términos obtenidos de Solr
    $terms = $resultado["terms"]["keywords_s"] ?? [];
    $keywords_with_counts = [];
    
    // Los términos vienen en pares: [keyword, count]
    for ($i = 0; $i < count($terms); $i += 2) {
        $keywords_with_counts[] = [
            "keyword" => $terms[$i],
            "count" => $terms[$i + 1]
        ];
    }

    // Devolver las keywords junto con sus conteos como respuesta JSON
    echo json_encode([
        "keywords" => $keywords_with_counts
    ]);
    exit();
}

/**
 * Función para realizar una solicitud a la API de Solr
 */
function apiMensaje($url, $parametros)
{
    // Construir la URL con los parámetros de la consulta
    $url = $url . "?" . http_build_query($parametros);
    
    // Iniciar una sesión cURL para hacer la solicitud
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $output = curl_exec($ch);
    curl_close($ch);
    
    // Decodificar la respuesta JSON de Solr
    $result = json_decode($output, true);
    return $result;
}