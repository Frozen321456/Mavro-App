<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Supports: Title, SKU, Description, Images, Categories, Tags, Properties
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
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

if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');

    // ১. Firestore Type Mapping
    $catValues = [];
    foreach (($inputData['category_ids'] ?? []) as $id) { $catValues[] = ["stringValue" => (string)$id]; }

    $tagValues = [];
    foreach (($inputData['tags'] ?? []) as $tag) { $tagValues[] = ["stringValue" => (string)$tag]; }

    // ২. Properties Mapping (Color, Size ইত্যাদি)
    $propertyValues = [];
    foreach (($inputData['properties'] ?? []) as $prop) {
        $pVals = [];
        foreach ($prop['values'] as $v) { $pVals[] = ["stringValue" => (string)$v]; }
        
        $propertyValues[] = ["mapValue" => [
            "fields" => [
                "name" => ["stringValue" => (string)$prop['name']],
                "values" => ["arrayValue" => ["values" => $pVals]]
            ]
        ]];
    }

    // ৩. Firestore Data Structure
    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)$inputData['sku']],
            "description" => ["stringValue" => (string)($inputData['description'] ?? '')],
            "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category_ids" => ["arrayValue" => ["values" => $catValues]],
            "tags" => ["arrayValue" => ["values" => $tagValues]],
            "properties" => ["arrayValue" => ["values" => $propertyValues]],
            "created_at" => ["stringValue" => $now],
            "updated_at" => ["stringValue" => $now],
            
            // MoveDrop association fix
            "channel_association" => ["mapValue" => ["fields" => [
                "custom" => ["arrayValue" => ["values" => [["mapValue" => ["fields" => [
                    "category_ids" => ["arrayValue" => ["values" => $catValues]]
                ]]]]]]
            ]]]
        ]
    ];

    // Firestore API Call
    $ch = curl_init(FIRESTORE_URL . "/products?documentId=$prodId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // ৪. Success Response (হুবহু মুভড্রপের ডকুমেন্টেশন অনুযায়ী)
    if ($httpCode == 200 || $httpCode == 201) {
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
    } else {
        http_response_code($httpCode);
        echo $res;
    }
    exit();
}