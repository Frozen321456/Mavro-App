<?php
// api/index.php - Official MoveDrop Custom Channel Implementation
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('API_KEY', 'EsperaApiKey26'); 
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// 1. Authentication Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid API Key']);
    exit();
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Firebase Request Helper
function callFirebase($endpoint, $method = 'GET', $data = null) {
    $url = FIREBASE_URL . $endpoint . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 2. Routing Logic
switch(true) {
    // Health Check
    case ($path === 'health'):
        echo json_encode(['status' => 'online', 'message' => 'MoveDrop Ready']);
        break;

    // Webhooks Registration (Crucial for Connection)
    case ($path === 'webhooks'):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            callFirebase('/webhooks_config', 'PUT', $input);
            http_response_code(201);
            echo json_encode(['message' => 'Webhooks registered successfully']);
        }
        break;

    // Categories Endpoint
    case ($path === 'categories'):
        if ($method === 'GET') {
            $data = callFirebase('/categories');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        }
        break;

    // Products Endpoint
    case ($path === 'products'):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $res = callFirebase('/products', 'POST', $input);
            http_response_code(201);
            echo json_encode(['message' => 'Product Created', 'id' => $res['name'] ?? 1]);
        }
        break;

    // Orders Endpoint
    case ($path === 'orders'):
        if ($method === 'GET') {
            $data = callFirebase('/orders');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            callFirebase('/orders', 'POST', $input);
            http_response_code(201);
            echo json_encode(['message' => 'Order Synced']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint Not Found']);
}
