<?php
/**
 * MoveDrop to Firestore Bridge API 2026
 * URL: domain.com/api/index.php?path=...
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200); exit();
}

// Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026'); // MoveDrop ড্যাশবোর্ড এ এই কি-টি দিবেন
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

function postToFirestore($collection, $fields) {
    $url = FIRESTORE_URL . $collection;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["fields" => $fields]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
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
    case 'categories':
        if ($method === 'POST') {
            $fields = ["name" => ["stringValue" => $input['name']]];
            postToFirestore('categories', $fields);
            echo json_encode(["message" => "Category Added"]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            $productId = $pathParts[1] ?? null;
            $subAction = $pathParts[2] ?? null;

            if (!$productId) {
                // নতুন প্রোডাক্ট অ্যাড (MoveDrop Title -> Firestore Name)
                $fields = [
                    "name" => ["stringValue" => $input['title']],
                    "sku" => ["stringValue" => $input['sku']],
                    "description" => ["stringValue" => $input['description'] ?? ''],
                    "price" => ["doubleValue" => 0], // ভেরিয়েশন থেকে আপডেট হবে
                    "image" => ["stringValue" => $input['images'][0]['src'] ?? ''],
                    "category" => ["stringValue" => "Uncategorized"],
                    "timestamp" => ["timestampValue" => gmdate("Y-m-d\TH:i:s\Z")]
                ];
                $res = postToFirestore('products', $fields);
                $newId = basename($res['name']);
                http_response_code(201);
                echo json_encode(["id" => $newId, "message" => "Product Created"]);
            }
        }
        break;

    case 'webhooks':
        http_response_code(200);
        echo json_encode(["status" => "success"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Not Found"]);
        break;
}
