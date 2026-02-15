<?php
/**
 * Mavro Essence - MoveDrop Firestore API (Final Fix)
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
            $catIds = $inputData['category_ids'] ?? ["uncategorized"];
            
            // MoveDrop এর সেই এররটি ফিক্স করার জন্য সঠিক ক্যাটাগরি অ্যারে ফরম্যাট
            $catArray = [];
            foreach ($catIds as $c) {
                $catArray[] = ["stringValue" => (string)$c];
            }

            // Firestore Data Structure
            $firestoreData = [
                "fields" => [
                    "id" => ["stringValue" => $prodId],
                    "title" => ["stringValue" => (string)($inputData['title'] ?? '')],
                    "name" => ["stringValue" => (string)($inputData['title'] ?? '')], // Shop.html compatibility
                    "sku" => ["stringValue" => (string)($inputData['sku'] ?? '')],
                    "price" => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
                    "image" => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
                    "category" => ["stringValue" => (string)$catIds[0]], // Single string for Shop filter
                    "category_ids" => ["arrayValue" => ["values" => $catArray]], // Array for MoveDrop
                    "description" => ["stringValue" => strip_tags($inputData['description'] ?? '')],
                    "created_at" => ["stringValue" => date('c')]
                ]
            ];

            // এই নেস্টেড অবজেক্টটি MoveDrop এর 'channel_association' এরর দূর করবে
            $firestoreData["fields"]["channel_association"] = ["mapValue" => [
                "fields" => [
                    "custom" => ["arrayValue" => ["values" => [
                        ["mapValue" => [
                            "fields" => [
                                "category_ids" => ["arrayValue" => ["values" => $catArray]]
                            ]
                        ]]
                    ]]]
                ]
            ]];

            curl_call("/products?documentId=$prodId", 'POST', $firestoreData);
            
            http_response_code(201);
            echo json_encode(["message" => "Success", "id" => $prodId]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "db" => "Firestore-REST-Fixed"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
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
