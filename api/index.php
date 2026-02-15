<?php
/**
 * Mavro Essence - Official Final API
 * Project ID: espera-mavro-6ddc5
 * Features: Unique Category IDs, MoveDrop Product Sync, Properties Support
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// কনফিগারেশন
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ১. হেলথ চেক (API চালু আছে কি না পরীক্ষা করা) ---
if ($path === 'health') {
    echo json_encode(["status" => "online", "database" => "Firestore REST API"]);
    exit();
}

// --- ২. ক্যাটাগরি সেকশন (ID Generation Fix) ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $newCatId = 'cat_' . time(); // ইউনিক আইডি
        $payload = [
            "fields" => [
                "id" => ["stringValue" => (string)$newCatId],
                "name" => ["stringValue" => (string)$inputData['name']]
            ]
        ];
        
        $res = firestore_call("/categories?documentId=" . $newCatId, 'POST', $payload);
        
        http_response_code(201);
        echo json_encode(["status" => "success", "data" => ["id" => $newCatId, "name" => $inputData['name']]]);
    } 
    else {
        // ক্যাটাগরি লিস্ট (GET)
        $res = firestore_call("/categories", 'GET');
        $list = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $list[] = [
                    "id" => basename($doc['name']),
                    "name" => $doc['fields']['name']['stringValue'] ?? 'Unnamed'
                ];
            }
        }
        echo json_encode(["data" => $list]);
    }
    exit();
}

// --- ৩. প্রোডাক্ট লিস্টিং সেকশন (MoveDrop Success Response Fix) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');
    
    // ক্যাটাগরি এবং ট্যাগ প্রসেসিং
    $catIds = $inputData['category_ids'] ?? [];
    $catArray = [];
    foreach ($catIds as $cid) { $catArray[] = ["stringValue" => (string)$cid]; }
    
    $tagArray = [];
    foreach (($inputData['tags'] ?? []) as $tag) { $tagArray[] = ["stringValue" => (string)$tag]; }

    // প্রোপার্টিজ (Color, Size) ম্যাপিং
    $propertyValues = [];
    foreach (($inputData['properties'] ?? []) as $prop) {
        $pVals = [];
        foreach ($prop['values'] as $v) { $pVals[] = ["stringValue" => (string)$v]; }
        $propertyValues[] = ["mapValue" => ["fields" => [
            "name" => ["stringValue" => (string)$prop['name']],
            "values" => ["arrayValue" => ["values" => $pVals]]
        ]]];
    }

    // Firestore ডাটা ফরম্যাট
    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)$inputData['sku']],
            "description" => ["stringValue" => (string)($inputData['description'] ?? '')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category_ids" => ["arrayValue" => ["values" => $catArray]],
            "tags" => ["arrayValue" => ["values" => $tagArray]],
            "properties" => ["arrayValue" => ["values" => $propertyValues]],
            "created_at" => ["stringValue" => $now],
            "updated_at" => ["stringValue" => $now],
            
            // MoveDrop এর জন্য সেই নেস্টেড ফিল্ড
            "channel_association" => ["mapValue" => ["fields" => [
                "custom" => ["arrayValue" => ["values" => [["mapValue" => ["fields" => [
                    "category_ids" => ["arrayValue" => ["values" => $catArray]]
                ]]]]]]
            ]]]
        ]
    ];

    firestore_call("/products?documentId=" . $prodId, 'POST', $firestoreData);

    // মুভড্রপ স্ট্যান্ডার্ড সাকসেস রেসপন্স
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => [
            "id" => $prodId,
            "title" => $inputData['title'],
            "sku" => $inputData['sku'],
            "tags" => $inputData['tags'] ?? [],
            "created_at" => $now,
            "updated_at" => $now
        ]
    ]);
    exit();
}

// --- ৪. হেল্পার ফাংশন: Firestore CURL ---
function firestore_call($endpoint, $method, $data = null) {
    $url = FIRESTORE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// ডিফল্ট এরর
http_response_code(404);
echo json_encode(["message" => "Resource not found"]);
