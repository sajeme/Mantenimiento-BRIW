<?php
header("Access-Control-Allow-Origin: *");
header("Content-type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Definir la base URL para la consulta de términos
    $baseurl = "http://localhost:8983/solr/ProyectoFinal/select";

    // Parámetros de consulta para obtener las facetas de las 20 keywords más comunes
    $mensaje = [
        "q" => "*:*",   // Selecciona todos los documentos
        "rows" => 0,    // No necesitamos los documentos, solo el conteo de facetas
        "facet" => "true",
        "facet.field" => "keywords_s",    // Campo para obtener las keywords más comunes
        "facet.limit" => 20,              // Limitar a los 20 términos más comunes
        "facet.sort" => "count",          // Ordenar por la cantidad de documentos en los que aparece
        "facet.mincount" => 1             // Solo términos que aparecen en al menos un documento
    ];

    // Llamar a la API y obtener el resultado
    $resultado = apiMensaje($baseurl, $mensaje);

    // Obtener y procesar las facetas para mostrar las keywords más comunes y sus conteos en documentos
    $terms = $resultado["facet_counts"]["facet_fields"]["keywords_s"] ?? [];
    $keywords_with_doc_counts = [];

    for ($i = 0; $i < count($terms); $i += 2) {
        $keyword = $terms[$i];
        
        // Contar documentos donde la keyword aparece en title, content, o keywords_s
        $mensaje_contador = [
            "q" => "title:($keyword) OR content:($keyword) OR keywords_s:($keyword)",
            "rows" => 0   // No necesitamos los documentos, solo el conteo
        ];

        $contador_resultado = apiMensaje($baseurl, $mensaje_contador);
        $document_count = $contador_resultado['response']['numFound'];

        $keywords_with_doc_counts[] = [
            "keyword" => $keyword,
            "count" => $document_count  // Número de documentos que contienen esta keyword
        ];
    }

    // Ordenar el array por el campo 'count' en orden descendente
    usort($keywords_with_doc_counts, function ($a, $b) {
        return $b['count'] <=> $a['count'];
    });

    // Devolver el resultado en formato JSON
    echo json_encode([
        "keywords" => $keywords_with_doc_counts
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