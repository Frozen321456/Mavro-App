<?php
/**
 * Mavro Essence - MoveDrop EXCLUSIVE API
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

// আপনার Firestore প্রজেক্ট আইডি নিশ্চিত করুন
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5'); 
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// সিকিউরিটি চেক
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
            // Firestore থেকে ক্যাটাগরি আনা
            $res = curl_request('/categories', 'GET');
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
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // মুভড্রপ থেকে নতুন ক্যাটাগরি তৈরি
            $id = 'cat_' . time();
            $data = [
                "fields" => [
                    "id" => ["stringValue" => $id],
                    "name" => ["stringValue" => $inputData['name'] ?? 'New Category']
                ]
            ];
            curl_request("/categories?documentId=$id", 'POST', $data);
            echo json_encode(["data" => ["id" => $id]]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prodId = $parts[1] ?? 'prod_' . time();
            $catIds = $inputData['category_ids'] ?? ["uncategorized"];
            $tags = $inputData['tags'] ?? [];
            
            // ছবি হ্যান্ডলিং
            $imgUrl = "";
            if (!empty($inputData['images']) && isset($inputData['images'][0]['src'])) {
                $imgUrl = $inputData['images'][0]['src'];
            }

            // Firestore Fields (Strict Format)
            $catValues = [];
            foreach ($catIds as $c) { $catValues[] = ["stringValue" => (string)$c]; }
            
            $tagValues = [];
            foreach ($tags as $t) { $tagValues[] = ["stringValue" => (string)$t]; }

            $firestoreData = [
                "fields" => [
                    "id" => ["stringValue" => $prodId],
                    "title" => ["stringValue" => (string)$inputData['title']],
                    "name" => ["stringValue" => (string)$inputData['title']], // For shop.html
                    "sku" => ["stringValue" => (string)($inputData['sku'] ?? '')],
                    "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
                    "image" => ["stringValue" => $imgUrl],
                    "category" => ["stringValue" => (string)$catIds[0]],
                    "category_ids" => ["arrayValue" => ["values" => $catValues]],
                    "tags" => ["arrayValue" => ["values" => $tagValues]],
                    "description" => ["stringValue" => strip_tags($inputData['description'] ?? '')],
                    "created_at" => ["stringValue" => date('c')]
                ]
            ];

            // এই ফিল্ডটি মুভড্রপ এরর ফিক্স করার জন্য জরুরি
            $firestoreData["fields"]["channel_association"] = ["stringValue" => "custom"];

            curl_request("/products?documentId=$prodId", 'POST', $firestoreData);
            
            http_response_code(201);
            echo json_encode(["message" => "Success", "id" => $prodId]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "db" => "Firestore REST"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint Not Found"]);
        break;
}

// CURL Helper Function
function curl_request($path, $method, $body = null) {
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
