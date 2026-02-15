<?php
/**
 * Mavro Essence - Firestore API for MoveDrop
 * Collection Names: 'products', 'categories'
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
// Firestore REST API URL (Project ID: espera-mavro-6ddc5)
define('FIRESTORE_URL', 'https://firestore.googleapis.com/v1/projects/espera-mavro-6ddc5/databases/(default)/documents');

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];
$inputData = json_decode(file_get_contents('php://input'), true);
$pathInfo = $_GET['path'] ?? '';
$parts = explode('/', trim($pathInfo, '/'));
$resource = $parts[0] ?? ''; // categories or products
$id = $parts[1] ?? null;

// Helper: Firestore API Request
function firestore($path, $method = 'GET', $fields = null) {
    $url = FIRESTORE_URL . $path;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($fields) {
        // Firestore REST API expects data in a specific 'fields' format
        $data = ["fields" => []];
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                $data["fields"][$key] = ["arrayValue" => ["values" => array_map(function($v){ return ["stringValue" => (string)$v]; }, $value)]];
            } else {
                $data["fields"][$key] = ["stringValue" => (string)$value];
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

switch ($resource) {
    case 'categories':
        if ($method === 'GET') {
            $res = firestore('/categories');
            $list = [];
            if (isset($res['documents'])) {
                foreach ($res['documents'] as $doc) {
                    $fields = $doc['fields'];
                    $list[] = [
                        "id" => basename($doc['name']),
                        "name" => $fields['name']['stringValue'] ?? ''
                    ];
                }
            }
            echo json_encode(["data" => $list]);
        } 
        elseif ($method === 'POST') {
            $catId = 'cat_' . time();
            $data = ['id' => $catId, 'name' => $inputData['name'] ?? 'New Cat'];
            firestore('/categories?documentId=' . $catId, 'POST', $data);
            echo json_encode(["data" => $data]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            $categoryIds = $inputData['category_ids'] ?? ["uncategorized"];
            $productId = $id ?? 'prod_' . time();

            // Firestore Structure (MoveDrop + Shop.html Compatibility)
            $productFields = [
                'id' => $productId,
                'name' => $inputData['title'] ?? '',
                'title' => $inputData['title'] ?? '',
                'sku' => $inputData['sku'] ?? '',
                'price' => $inputData['regular_price'] ?? '0',
                'image' => $inputData['images'][0]['src'] ?? '',
                'category' => (string)$categoryIds[0], // Firestore এর জন্য string
                'category_ids' => $categoryIds,         // MoveDrop এর জন্য array
                'description' => strip_tags($inputData['description'] ?? '')
            ];

            firestore('/products?documentId=' . $productId, 'POST', $productFields);
            
            http_response_code(201);
            echo json_encode(["message" => "Created", "data" => ["id" => $productId]]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "database" => "Firestore"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
