<?php
/**
 * MoveDrop Custom Channel - Updated Implementation 2026
 * API Key: MAVRO-ESSENCE-SECURE-KEY-2026
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

// 3. Helper: Key Verification
function verifyKey() {
    $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    $providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

    if ($providedKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized: Invalid API Key"]);
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

// 5. API Routing Setup
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', rtrim($requestPath, '/'));
$inputData = json_decode(file_get_contents('php://input'), true);

// Health Check
if ($requestPath === 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is ready"]);
    exit();
}

// Authentication
verifyKey();

// --- Main Router ---
switch ($pathParts[0]) {
    
    // Webhooks Endpoints
    case 'webhooks':
        if ($method === 'POST') {
            firebase('/webhooks_config', 'PUT', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "MoveDrop Webhooks Registered"]);
        }
        break;

    // Categories Endpoints
    case 'categories':
        if ($method === 'GET') {
            $data = firebase('/categories');
            // Pagination handle (Optional but recommended)
            echo json_encode(["data" => $data ? array_values($data) : []]);
        } elseif ($method === 'POST') {
            $res = firebase('/categories', 'POST', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "Category Created", "id" => $res['name']]);
        }
        break;

    // Products Endpoints
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // POST /products/:id/variations
                firebase("/products/$productId/variations", 'PUT', $inputData['variations']);
                echo json_encode(["message" => "Variations Updated"]);
            } else {
                // POST /products
                $res = firebase('/products', 'POST', $inputData);
                http_response_code(201);
                echo json_encode(["message" => "Product Synced", "id" => $res['name']]);
            }
        } elseif ($method === 'DELETE' && $productId) {
            // DELETE /products/:id
            firebase('/products/' . $productId, 'DELETE');
            echo json_encode(["message" => "Product Deleted"]);
        }
        break;

    // Orders Endpoints
    case 'orders':
        $orderId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebase('/orders');
            echo json_encode(["data" => $data ? array_values($data) : []]);
        } elseif ($method === 'PUT' && $orderId) {
            // PUT /orders/:id (Update Status)
            firebase('/orders/' . $orderId, 'PATCH', $inputData);
            echo json_encode(["message" => "Order Status Updated"]);
        } elseif ($method === 'POST' && $orderId && $subAction === 'timelines') {
            // POST /orders/:id/timelines
            firebase("/orders/$orderId/timelines", 'POST', $inputData);
            http_response_code(201);
            echo json_encode(["message" => "Timeline Added"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
