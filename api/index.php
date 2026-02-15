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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        return null;
    }
    return json_decode($res, true);
}

// 5. Generate slug from name
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 6. Pagination helper (MoveDrop format)
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

// 7. API Routing Setup
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

// Authentication (except health)
verifyKey();

// --- Main Router ---
switch ($pathParts[0]) {
    
    // Webhooks Endpoints
    case 'webhooks':
        if ($method === 'POST') {
            // Store webhooks in Firebase
            $webhooks = $inputData['webhooks'] ?? [];
            $storedWebhooks = [];
            
            foreach ($webhooks as $webhook) {
                $webhookId = uniqid();
                $webhookData = [
                    'name' => $webhook['name'] ?? '',
                    'event' => $webhook['event'] ?? '',
                    'delivery_url' => $webhook['delivery_url'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                firebase('/webhooks/' . $webhookId, 'PUT', $webhookData);
                $storedWebhooks[] = $webhookData;
            }
            
            http_response_code(201);
            echo json_encode([
                "message" => "MoveDrop Webhooks Registered",
                "data" => $storedWebhooks
            ]);
        }
        break;

    // Categories Endpoints
    case 'categories':
        if ($method === 'GET') {
            $data = firebase('/categories');
            if (!$data) $data = [];
            
            // Format categories for MoveDrop
            $formattedCategories = [];
            foreach ($data as $id => $category) {
                $formattedCategories[] = [
                    "id" => is_numeric($id) ? (int)$id : abs(crc32($id)),
                    "name" => $category['name'] ?? '',
                    "slug" => $category['slug'] ?? generateSlug($category['name'] ?? ''),
                    "created_at" => $category['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            $result = paginate($formattedCategories, $page, $perPage);
            echo json_encode($result);
            
        } elseif ($method === 'POST') {
            $name = $inputData['name'] ?? '';
            if (empty($name)) {
                http_response_code(422);
                echo json_encode(["message" => "Category name is required"]);
                break;
            }
            
            // Generate unique ID
            $categoryId = time();
            
            $categoryData = [
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $res = firebase('/categories/' . $categoryId, 'PUT', $categoryData);
            
            http_response_code(201);
            echo json_encode([
                "data" => [
                    "id" => $categoryId,
                    "name" => $name,
                    "slug" => generateSlug($name),
                    "created_at" => date('Y-m-d H:i:s')
                ]
            ]);
        }
        break;

    // Products Endpoints
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebase('/products');
            if (!$data) $data = [];
            
            // Format products for MoveDrop
            $formattedProducts = [];
            foreach ($data as $id => $product) {
                $formattedProducts[] = [
                    "id" => is_numeric($id) ? (int)$id : abs(crc32($id)),
                    "title" => $product['title'] ?? $product['name'] ?? '',
                    "sku" => $product['sku'] ?? '',
                    "tags" => $product['tags'] ?? [],
                    "created_at" => $product['created_at'] ?? date('Y-m-d H:i:s'),
                    "updated_at" => $product['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            $result = paginate($formattedProducts, $page, $perPage);
            echo json_encode($result);
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // POST /products/:id/variations
                $variations = $inputData['variations'] ?? [];
                
                // Check for duplicate SKUs
                $existingVariations = firebase("/products/$productId/variations") ?? [];
                $existingSkus = array_column($existingVariations, 'sku');
                
                $variationResults = [];
                foreach ($variations as $index => $var) {
                    if (in_array($var['sku'], $existingSkus)) {
                        $variationResults[] = [
                            "error" => [
                                "code" => "variation_duplicate_sku",
                                "message" => "SKU already exists.",
                                "data" => [
                                    "variation_id" => $index + 1,
                                    "sku" => $var['sku']
                                ]
                            ]
                        ];
                    } else {
                        $varId = time() + $index;
                        firebase("/products/$productId/variations/$varId", 'PUT', $var);
                        $variationResults[] = [
                            "id" => $varId,
                            "sku" => $var['sku']
                        ];
                    }
                }
                
                echo json_encode([
                    "message" => "Product Variations Created",
                    "data" => $variationResults
                ]);
                
            } else {
                // POST /products - Create product
                $title = $inputData['title'] ?? '';
                $sku = $inputData['sku'] ?? '';
                
                // Check for duplicate SKU
                $existingProducts = firebase('/products') ?? [];
                foreach ($existingProducts as $id => $prod) {
                    if (($prod['sku'] ?? '') === $sku) {
                        http_response_code(400);
                        echo json_encode([
                            "message" => "Product with given SKU already exists",
                            "data" => [
                                "error" => [
                                    "code" => "product_duplicate_sku",
                                    "message" => "SKU already exists.",
                                    "data" => [
                                        "product_id" => is_numeric($id) ? (int)$id : abs(crc32($id)),
                                        "sku" => $sku
                                    ]
                                ]
                            ]
                        ]);
                        break 2;
                    }
                }
                
                $productData = [
                    'title' => $title,
                    'sku' => $sku,
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $inputData['category_ids'] ?? [],
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $productId = time();
                $res = firebase('/products/' . $productId, 'PUT', $productData);
                
                http_response_code(201);
                echo json_encode([
                    "message" => "Product Created",
                    "data" => [
                        "id" => $productId,
                        "title" => $title,
                        "sku" => $sku,
                        "tags" => $inputData['tags'] ?? [],
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                ]);
            }
            
        } elseif ($method === 'DELETE' && $productId) {
            // DELETE /products/:id
            firebase('/products/' . $productId, 'DELETE');
            echo json_encode(["message" => "Product Deleted Successfully"]);
        }
        break;

    // Orders Endpoints
    case 'orders':
        $orderId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $data = firebase('/orders') ?? [];
            $formattedOrders = [];
            
            foreach ($data as $id => $order) {
                // Filter by order_number if provided
                if (isset($_GET['order_number']) && !empty($_GET['order_number'])) {
                    if (($order['order_number'] ?? '') !== $_GET['order_number']) {
                        continue;
                    }
                }
                
                // Filter by created_at range if provided
                if (isset($_GET['created_at']) && is_array($_GET['created_at']) && count($_GET['created_at']) == 2) {
                    $orderTime = strtotime($order['created_at'] ?? '');
                    $start = strtotime($_GET['created_at'][0]);
                    $end = strtotime($_GET['created_at'][1]);
                    
                    if ($orderTime < $start || $orderTime > $end) {
                        continue;
                    }
                }
                
                $formattedOrders[] = [
                    "id" => is_numeric($id) ? (int)$id : abs(crc32($id)),
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
            
            $result = paginate($formattedOrders, $page, $perPage);
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
            
            // Get existing order
            $order = firebase('/orders/' . $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            // Update status
            $order['status'] = $status;
            $order['updated_at'] = date('Y-m-d H:i:s');
            firebase('/orders/' . $orderId, 'PUT', $order);
            
            echo json_encode([
                "data" => [
                    "id" => is_numeric($orderId) ? (int)$orderId : abs(crc32($orderId)),
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
            // POST /orders/:id/timelines
            $message = $inputData['message'] ?? '';
            
            $timeline = [
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Get existing timelines or create new array
            $timelines = firebase("/orders/$orderId/timelines") ?? [];
            $timelines[] = $timeline;
            
            firebase("/orders/$orderId/timelines", 'PUT', $timelines);
            
            // Get updated order
            $order = firebase('/orders/' . $orderId);
            
            http_response_code(201);
            echo json_encode([
                "data" => [
                    "id" => is_numeric($orderId) ? (int)$orderId : abs(crc32($orderId)),
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
