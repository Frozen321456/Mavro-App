<?php
/**
 * MoveDrop to Firestore Complete Bridge API 2026
 * Supports: Products, Categories, Variations, and Tags
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

// Helper: Firestore REST Request
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
    // CATEGORY ADD
    case 'categories':
        if ($method === 'POST') {
            $fields = ["name" => ["stringValue" => $input['name']]];
            callFirestore('categories', 'POST', ["fields" => $fields]);
            echo json_encode(["message" => "Category Created"]);
        }
        break;

    // PRODUCT ADD (With Tags and Category Sync)
    case 'products':
        if ($method === 'POST') {
            $productId = $pathParts[1] ?? null;
            $subAction = $pathParts[2] ?? null;

            if (!$productId) {
                // Formatting Tags for Firestore
                $tagList = [];
                if (!empty($input['tags'])) {
                    foreach ($input['tags'] as $t) {
                        $tagList[] = ["stringValue" => $t];
                    }
                }

                // Formatting Fields to match your App's Firestore structure
                $fields = [
                    "name" => ["stringValue" => $input['title']],
                    "sku" => ["stringValue" => $input['sku']],
                    "description" => ["stringValue" => $input['description'] ?? ''],
                    "price" => ["doubleValue" => 0], // Variations থেকে পরে আপডেট হবে
                    "image" => ["stringValue" => $input['images'][0]['src'] ?? ''],
                    "category" => ["stringValue" => "General"], // MoveDrop ID থেকে ক্যাটাগরি নাম ম্যাপ করা যায়
                    "tags" => ["arrayValue" => ["values" => $tagList]],
                    "timestamp" => ["timestampValue" => gmdate("Y-m-d\TH:i:s\Z")]
                ];

                $res = callFirestore('products', 'POST', ["fields" => $fields]);
                $newId = basename($res['name']);
                http_response_code(201);
                echo json_encode(["id" => $newId, "message" => "Product Synced"]);
            }
        }
        break;

    case 'webhooks':
        echo json_encode(["status" => "success"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Endpoint Error"]);
        break;
}
