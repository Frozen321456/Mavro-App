<?php
// api/index.php - MoveDrop Advanced Custom Store API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'EsperaApiKey26'); 
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// Get Request Data
$method = $_SERVER['REQUEST_METHOD'];
$path = $_GET['path'] ?? '';
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

// 1. Health Check
if ($path === 'health') {
    echo json_encode(['status' => 'online', 'message' => 'MoveDrop API is running']);
    exit();
}

// 2. Auth Check
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

// Firebase Helper
function firebaseReq($ep, $m = 'GET', $d = null) {
    $url = FIREBASE_URL . $ep . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $m);
    if ($d) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($d));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// 3. Routing (Based on Postman Collection)
switch(true) {
    // Categories
    case (strpos($path, 'categories') === 0):
        if ($method === 'GET') {
            $data = firebaseReq('/categories');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $res = firebaseReq('/categories', 'POST', $input);
            echo json_encode(['message' => 'Category Created', 'id' => $res['name']]);
        }
        break;

    // Products
    case (strpos($path, 'products') === 0):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $res = firebaseReq('/products', 'POST', $input);
            echo json_encode(['message' => 'Product Sync Success', 'id' => $res['name']]);
        } elseif ($method === 'DELETE') {
            // Path structure: products/123
            $parts = explode('/', $path);
            $id = end($parts);
            firebaseReq('/products/'.$id, 'DELETE');
            echo json_encode(['message' => 'Product Deleted']);
        }
        break;

    // Orders
    case (strpos($path, 'orders') === 0):
        if ($method === 'GET') {
            $data = firebaseReq('/orders');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        } elseif ($method === 'PUT') {
            $parts = explode('/', $path);
            $id = $parts[1]; // orders/:id
            $input = json_decode(file_get_contents('php://input'), true);
            firebaseReq('/orders/'.$id, 'PATCH', $input);
            echo json_encode(['message' => 'Status Updated']);
        }
        break;

    // Webhook Registration
    case ($path === 'webhooks'):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            firebaseReq('/webhooks', 'SET', $input);
            echo json_encode(['message' => 'Webhooks Registered']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint not found']);
}
