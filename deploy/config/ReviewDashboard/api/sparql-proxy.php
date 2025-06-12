<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$query = $_GET['query'] ?? '';
if (empty($query)) {
    http_response_code(400);
    echo json_encode(['error' => 'No query provided']);
    exit;
}

// Use internal Docker network with proper User-Agent
$sparqlEndpoint = 'http://wdqs:9999/bigdata/namespace/wdq/sparql';
$url = $sparqlEndpoint . '?' . http_build_query([
    'query' => $query,
    'format' => 'json'
]);

$context = stream_context_create([
    'http' => [
        'timeout' => 30,
        'method' => 'GET',
        'header' => [
            'User-Agent: ReviewDashboard/1.0 (Wikibase Extension)',
            'Accept: application/sparql-results+json'
        ]
    ]
]);

$response = file_get_contents($url, false, $context);
if ($response === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch from SPARQL endpoint']);
    exit;
}

echo $response;
?>
