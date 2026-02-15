<?php
/**
 * Mavro Essence - MoveDrop Fix (Final Version 10.0)
 * Focusing: Strong Category Type Handling & Deep Nested Association
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

// --- ১. ক্যাটাগরি সেকশন (ID এবং Type ফিক্স) ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // মুভড্রপ অনেক সময় সংখ্যা আইডি চায়, তাই আমরা টাইমস্ট্যাম্প ব্যবহার করছি
        $rawId = time(); 
        $newCatId = (string)$rawId; 
        
        $catData = [
            "fields" => [
                "id" => ["integerValue" => $rawId], // Integer হিসেবে সেভ করা
                "name" => ["stringValue" => (string)$inputData['name']]
            ]
        ];

        firestore_call("/categories?documentId=" . $newCatId, 'POST', $catData);
        
        http_response_code(201);
        echo json_encode(["status" => "success", "data" => ["id" => $rawId, "name" => $inputData['name']]]);
    } 
    else {
        $res = firestore_call("/categories", 'GET');
        $list = [];
        if (isset($res['documents'])) {
            foreach ($res['documents'] as $doc) {
                $f = $doc['fields'];
                $list[] = [
                    "id" => isset($f['id']['integerValue']) ? (int)$f['id']['integerValue'] : basename($doc['name']),
                    "name" => $f['name']['stringValue'] ?? 'Unnamed'
                ];
            }
        }
        echo json_encode(["data" => $list]);
    }
    exit();
}

// --- ২. প্রোডাক্ট লিস্টিং (MoveDrop Association এরর ফিক্স) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');
    
    // মুভড্রপ থেকে আসা category_ids কে ইন্টিজার হিসেবে নিশ্চিত করা
    $catIds = isset($inputData['category_ids']) ? array_map('intval', $inputData['category_ids']) : [];
    if (empty($catIds)) { $catIds = [1]; } // ডিফল্ট আইডি

    // Firestore-এর জন্য ক্যাটাগরি আইডি ফরম্যাট (integerValue ব্যবহার করে)
    $catArrayValues = [];
    foreach ($catIds as $id) {
        $catArrayValues[] = ["integerValue" => $id];
    }

    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)$inputData['sku']],
            "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category_ids" => ["arrayValue" => ["values" => $catArrayValues]],
            "created_at" => ["stringValue" => $now],
            
            // এই সেই অংশ যা মুভড্রপ হুবহু খুঁজছে (Category IDs inside custom association)
            "channel_association" => [
                "mapValue" => [
                    "fields" => [
                        "custom" => [
                            "arrayValue" => [
                                "values" => [
                                    [
                                        "mapValue" => [
                                            "fields" => [
                                                "category_ids" => [
                                                    "arrayValue" => ["values" => $catArrayValues]
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
        ]
    ];

    firestore_call("/products?documentId=" . $prodId, 'POST', $firestoreData);

    // মুভড্রপ সাকসেস রেসপন্স (Strict JSON)
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => [
            "id" => $prodId,
            "title" => $inputData['title'],
            "sku" => $inputData['sku'],
            "category_ids" => $catIds,
            "created_at" => $now
        ]
    ]);
    exit();
}

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
