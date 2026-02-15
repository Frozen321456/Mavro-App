<?php
/**
 * MoveDrop Custom Channel - Complete Working Version
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
define('FIREBASE_PROJECT_ID', 'espera-mavro-6ddc5');
define('FIREBASE_API_KEY', 'AIzaSyAB7dyaJwkadV7asGOhj6TCN5it5pCWg10');
define('FIREBASE_DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

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

// 4. Firebase Realtime Database Request (More reliable than Firestore REST)
function firebaseRequest($path, $method = 'GET', $data = null) {
    $url = FIREBASE_DATABASE_URL . $path . '.json?auth=' . FIREBASE_API_KEY;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Firebase $method $path - HTTP $httpCode");
    
    if ($httpCode >= 400) {
        return null;
    }
    
    return json_decode($response, true);
}

// 5. Generate slug
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 6. Pagination helper
function paginate($data, $page = 1, $perPage = 20) {
    if (!is_array($data)) $data = [];
    $data = array_values($data);
    $total = count($data);
    $offset = ($page - 1) * $perPage;
    $items = array_slice($data, $offset, $perPage);
    
    return [
        "data" => $items,
        "meta" => [
            "current_page" => (int)$page,
            "from" => $offset + 1,
            "last_page" => ceil($total / $perPage),
            "per_page" => (int)$perPage,
            "to" => min($offset + $perPage, $total),
            "total" => $total
        ]
    ];
}

// 7. API Routing
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', rtrim($requestPath, '/'));
$inputData = json_decode(file_get_contents('php://input'), true);

// Get query parameters
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;

// Health Check
if ($requestPath === 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is ready"]);
    exit();
}

// Authentication
verifyKey();

error_log("MoveDrop API: $method $requestPath");

// --- Router ---
switch ($pathParts[0]) {
    
    // Webhooks
    case 'webhooks':
        if ($method === 'POST') {
            $webhooks = $inputData['webhooks'] ?? [];
            $results = [];
            
            foreach ($webhooks as $webhook) {
                $id = uniqid();
                $webhookData = [
                    'name' => $webhook['name'] ?? '',
                    'event' => $webhook['event'] ?? '',
                    'delivery_url' => $webhook['delivery_url'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $response = firebaseRequest('/webhooks/' . $id, 'PUT', $webhookData);
                if ($response) {
                    $results[] = $webhookData;
                }
            }
            
            http_response_code(201);
            echo json_encode([
                "message" => "Webhooks Registered",
                "data" => $results
            ]);
        }
        break;

    // Categories
    case 'categories':
        if ($method === 'GET') {
            $data = firebaseRequest('/categories');
            $categories = [];
            
            if (is_array($data)) {
                foreach ($data as $id => $cat) {
                    $categories[] = [
                        "id" => $id,
                        "name" => $cat['name'] ?? '',
                        "slug" => $cat['slug'] ?? generateSlug($cat['name'] ?? ''),
                        "created_at" => $cat['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            echo json_encode(paginate($categories, $page, $perPage));
            
        } elseif ($method === 'POST') {
            $name = $inputData['name'] ?? '';
            if (!$name) {
                http_response_code(422);
                echo json_encode(["message" => "Category name required"]);
                break;
            }
            
            $id = time(); // Use timestamp as ID
            $categoryData = [
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $response = firebaseRequest('/categories/' . $id, 'PUT', $categoryData);
            
            if ($response) {
                http_response_code(201);
                echo json_encode([
                    "data" => [
                        "id" => $id,
                        "name" => $name,
                        "slug" => generateSlug($name),
                        "created_at" => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create category"]);
            }
        }
        break;

    // Products
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebaseRequest('/products');
            $products = [];
            
            if (is_array($data)) {
                foreach ($data as $id => $prod) {
                    $products[] = [
                        "id" => $id,
                        "title" => $prod['title'] ?? $prod['name'] ?? '',
                        "sku" => $prod['sku'] ?? '',
                        "tags" => $prod['tags'] ?? [],
                        "created_at" => $prod['created_at'] ?? date('Y-m-d H:i:s'),
                        "updated_at" => $prod['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            echo json_encode(paginate($products, $page, $perPage));
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // Add variations to existing product
                $variations = $inputData['variations'] ?? [];
                
                // Get existing product
                $product = firebaseRequest('/products/' . $productId);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(["message" => "Product not found"]);
                    break;
                }
                
                $product['variations'] = $variations;
                $product['updated_at'] = date('Y-m-d H:i:s');
                
                firebaseRequest('/products/' . $productId, 'PUT', $product);
                
                $results = [];
                foreach ($variations as $index => $var) {
                    $results[] = [
                        "id" => $index + 1,
                        "sku" => $var['sku'] ?? ''
                    ];
                }
                
                echo json_encode([
                    "message" => "Variations Created",
                    "data" => $results
                ]);
                
            } else {
                // Create new product
                $title = $inputData['title'] ?? '';
                $sku = $inputData['sku'] ?? '';
                $category_ids = $inputData['category_ids'] ?? [];
                
                if (!$title || !$sku) {
                    http_response_code(422);
                    echo json_encode(["message" => "Title and SKU required"]);
                    break;
                }
                
                // Ensure category_ids is an array of numbers
                if (empty($category_ids)) {
                    $category_ids = [1]; // Default category
                }
                
                $id = time(); // Use timestamp as ID
                $productData = [
                    'title' => $title,
                    'sku' => $sku,
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $category_ids,
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $response = firebaseRequest('/products/' . $id, 'PUT', $productData);
                
                if ($response) {
                    http_response_code(201);
                    echo json_encode([
                        "message" => "Product Created",
                        "data" => [
                            "id" => $id,
                            "title" => $title,
                            "sku" => $sku,
                            "tags" => $inputData['tags'] ?? [],
                            "created_at" => date('Y-m-d H:i:s'),
                            "updated_at" => date('Y-m-d H:i:s')
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to create product"]);
                }
            }
            
        } elseif ($method === 'DELETE' && $productId) {
            firebaseRequest('/products/' . $productId, 'DELETE');
            echo json_encode(["message" => "Product Deleted Successfully"]);
        }
        break;

    // Orders
    case 'orders':
        $orderId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebaseRequest('/orders');
            $orders = [];
            
            if (is_array($data)) {
                foreach ($data as $id => $order) {
                    // Filter by order number if provided
                    if (isset($_GET['order_number']) && ($order['order_number'] ?? '') !== $_GET['order_number']) {
                        continue;
                    }
                    
                    $orders[] = [
                        "id" => $id,
                        "order_number" => $order['order_number'] ?? '',
                        "status" => $order['status'] ?? 'pending',
                        "currency" => $order['currency'] ?? 'BDT',
                        "total" => $order['total'] ?? '0',
                        "payment_method" => $order['payment_method'] ?? 'cod',
                        "shipping_address" => $order['shipping_address'] ?? [],
                        "customer_notes" => $order['customer_notes'] ?? '',
                        "line_items" => $order['line_items'] ?? [],
                        "created_at" => $order['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            echo json_encode(paginate($orders, $page, $perPage));
            
        } elseif ($method === 'PUT' && $orderId) {
            $status = $inputData['status'] ?? '';
            $valid = ['pending', 'processing', 'completed', 'cancelled'];
            
            if (!in_array($status, $valid)) {
                http_response_code(422);
                echo json_encode(["message" => "Invalid status"]);
                break;
            }
            
            $order = firebaseRequest('/orders/' . $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $order['status'] = $status;
            $order['updated_at'] = date('Y-m-d H:i:s');
            
            firebaseRequest('/orders/' . $orderId, 'PUT', $order);
            
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $order['order_number'] ?? '',
                    "status" => $status,
                    "currency" => $order['currency'] ?? 'BDT',
                    "total" => $order['total'] ?? '0',
                    "payment_method" => $order['payment_method'] ?? 'cod',
                    "shipping_address" => $order['shipping_address'] ?? [],
                    "customer_notes" => $order['customer_notes'] ?? '',
                    "line_items" => $order['line_items'] ?? [],
                    "created_at" => $order['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
            
        } elseif ($method === 'POST' && $orderId && $subAction === 'timelines') {
            $message = $inputData['message'] ?? '';
            
            $order = firebaseRequest('/orders/' . $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $timeline = [
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (!isset($order['timelines'])) {
                $order['timelines'] = [];
            }
            $order['timelines'][] = $timeline;
            $order['updated_at'] = date('Y-m-d H:i:s');
            
            firebaseRequest('/orders/' . $orderId, 'PUT', $order);
            
            http_response_code(201);
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $order['order_number'] ?? '',
                    "status" => $order['status'] ?? 'pending',
                    "currency" => $order['currency'] ?? 'BDT',
                    "total" => $order['total'] ?? '0',
                    "payment_method" => $order['payment_method'] ?? 'cod',
                    "shipping_address" => $order['shipping_address'] ?? [],
                    "customer_notes" => $order['customer_notes'] ?? '',
                    "line_items" => $order['line_items'] ?? [],
                    "created_at" => $order['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
