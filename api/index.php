<?php
/**
 * Project: Mavro Essence - Official Firestore API
 * Designed for: MoveDrop Integration & Custom Shop
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('BASE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// 1. Health Check
if ($path === 'health') {
    echo json_encode(["status" => "online", "message" => "System is fresh and ready"]);
    exit();
}

// 2. Categories Resource
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = time(); // Numeric ID for MoveDrop compatibility
        $docId = (string)$id;
        $data = [
            "fields" => [
                "id" => ["integerValue" => $id],
                "name" => ["stringValue" => (string)$inputData['name']]
            ]
        ];
        firestore_call("/categories?documentId=$docId", 'POST', $data);
        echo json_encode(["status" => "success", "data" => ["id" => $id, "name" => $inputData['name']]]);
    } else {
        $res = firestore_call("/categories", 'GET');
        $list = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $f = $doc['fields'];
                $list[] = [
                    "id" => isset($f['id']['integerValue']) ? (int)$f['id']['integerValue'] : (int)basename($doc['name']),
                    "name" => $f['name']['stringValue'] ?? 'Unnamed'
                ];
            }
        }
        echo json_encode(["data" => $list]);
    }
    exit();
}

// 3. Products Resource (MoveDrop Sync)
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = 'p_' . time();
    $now = date('c');
    
    // Category mapping logic
    $rawCats = $inputData['category_ids'] ?? [];
    $catArray = [];
    foreach ($rawCats as $c) { $catArray[] = ["integerValue" => (int)$c]; }
    if (empty($catArray)) { $catArray = [["integerValue" => 0]]; }

    // Tag mapping
    $tagArray = [];
    foreach (($inputData['tags'] ?? []) as $t) { $tagArray[] = ["stringValue" => (string)$t]; }

    // Prepare Firestore Structure
    $payload = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)$inputData['sku']],
            "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "description" => ["stringValue" => (string)($inputData['description'] ?? '')],
            "category_ids" => ["arrayValue" => ["values" => $catArray]],
            "tags" => ["arrayValue" => ["values" => $tagArray]],
            "created_at" => ["stringValue" => $now],
            "updated_at" => ["stringValue" => $now],
            // Deep association for MoveDrop validation
            "channel_association" => ["mapValue" => ["fields" => [
                "custom" => ["arrayValue" => ["values" => [["mapValue" => ["fields" => [
                    "category_ids" => ["arrayValue" => ["values" => $catArray]]
                ]]]]]]
            ]]]
        ]
    ];

    firestore_call("/products?documentId=$prodId", 'POST', $payload);

    // Response for MoveDrop
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => [
            "id" => $prodId,
            "title" => $inputData['title'],
            "sku" => $inputData['sku'],
            "category_ids" => $rawCats,
            "created_at" => $now,
            "updated_at" => $now
        ]
    ]);
    exit();
}

// Firestore Helper
function firestore_call($endpoint, $method, $data = null) {
    $ch = curl_init(BASE_URL . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
