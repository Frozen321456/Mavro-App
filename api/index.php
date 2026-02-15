<?php
/**
 * MoveDrop to Firestore - Complete Sync Engine 2026
 * Fix: Category Listing, Tag Support & Product Mapping
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026'); 
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', 'https://firestore.googleapis.com/v1/projects/' . PROJECT_ID . '/databases/(default)/documents/');

function verifyKey() {
    $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    $providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($providedKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized"]); exit();
    }
}

function callFirestore($path, $method = 'GET', $payload = null) {
    $url = FIRESTORE_URL . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($payload) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

verifyKey();

$path = $_GET['path'] ?? '';
$pathParts = explode('/', trim($path, '/'));
$endpoint = $pathParts[0];
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($endpoint) {
    // 1. CATEGORY SYNC
    case 'categories':
        if ($method === 'POST') {
            // MoveDrop sends { "name": "Tech" }
            $fields = ["name" => ["stringValue" => $input['name']]];
            callFirestore('categories', 'POST', ["fields" => $fields]);
            echo json_encode(["message" => "Category Created Success"]);
        }
        break;

    // 2. PRODUCT SYNC
    case 'products':
        if ($method === 'POST') {
            $productId = $pathParts[1] ?? null;
            $subAction = $pathParts[2] ?? null;

            if (!$productId) {
                // Formatting Tags for Firestore Array
                $tagValues = [];
                if (!empty($input['tags'])) {
                    foreach ($input['tags'] as $tag) {
                        $tagValues[] = ["stringValue" => $tag];
                    }
                }

                // Formatting Properties (Color, Size)
                $propertyValues = [];
                if (!empty($input['properties'])) {
                    foreach ($input['properties'] as $prop) {
                        $propertyValues[] = [
                            "mapValue" => [
                                "fields" => [
                                    "name" => ["stringValue" => $prop['name']],
                                    "values" => ["arrayValue" => ["values" => array_map(function($v){return ["stringValue"=>$v];}, $prop['values'])]]
                                ]
                            ]
                        ];
                    }
                }

                // Map MoveDrop fields to your Firestore Structure (name, price, image, etc)
                $fields = [
                    "name" => ["stringValue" => $input['title']],
                    "sku" => ["stringValue" => $input['sku']],
                    "description" => ["stringValue" => $input['description'] ?? ''],
                    "image" => ["stringValue" => $input['images'][0]['src'] ?? ''],
                    "price" => ["doubleValue" => 0], // Variations will update this later
                    "category" => ["stringValue" => "General"], // Can be dynamic based on category_ids
                    "tags" => ["arrayValue" => ["values" => $tagValues]],
                    "properties" => ["arrayValue" => ["values" => $propertyValues]],
                    "timestamp" => ["timestampValue" => gmdate("Y-m-d\TH:i:s\Z")]
                ];

                $res = callFirestore('products', 'POST', ["fields" => $fields]);
                $newFirestoreId = basename($res['name']);
                
                http_response_code(201);
                echo json_encode(["id" => $newFirestoreId, "message" => "Product Synced"]);
            }
        }
        break;

    case 'webhooks':
        echo json_encode(["status" => "webhook active"]);
        break;
}
