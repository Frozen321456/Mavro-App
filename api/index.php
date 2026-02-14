<?php
/**
 * MoveDrop Custom Channel - Final Go-Live Implementation
 * API Key: MAVRO-ESSENCE-SECURE-KEY-2026
 * Base URL: https://mavro-app.vercel.app/api
 */

// 1. Headers & Security
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// 3. Helper: Key Verification (Advanced Check)
function verifyKey() {
    $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    $providedKey = '';
    
    if (isset($headers['X-API-KEY'])) {
        $providedKey = $headers['X-API-KEY'];
    } elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
        $providedKey = $_SERVER['HTTP_X_API_KEY'];
    }

    if ($providedKey !== API_KEY) {
        http_response_code(401);
        echo json_encode([
            "status" => "error",
            "message" => "Unauthorized: Invalid API Key",
            "received" => $providedKey ? "Protected" : "None"
        ]);
        exit();
    }
}

// 4. Helper: Firebase Request
function firebase($path, $method = 'GET', $body = null) {
    $url = FIREBASE_URL . $path . '.json';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// 5. API Routing
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Health Check (Public or Protected)
if ($requestPath === 'health') {
    echo json_encode([
        "status" => "online",
        "message" => "MoveDrop API is ready",
        "api_key_configured" => true
    ]);
    exit();
}

// All other endpoints require Authentication
verifyKey();

$inputData = json_decode(file_get_contents('php://input'), true);

switch (true) {
    
    // --- WEBHOOK REGISTRATION (Crucial for Connection) ---
    case ($requestPath === 'webhooks'):
        if ($method === 'POST') {
            firebase('/webhooks_config', 'PUT', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "MoveDrop Webhooks Registered"]);
        }
        break;

    // --- CATEGORIES ---
    case ($requestPath === 'categories'):
        if ($method === 'GET') {
            $data = firebase('/categories');
            echo json_encode(["data" => $data ? array_values($data) : []]);
        } elseif ($method === 'POST') {
            $res = firebase('/categories', 'POST', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "Category Created", "id" => $res['name']]);
        }
        break;

    // --- PRODUCTS ---
    case (strpos($requestPath, 'products') === 0):
        $parts = explode('/', $requestPath);
        $productId = $parts[1] ?? null;

        if ($method === 'POST') {
            $res = firebase('/products', 'POST', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "Product Synced", "id" => $res['name']]);
        } elseif ($method === 'DELETE' && $productId) {
            firebase('/products/' . $productId, 'DELETE');
            echo json_encode(["message" => "Product Deleted"]);
        }
        break;

    // --- ORDERS ---
    case (strpos($requestPath, 'orders') === 0):
        $parts = explode('/', $requestPath);
        $orderId = $parts[1] ?? null;

        if ($method === 'GET') {
            $data = firebase('/orders');
            echo json_encode(["data" => $data ? array_values($data) : []]);
        } elseif ($method === 'PUT' && $orderId) {
            // Update Order Status (Pending/Processing/Completed)
            firebase('/orders/' . $orderId, 'PATCH', $inputData);
            echo json_encode(["message" => "Order Status Updated"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
