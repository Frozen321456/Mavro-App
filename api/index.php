<?php
/**
 * Mavro Essence - Fixed for MoveDrop Association Error
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
    $catIds = $inputData['category_ids'] ?? ["uncategorized"];
    
    // ক্যাটাগরি আইডিগুলোকে Firestore array ফরম্যাটে নেওয়া
    $catArrayValues = [];
    foreach ($catIds as $id) {
        $catArrayValues[] = ["stringValue" => (string)$id];
    }

    // মুভড্রপ যে নেস্টেড স্ট্রাকচারটি খুঁজছে সেটি এখানে তৈরি করা হয়েছে
    $firestoreData = [
        "fields" => [
            "id" => ["stringValue" => $prodId],
            "title" => ["stringValue" => (string)$inputData['title']],
            "name" => ["stringValue" => (string)$inputData['title']],
            "sku" => ["stringValue" => (string)($inputData['sku'] ?? '')],
            "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category" => ["stringValue" => (string)$catIds[0]], // শপ পেজের জন্য
            "category_ids" => ["arrayValue" => ["values" => $catArrayValues]], // মুভড্রপের জন্য
            
            // এই সেই ফিল্ড যা না থাকলে এরর দিচ্ছে
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
            ],
            "created_at" => ["stringValue" => date('c')]
        ]
    ];

    // Firestore এ সেভ করার জন্য CURL কল
    $ch = curl_init(FIRESTORE_URL . "/products?documentId=$prodId");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    curl_close($ch);

    // মুভড্রপকে সাকসেস মেসেজ পাঠানো
    http_response_code(201);
    echo json_encode([
        "message" => "Success",
        "id" => $prodId
    ]);
    exit();
}

// ক্যাটাগরি গেট করার জন্য সাধারণ লজিক
if ($resource === 'categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $ch = curl_init(FIRESTORE_URL . "/categories");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
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
    exit();
}

http_response_code(404);
echo json_encode(["message" => "Route not found"]);
