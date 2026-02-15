<?php
/**
 * Mavro Essence - MoveDrop EXCLUSIVE FINAL FIX
 * Project ID: espera-mavro-6ddc5
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

switch ($resource) {
    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $res = curl_call('/categories', 'GET');
            $list = [];
            if (isset($res['documents'])) {
                foreach ($res['documents'] as $doc) {
                    $list[] = [
                        "id" => basename($doc['name']),
                        "name" => $doc['fields']['name']['stringValue'] ?? 'Unnamed'
                    ];
                }
            }
            // MoveDrop expects 'data' wrapper
            echo json_encode(["data" => $list]);
        } 
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = 'cat_' . time();
            $payload = [
                "fields" => [
                    "id" => ["stringValue" => $id],
                    "name" => ["stringValue" => $inputData['name'] ?? 'New Category']
                ]
            ];
            curl_call("/categories?documentId=$id", 'POST', $payload);
            echo json_encode(["data" => ["id" => $id]]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prodId = $parts[1] ?? 'prod_' . time();
            
            // ১. MoveDrop থেকে আসা ডেটা প্রসেসিং
            $catIds = $inputData['category_ids'] ?? [];
            if (empty($catIds)) { $catIds = ["uncategorized"]; }

            // ক্যাটাগরি আইডিগুলোকে Firestore array ফরম্যাটে নেওয়া
            $catValues = [];
            foreach ($catIds as $c) { $catValues[] = ["stringValue" => (string)$c]; }

            // ২. Firestore Strict Data Mapping
            $firestoreData = [
                "fields" => [
                    "id" => ["stringValue" => $prodId],
                    "title" => ["stringValue" => (string)($inputData['title'] ?? '')],
                    "name" => ["stringValue" => (string)($inputData['title'] ?? '')], // Shop.html compatibility
                    "sku" => ["stringValue" => (string)($inputData['sku'] ?? '')],
                    "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
                    "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
                    "category" => ["stringValue" => (string)$catIds[0]], // String for easy shop filtering
                    "category_ids" => ["arrayValue" => ["values" => $catValues]], // Array for MoveDrop
                    "description" => ["stringValue" => strip_tags($inputData['description'] ?? '')],
                    "created_at" => ["stringValue" => date('c')]
                ]
            ];

            // ৩. MoveDrop এর সেই নির্দিষ্ট এরর ফিক্স (channel_association mapping)
            // মুভড্রপ মূলত এই নিচের নেস্টেড স্ট্রাকচারটি ভ্যালিডেশন করে
            $firestoreData["fields"]["channel_association"] = [
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
                                                        "values" => $catValues
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

            // ৪. Firestore এ রিকোয়েস্ট পাঠানো
            $saveRes = curl_call("/products?documentId=$prodId", 'POST', $firestoreData);
            
            // ৫. মুভড্রপকে সাকসেস রেসপন্স দেওয়া
            http_response_code(201);
            echo json_encode([
                "message" => "Success",
                "id" => $prodId,
                "data" => [
                    "id" => $prodId,
                    "category_ids" => $catIds // মুভড্রপকে জানানো যে আমরা আইডি পেয়েছি
                ]
            ]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "engine" => "Firestore-MoveDrop-Final"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Resource not found"]);
        break;
}

function curl_call($path, $method, $body = null) {
    $ch = curl_init(FIRESTORE_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
