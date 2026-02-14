<?php
// api/index.php - MoveDrop Complete API Bridge
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('API_KEY', 'EsperaApiKey26'); 
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// Helper to communicate with Firebase
function firebaseRequest($endpoint, $method = 'GET', $data = null) {
    $url = FIREBASE_URL . $endpoint . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Get Path from URL
$path = $_GET['path'] ?? '';

// 1. Health Check (Public)
if ($path === 'health') {
    echo json_encode(['status' => 'online', 'message' => 'API Bridge Ready']);
    exit();
}

// 2. API Key Security
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

// 3. API Routes
$method = $_SERVER['REQUEST_METHOD'];

if ($path === 'products') {
    if ($method === 'GET') {
        $data = firebaseRequest('/products');
        echo json_encode(['data' => $data ? array_values($data) : []]);
    }
} 
elseif ($path === 'orders') {
    if ($method === 'GET') {
        $data = firebaseRequest('/orders');
        echo json_encode(['data' => $data ? array_values($data) : []]);
    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $res = firebaseRequest('/orders', 'POST', $input);
        echo json_encode(['message' => 'Success', 'id' => $res['name'] ?? '']);
    }
} else {
    echo json_encode(['message' => 'Invalid Endpoint']);
}

