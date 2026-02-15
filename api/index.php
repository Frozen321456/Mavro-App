<?php
/**
 * Mavro Essence - MoveDrop Final Fix
 * Firestore REST API Implementation
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';
$parts = explode('/', trim($path, '/'));
$resource = $parts[0] ?? '';

if ($resource === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = $parts[1] ?? 'prod_' . time();
    $catIds = $inputData['category_ids'] ?? [];
    
    // Firestore Array Format for Category IDs
    $catArrayValues = [];
    foreach ($catIds as $id) {
        $catArrayValues[] = ["stringValue" => (string)$id];
    }

    // Firestore Array Format for Tags
    $tagArrayValues = [];
    foreach ($inputData['tags'] as $tag) {
        $tagArrayValues[] = ["stringValue" => (string)$tag];
    }

    // মুভড্রপ যে নেস্টেড স্ট্রাকচারটি খুঁজছে (The Missing Link)
    $channelAssociation = [
        "mapValue" => [
            "fields" => [
                "custom" => [
                    "arrayValue" => [
                        "values" => [
                            [
                                "mapValue" => [
                                    "fields" => [
                                        "category_ids" => [
                                            "arrayValue" => [
                                                "values" => $catArrayValues
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ];

    // সম্পূর্ণ ফায়ারস্টোর ডেটা ফরম্যাট
    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "name" => ["stringValue" => (string)$inputData['title']], // Shop compatibility
            "sku" => ["stringValue" => (string)($inputData['sku'] ?? '')],
            "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "description" => ["stringValue" => (string)$inputData['description']],
            "category" => ["stringValue" => (string)($catIds[0] ?? 'uncategorized')],
            "category_ids" => ["arrayValue" => ["values" => $catArrayValues]],
            "tags" => ["arrayValue" => ["values" => $tagArrayValues]],
            "channel_association" => $channelAssociation, // এটিই সেই রিকোয়ার্ড ফিল্ড
            "created_at" => ["stringValue" => date('c')]
        ]
    ];

    // Firestore REST API Call
    $ch = curl_init(FIRESTORE_URL . "/products?documentId=$prodId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($info['http_code'] == 200 || $info['http_code'] == 201) {
        http_response_code(201);
        echo json_encode(["status" => "success", "id" => $prodId]);
    } else {
        http_response_code($info['http_code']);
        echo $res; // ফায়ারবেস থেকে আসা এরর দেখাবে
    }
    exit();
}
