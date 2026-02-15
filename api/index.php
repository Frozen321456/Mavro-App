<?php
/**
 * MoveDrop Custom Channel - Fixed Implementation 2026
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

// 5. Pagination helper
function paginate($data, $page = 1, $perPage = 10) {
    if (!is_array($data)) $data = [];
    $data = array_values($data);
    $total = count($data);
    $offset = ($page - 1) * $perPage;
    $items = array_slice($data, $offset, $perPage);
    
    return [
        'data' => $items,
        'meta' => [
            'current_page' => (int)$page,
            'per_page' => (int)$perPage,
            'total' => $total,
            'last_page' => ceil($total / $perPage)
        ]
    ];
}

// 6. API Routing Setup
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', rtrim($requestPath, '/'));
$inputData = json_decode(file_get_contents('php://input'), true);

// Get query parameters
$page = $_GET['page'] ?? 1;
$perPage = $_GET['per_page'] ?? 10;

// Health Check
if ($requestPath === 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is ready"]);
    exit();
}

// Authentication (except health)
verifyKey();

// --- Main Router ---
switch ($pathParts[0]) {
    
    // Webhooks Endpoints
    case 'webhooks':
        if ($method === 'POST') {
            // Store webhooks in Firebase
            $webhooks = $inputData['webhooks'] ?? [];
            foreach ($webhooks as $webhook) {
                $webhook['created_at'] = date('Y-m-d H:i:s');
                firebase('/webhooks/' . uniqid(), 'PUT', $webhook);
            }
            http_response_code(201);
            echo json_encode(["message" => "MoveDrop Webhooks Registered"]);
        }
        break;

    // Categories Endpoints
    case 'categories':
        if ($method === 'GET') {
            $data = firebase('/categories');
            $result = paginate($data, $page, $perPage);
            echo json_encode($result);
        } elseif ($method === 'POST') {
            $categoryData = [
                'name' => $inputData['name'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $res = firebase('/categories', 'POST', $categoryData);
            http_response_code(201);
            echo json_encode([
                "message" => "Category Created",
                "id" => $res['name']
            ]);
        }
        break;

    // Products Endpoints
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebase('/products');
            $result = paginate($data, $page, $perPage);
            echo json_encode($result);
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // POST /products/:id/variations
                $variations = $inputData['variations'] ?? [];
                
                // Format variations for MoveDrop
                $formattedVariations = [];
                foreach ($variations as $var) {
                    $formattedVariations[] = [
                        'sku' => $var['sku'] ?? '',
                        'regular_price' => $var['regular_price'] ?? 0,
                        'sale_price' => $var['sale_price'] ?? null,
                        'date_on_sale_from' => $var['date_on_sale_from'] ?? null,
                        'date_on_sale_to' => $var['date_on_sale_to'] ?? null,
                        'stock_quantity' => $var['stock_quantity'] ?? 0,
                        'image' => $var['image'] ?? '',
                        'properties' => $var['properties'] ?? []
                    ];
                }
                
                firebase("/products/$productId/variations", 'PUT', $formattedVariations);
                echo json_encode(["message" => "Variations Updated"]);
                
            } else {
                // POST /products - Create product
                $productData = [
                    'title' => $inputData['title'] ?? '',
                    'sku' => $inputData['sku'] ?? '',
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $inputData['category_ids'] ?? [],
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $res = firebase('/products', 'POST', $productData);
                http_response_code(201);
                echo json_encode([
                    "message" => "Product Synced",
                    "id" => $res['name']
                ]);
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
            
            // Filter by order_number if provided
            if (isset($_GET['order_number']) && !empty($_GET['order_number'])) {
                $data = array_filter($data, function($order) use ($_GET) {
                    return ($order['order_number'] ?? '') === $_GET['order_number'];
                });
            }
            
            // Filter by created_at range if provided
            if (isset($_GET['created_at']) && is_array($_GET['created_at'])) {
                $start = strtotime($_GET['created_at'][0] ?? '');
                $end = strtotime($_GET['created_at'][1] ?? '');
                
                if ($start && $end) {
                    $data = array_filter($data, function($order) use ($start, $end) {
                        $created = strtotime($order['created_at'] ?? '');
                        return $created >= $start && $created <= $end;
                    });
                }
            }
            
            $result = paginate($data, $page, $perPage);
            echo json_encode($result);
            
        } elseif ($method === 'PUT' && $orderId) {
            // PUT /orders/:id (Update Status)
            $status = $inputData['status'] ?? '';
            $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
            
            if (!in_array($status, $validStatuses)) {
                http_response_code(422);
                echo json_encode(["message" => "Invalid status. Must be one of: " . implode(', ', $validStatuses)]);
                break;
            }
            
            firebase('/orders/' . $orderId, 'PATCH', [
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            echo json_encode(["message" => "Order Status Updated"]);
            
        } elseif ($method === 'POST' && $orderId && $subAction === 'timelines') {
            // POST /orders/:id/timelines
            $timeline = [
                'message' => $inputData['message'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            firebase("/orders/$orderId/timelines", 'POST', $timeline);
            http_response_code(201);
            echo json_encode(["message" => "Timeline Added"]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}