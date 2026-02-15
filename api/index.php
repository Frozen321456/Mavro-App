<?php
/**
 * Mavro Essence - MoveDrop & Firestore (Category ID Fix)
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ক্যাটাগরি সেকশন (ID ফিক্সড) ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // ১. ইউনিক আইডি জেনারেট করা (এটিই আপনার মিসিং ছিল)
        $newCatId = 'cat_' . bin2hex(random_bytes(4)); 
        
        $catData = [
            "fields" => [
                "id" => ["stringValue" => $newCatId],
                "name" => ["stringValue" => (string)$inputData['name']]
            ]
        ];

        // Firestore-এ নির্দিষ্ট আইডি দিয়ে সেভ করা
        $ch = curl_init(FIRESTORE_URL . "/categories?documentId=$newCatId");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($catData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode(["status" => "success", "data" => ["id" => $newCatId]]);
        exit();
    }
}

// --- প্রোডাক্ট লিস্টিং সেকশন (MoveDrop Success Response ফিক্সড) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');

    // Firestore Array Format for Categories & Tags
    $catArray = [];
    foreach (($inputData['category_ids'] ?? []) as $cid) { $catArray[] = ["stringValue" => (string)$cid]; }
    
    $tagArray = [];
    foreach (($inputData['tags'] ?? []) as $tag) { $tagArray[] = ["stringValue" => (string)$tag]; }

    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)$inputData['sku']],
            "description" => ["stringValue" => (string)($inputData['description'] ?? '')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category_ids" => ["arrayValue" => ["values" => $catArray]],
            "tags" => ["arrayValue" => ["values" => $tagArray]],
            "created_at" => ["stringValue" => $now],
            "updated_at" => ["stringValue" => $now],
            // MoveDrop Nested Field Fix
            "channel_association" => ["mapValue" => ["fields" => [
                "custom" => ["arrayValue" => ["values" => [["mapValue" => ["fields" => [
                    "category_ids" => ["arrayValue" => ["values" => $catArray]]
                ]]]]]]
            ]]]
        ]
    ];

    $ch = curl_init(FIRESTORE_URL . "/products?documentId=$prodId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_exec($ch);
    curl_close($ch);

    // ২. একদম আপনার দেওয়া সাকসেস রেসপন্স ফরম্যাট
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
