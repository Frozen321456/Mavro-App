<?php
// MoveDrop Go-Live Ready API
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Authentication (X-API-KEY Header)
define('API_KEY', 'EsperaApiKey26'); 
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

// API Key Validation
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized: Invalid API Key']);
    exit();
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

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

// 2. Routing Logic for Go-Live Checklist
switch(true) {
    // Health Check
    case ($path === 'health'):
        echo json_encode(['status' => 'online', 'message' => 'MoveDrop API is running']);
        break;

    // Webhook Registration (কানেক্ট হওয়ার জন্য এটি সবচেয়ে জরুরি)
    case ($path === 'webhooks'):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            // মুভড্রপের পাঠানো ওয়েব হুক ডাটা ফায়ারবেসে সেভ করে রাখা
            firebaseReq('/webhooks_config', 'PUT', $input);
            http_response_code(201); // মুভড্রপ এই কোডটি আশা করে
            echo json_encode(['message' => 'Webhooks registered successfully']);
        }
        break;

    // Categories List
    case ($path === 'categories'):
        if ($method === 'GET') {
            $data = firebaseReq('/categories');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        } elseif ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $res = firebaseReq('/categories', 'POST', $input);
            http_response_code(201);
            echo json_encode(['message' => 'Category Created', 'id' => $res['name']]);
        }
        break;

    // Products Management
    case (strpos($path, 'products') === 0):
        if ($method === 'POST') {
            $input = json_decode(file_get_contents('php://input'), true);
            $res = firebaseReq('/products', 'POST', $input);
            http_response_code(201);
            echo json_encode(['message' => 'Product Sync Success', 'id' => $res['name']]);
        } elseif ($method === 'DELETE') {
            $parts = explode('/', $path);
            $id = end($parts);
            firebaseReq('/products/'.$id, 'DELETE');
            echo json_encode(['message' => 'Product Deleted']);
        }
        break;

    // Orders Management
    case (strpos($path, 'orders') === 0):
        if ($method === 'GET') {
            $data = firebaseReq('/orders');
            echo json_encode(['data' => $data ? array_values($data) : []]);
        } elseif ($method === 'PUT') {
            // অর্ডার স্ট্যাটাস আপডেট (MoveDrop থেকে আসবে)
            $parts = explode('/', $path);
            $id = $parts[1];
            $input = json_decode(file_get_contents('php://input'), true);
            firebaseReq('/orders/'.$id, 'PATCH', $input);
            echo json_encode(['message' => 'Order updated successfully']);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(['message' => 'Endpoint not found']);
}
