<?php
// api/index.php - MoveDrop Complete API
// এই ফাইলটি আপনার সাইটের api ফোল্ডারে আপলোড করুন

// CORS Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");

// Handle Preflight Requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('API_KEY', 'EsperaApiKey26'); // MoveDrop এ এই Key দিবেন
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');
define('FIREBASE_SECRET', ''); // যদি Firebase Secret needed হয়

// API Key Verification
function verifyApiKey() {
    $headers = getallheaders();
    $apiKey = $headers['X-API-KEY'] ?? '';
    
    if ($apiKey !== API_KEY) {
        http_response_code(401);
        echo json_encode([
            'message' => 'Unauthorized',
            'error' => 'Invalid API Key'
        ]);
        exit();
    }
}

// Firebase Request Helper
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
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'data' => json_decode($response, true),
        'code' => $httpCode
    ];
}

// Verify API Key for all requests
verifyApiKey();

// Parse request path
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// ============================================
// 1. CATEGORIES ENDPOINTS
// ============================================
if ($requestPath === 'categories') {
    
    // GET /categories - List all categories
    if ($method === 'GET') {
        $page = $_GET['page'] ?? 1;
        $per_page = $_GET['per_page'] ?? 10;
        
        // Get categories from Firebase
        $result = firebaseRequest('/categories');
        $categories = $result['data'] ? array_values($result['data']) : [];
        
        // Format for MoveDrop
        $formatted = [];
        foreach ($categories as $id => $cat) {
            $formatted[] = [
                'id' => $id,
                'name' => $cat['name'] ?? '',
                'slug' => strtolower(str_replace(' ', '-', $cat['name'] ?? '')),
                'created_at' => $cat['created_at'] ?? date('Y-m-d H:i:s')
            ];
        }
        
        // Pagination
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($formatted, $offset, $per_page);
        
        echo json_encode(['data' => $paginated]);
        exit();
    }
    
    // POST /categories - Create new category
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['name']) || empty($input['name'])) {
            http_response_code(422);
            echo json_encode([
                'message' => 'Validation failed',
                'errors' => ['name' => 'Category name is required']
            ]);
            exit();
        }
        
        // Create category in Firebase
        $categoryData = [
            'name' => $input['name'],
            'slug' => strtolower(str_replace(' ', '-', $input['name'])),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = firebaseRequest('/categories', 'POST', $categoryData);
        
        if ($result['code'] === 200) {
            http_response_code(201);
            echo json_encode([
                'data' => [
                    'id' => $result['data']['name'], // Firebase push returns name as ID
                    'name' => $categoryData['name'],
                    'slug' => $categoryData['slug'],
                    'created_at' => $categoryData['created_at']
                ]
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create category']);
        }
        exit();
    }
}

// ============================================
// 2. PRODUCTS ENDPOINTS
// ============================================
if ($requestPath === 'products') {
    
    // GET /products - List products with pagination
    if ($method === 'GET') {
        $page = (int)($_GET['page'] ?? 1);
        $per_page = (int)($_GET['per_page'] ?? 10);
        
        $result = firebaseRequest('/products');
        $products = $result['data'] ? array_values($result['data']) : [];
        
        // Format products for MoveDrop
        $formatted = [];
        foreach ($products as $id => $product) {
            $formatted[] = [
                'id' => $id,
                'title' => $product['name'] ?? '',
                'sku' => $product['sku'] ?? 'SKU-' . $id,
                'description' => $product['description'] ?? '',
                'images' => array_map(function($img) {
                    return ['src' => $img, 'default' => false];
                }, $product['images'] ?? [$product['image'] ?? '']),
                'category_ids' => $product['category_ids'] ?? [],
                'tags' => $product['tags'] ?? [],
                'created_at' => $product['created_at'] ?? date('Y-m-d H:i:s'),
                'updated_at' => $product['updated_at'] ?? date('Y-m-d H:i:s')
            ];
        }
        
        // Pagination
        $total = count($formatted);
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($formatted, $offset, $per_page);
        
        echo json_encode([
            'data' => $paginated,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per_page,
                'last_page' => ceil($total / $per_page)
            ]
        ]);
        exit();
    }
    
    // POST /products - Create new product
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $errors = [];
        if (!isset($input['title']) || empty($input['title'])) {
            $errors['title'] = 'Product title is required';
        }
        if (!isset($input['sku']) || empty($input['sku'])) {
            $errors['sku'] = 'SKU is required';
        }
        
        if (!empty($errors)) {
            http_response_code(422);
            echo json_encode([
                'message' => 'Validation failed',
                'errors' => $errors
            ]);
            exit();
        }
        
        // Check for duplicate SKU
        $result = firebaseRequest('/products');
        $existingProducts = $result['data'] ?? [];
        
        foreach ($existingProducts as $id => $product) {
            if (isset($product['sku']) && $product['sku'] === $input['sku']) {
                http_response_code(400);
                echo json_encode([
                    'message' => 'Product with given SKU already exists',
                    'data' => [
                        'error' => [
                            'code' => 'product_duplicate_sku',
                            'message' => 'SKU already exists.',
                            'data' => [
                                'product_id' => $id,
                                'sku' => $input['sku']
                            ]
                        ]
                    ]
                ]);
                exit();
            }
        }
        
        // Prepare product data
        $productData = [
            'name' => $input['title'],
            'sku' => $input['sku'],
            'description' => $input['description'] ?? '',
            'price' => $input['price'] ?? '0',
            'images' => array_map(function($img) {
                return $img['src'] ?? '';
            }, $input['images'] ?? []),
            'category_ids' => $input['category_ids'] ?? [],
            'tags' => $input['tags'] ?? [],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to Firebase
        $saveResult = firebaseRequest('/products', 'POST', $productData);
        
        if ($saveResult['code'] === 200) {
            http_response_code(201);
            echo json_encode([
                'message' => 'Product Created',
                'data' => [
                    'id' => $saveResult['data']['name'],
                    'title' => $productData['name'],
                    'sku' => $productData['sku'],
                    'tags' => $productData['tags'],
                    'created_at' => $productData['created_at'],
                    'updated_at' => $productData['updated_at']
                ]
            ]);
            
            // Trigger webhook for product creation
            triggerWebhook('product.created', [
                'id' => $saveResult['data']['name'],
                'title' => $productData['name'],
                'sku' => $productData['sku']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to create product']);
        }
        exit();
    }
}

// ============================================
// 3. PRODUCT VARIATIONS ENDPOINTS
// ============================================
if (preg_match('/^products\/([^\/]+)\/variations$/', $requestPath, $matches)) {
    $productId = $matches[1];
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['variations']) || empty($input['variations'])) {
            http_response_code(422);
            echo json_encode(['message' => 'Variations are required']);
            exit();
        }
        
        // Get existing product
        $result = firebaseRequest('/products/' . $productId);
        $product = $result['data'];
        
        if (!$product) {
            http_response_code(404);
            echo json_encode(['message' => 'Product not found']);
            exit();
        }
        
        // Add variations to product
        $variations = [];
        foreach ($input['variations'] as $var) {
            $variations[] = [
                'sku' => $var['sku'] ?? '',
                'regular_price' => $var['regular_price'] ?? '0',
                'sale_price' => $var['sale_price'] ?? null,
                'stock_quantity' => $var['stock_quantity'] ?? 0,
                'image' => $var['image'] ?? '',
                'properties' => $var['properties'] ?? []
            ];
        }
        
        $product['variations'] = $variations;
        $product['updated_at'] = date('Y-m-d H:i:s');
        
        // Update product in Firebase
        $updateResult = firebaseRequest('/products/' . $productId, 'PUT', $product);
        
        if ($updateResult['code'] === 200) {
            echo json_encode([
                'message' => 'Product Variations Created',
                'data' => array_map(function($var, $index) {
                    return [
                        'id' => $index + 1000,
                        'sku' => $var['sku']
                    ];
                }, $variations, array_keys($variations))
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to add variations']);
        }
        exit();
    }
}

// ============================================
// 4. ORDERS ENDPOINTS
// ============================================
if ($requestPath === 'orders') {
    
    // GET /orders - List orders
    if ($method === 'GET') {
        $page = $_GET['page'] ?? 1;
        $per_page = $_GET['per_page'] ?? 10;
        $order_number = $_GET['order_number'] ?? null;
        
        $result = firebaseRequest('/orders');
        $orders = $result['data'] ? array_values($result['data']) : [];
        
        // Filter by order number if provided
        if ($order_number) {
            $orders = array_filter($orders, function($order) use ($order_number) {
                return ($order['order_number'] ?? '') === $order_number;
            });
        }
        
        // Format for MoveDrop
        $formatted = [];
        foreach ($orders as $id => $order) {
            $formatted[] = [
                'id' => $id,
                'order_number' => $order['order_number'] ?? 'ORD-' . $id,
                'status' => $order['status'] ?? 'pending',
                'currency' => $order['currency'] ?? 'BDT',
                'total' => $order['total'] ?? '0',
                'payment_method' => $order['payment_method'] ?? 'cod',
                'shipping_address' => [
                    'first_name' => $order['shipping_address']['first_name'] ?? ($order['customer']['name'] ?? ''),
                    'last_name' => $order['shipping_address']['last_name'] ?? '',
                    'email' => $order['shipping_address']['email'] ?? ($order['customer']['email'] ?? ''),
                    'phone' => $order['shipping_address']['phone'] ?? ($order['customer']['phone'] ?? ''),
                    'address_1' => $order['shipping_address']['address_1'] ?? ($order['customer']['address'] ?? ''),
                    'address_2' => $order['shipping_address']['address_2'] ?? '',
                    'city' => $order['shipping_address']['city'] ?? 'Dhaka',
                    'state' => $order['shipping_address']['state'] ?? 'Dhaka',
                    'postcode' => $order['shipping_address']['postcode'] ?? '1200',
                    'country' => $order['shipping_address']['country'] ?? 'Bangladesh'
                ]
            ];
        }
        
        // Pagination
        $offset = ($page - 1) * $per_page;
        $paginated = array_slice($formatted, $offset, $per_page);
        
        echo json_encode(['data' => $paginated]);
        exit();
    }
}

// ============================================
// 5. SINGLE ORDER ENDPOINTS
// ============================================
if (preg_match('/^orders\/([^\/]+)$/', $requestPath, $matches)) {
    $orderId = $matches[1];
    
    // PUT /orders/:id - Update order status
    if ($method === 'PUT') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['status'])) {
            http_response_code(422);
            echo json_encode(['message' => 'Status is required']);
            exit();
        }
        
        // Validate status
        $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($input['status'], $validStatuses)) {
            http_response_code(400);
            echo json_encode(['message' => 'Invalid status']);
            exit();
        }
        
        // Get existing order
        $result = firebaseRequest('/orders/' . $orderId);
        $order = $result['data'];
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['message' => 'Order not found']);
            exit();
        }
        
        // Update status
        $order['status'] = $input['status'];
        $order['updated_at'] = date('Y-m-d H:i:s');
        
        // Save to Firebase
        $updateResult = firebaseRequest('/orders/' . $orderId, 'PUT', $order);
        
        if ($updateResult['code'] === 200) {
            echo json_encode([
                'data' => [
                    'id' => $orderId,
                    'order_number' => $order['order_number'] ?? 'ORD-' . $orderId,
                    'status' => $input['status'],
                    'currency' => $order['currency'] ?? 'BDT',
                    'total' => $order['total'] ?? '0',
                    'payment_method' => $order['payment_method'] ?? 'cod',
                    'shipping_address' => $order['shipping_address'] ?? []
                ]
            ]);
            
            // Trigger webhook for order update
            triggerWebhook('order.updated', [
                'id' => $orderId,
                'order_number' => $order['order_number'] ?? 'ORD-' . $orderId,
                'status' => $input['status']
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to update order']);
        }
        exit();
    }
}

// ============================================
// 6. ORDER TIMELINE ENDPOINTS
// ============================================
if (preg_match('/^orders\/([^\/]+)\/timelines$/', $requestPath, $matches)) {
    $orderId = $matches[1];
    
    if ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['message'])) {
            http_response_code(422);
            echo json_encode(['message' => 'Message is required']);
            exit();
        }
        
        // Get existing order
        $result = firebaseRequest('/orders/' . $orderId);
        $order = $result['data'];
        
        if (!$order) {
            http_response_code(404);
            echo json_encode(['message' => 'Order not found']);
            exit();
        }
        
        // Add timeline
        if (!isset($order['timelines'])) {
            $order['timelines'] = [];
        }
        
        $order['timelines'][] = [
            'message' => $input['message'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Save to Firebase
        $updateResult = firebaseRequest('/orders/' . $orderId, 'PUT', $order);
        
        if ($updateResult['code'] === 200) {
            echo json_encode([
                'data' => [
                    'id' => $orderId,
                    'order_number' => $order['order_number'] ?? 'ORD-' . $orderId,
                    'status' => $order['status'] ?? 'pending',
                    'currency' => $order['currency'] ?? 'BDT',
                    'total' => $order['total'] ?? '0',
                    'payment_method' => $order['payment_method'] ?? 'cod',
                    'shipping_address' => $order['shipping_address'] ?? []
                ],
                'http_status_code' => 200
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['message' => 'Failed to add timeline']);
        }
        exit();
    }
}

// ============================================
// 7. WEBHOOKS ENDPOINT
// ============================================
if ($requestPath === 'webhooks' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['webhooks']) || !is_array($input['webhooks'])) {
        http_response_code(400);
        echo json_encode(['message' => 'Invalid webhook data']);
        exit();
    }
    
    // Save webhooks to Firebase
    $webhooks = [];
    foreach ($input['webhooks'] as $webhook) {
        $webhookData = [
            'name' => $webhook['name'] ?? '',
            'event' => $webhook['event'] ?? '',
            'delivery_url' => $webhook['delivery_url'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $result = firebaseRequest('/webhooks', 'POST', $webhookData);
        if ($result['code'] === 200) {
            $webhooks[] = $webhookData;
        }
    }
    
    http_response_code(201);
    echo json_encode(['message' => 'Webhooks Stored Successfully']);
    exit();
}

// ============================================
// 8. HELPER FUNCTION: Trigger Webhooks
// ============================================
function triggerWebhook($event, $data) {
    // Get all webhooks from Firebase
    $result = firebaseRequest('/webhooks');
    $webhooks = $result['data'] ?? [];
    
    foreach ($webhooks as $webhook) {
        if ($webhook['event'] === $event) {
            // Send webhook notification
            $ch = curl_init($webhook['delivery_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}

// ============================================
// 9. HEALTH CHECK ENDPOINT
// ============================================
if ($requestPath === 'health') {
    echo json_encode([
        'status' => 'online',
        'message' => 'MoveDrop API is running',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit();
}

// If no endpoint matched
http_response_code(404);
echo json_encode(['message' => 'Endpoint not found']);
?>