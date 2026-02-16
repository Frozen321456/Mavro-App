<?php
/**
 * MAVRO ESSENCE - MOVE-DROP OFFICIAL API
 * Version: 3.1.0
 * Fully fixed for MoveDrop integration
 */

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");
header("Access-Control-Max-Age: 3600");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app');
define('ITEMS_PER_PAGE', 20);

// API Key verification
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$apiKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing API key'
    ]);
    exit();
}

// Parse request
$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ============================================
// HEALTH CHECK
// ============================================
if ($path === 'health' && $method === 'GET') {
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'service' => 'Mavro Essence API'
    ]);
    exit();
}

// ============================================
// WEBHOOKS REGISTRATION
// ============================================
if ($path === 'webhooks' && $method === 'POST') {
    $webhooks = $input['webhooks'] ?? [];
    
    if (empty($webhooks)) {
        http_response_code(400);
        echo json_encode(['message' => 'No webhooks provided']);
        exit();
    }
    
    $saved = [];
    foreach ($webhooks as $webhook) {
        $webhook_data = [
            'name' => $webhook['name'],
            'event' => $webhook['event'],
            'delivery_url' => $webhook['delivery_url'],
            'created_at' => date('c')
        ];
        
        firebase_put("/webhooks/{$webhook['event']}.json", $webhook_data);
        $saved[] = $webhook_data;
    }
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Webhooks registered successfully',
        'data' => $saved
    ]);
    exit();
}

// ============================================
// CATEGORIES ENDPOINTS
// ============================================

// GET /categories - List categories with pagination
if ($path === 'categories' && $method === 'GET') {
    $categories_data = firebase_get('/categories.json');
    
    $formatted_categories = [];
    
    if ($categories_data && is_array($categories_data)) {
        foreach ($categories_data as $key => $category) {
            if (is_array($category)) {
                $name = $category['name'] ?? 'Unnamed Category';
                $slug = $category['slug'] ?? generate_slug($name);
                
                $formatted_categories[] = [
                    'id' => (int)($category['id'] ?? (is_numeric($key) ? $key : crc32($key))),
                    'name' => $name,
                    'slug' => $slug,
                    'created_at' => $category['created_at'] ?? date('c')
                ];
            }
        }
    }
    
    usort($formatted_categories, function($a, $b) {
        return $a['id'] - $b['id'];
    });
    
    $total = count($formatted_categories);
    $paginated = array_slice($formatted_categories, $offset, $per_page);
    $last_page = ceil($total / $per_page);
    $from = $offset + 1;
    $to = min($offset + $per_page, $total);
    
    http_response_code(200);
    echo json_encode([
        'data' => $paginated,
        'meta' => [
            'current_page' => $page,
            'from' => $from,
            'last_page' => $last_page,
            'per_page' => $per_page,
            'to' => $to,
            'total' => $total
        ]
    ]);
    exit();
}

// POST /categories - Create new category
if ($path === 'categories' && $method === 'POST') {
    $name = $input['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The name field is required.',
            'errors' => ['name' => ['The name field is required.']]
        ]);
        exit();
    }
    
    $existing_categories = firebase_get('/categories.json') ?? [];
    
    foreach ($existing_categories as $cat) {
        if (is_array($cat) && isset($cat['name']) && strtolower($cat['name']) === strtolower($name)) {
            http_response_code(400);
            echo json_encode(['message' => 'Category with this name already exists']);
            exit();
        }
    }
    
    $max_id = 0;
    foreach ($existing_categories as $cat) {
        if (is_array($cat) && isset($cat['id']) && $cat['id'] > $max_id) {
            $max_id = $cat['id'];
        }
    }
    $new_id = $max_id + 1;
    
    $slug = generate_slug($name);
    $timestamp = date('c');
    
    $category_data = [
        'id' => $new_id,
        'name' => $name,
        'slug' => $slug,
        'created_at' => $timestamp
    ];
    
    $save_result = firebase_put("/categories/{$new_id}.json", $category_data);
    
    if ($save_result) {
        http_response_code(201);
        echo json_encode(['data' => $category_data]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create category']);
    }
    exit();
}

// ============================================
// PRODUCTS ENDPOINTS
// ============================================

// GET /products - List all products (for MoveDrop sync)
if ($path === 'products' && $method === 'GET') {
    $products = firebase_get('/products.json') ?? [];
    
    $formatted_products = [];
    foreach ($products as $key => $product) {
        // Process images
        $images = [];
        if (!empty($product['images'])) {
            foreach ($product['images'] as $img) {
                if (is_string($img)) {
                    $images[] = [
                        'is_default' => count($images) === 0,
                        'src' => $img
                    ];
                } else {
                    $images[] = $img;
                }
            }
        }
        
        $formatted_products[] = [
            'id' => (int)($product['id'] ?? (is_numeric($key) ? $key : crc32($key))),
            'title' => $product['title'] ?? $product['name'] ?? 'Untitled',
            'sku' => $product['sku'] ?? '',
            'description' => $product['description'] ?? '',
            'images' => $images,
            'category_ids' => $product['category_ids'] ?? [],
            'tags' => $product['tags'] ?? [],
            'properties' => $product['properties'] ?? [],
            'created_at' => $product['created_at'] ?? date('c'),
            'updated_at' => $product['updated_at'] ?? date('c')
        ];
    }
    
    http_response_code(200);
    echo json_encode($formatted_products);
    exit();
}

// POST /products - Create new product
if ($path === 'products' && $method === 'POST') {
    $errors = [];
    
    if (empty($input['title'])) {
        $errors['title'] = ['The title field is required.'];
    }
    
    if (empty($input['sku'])) {
        $errors['sku'] = ['The sku field is required.'];
    }
    
    if (empty($input['images']) || !is_array($input['images'])) {
        $errors['images'] = ['At least one image is required.'];
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
    $existing = firebase_get('/products.json');
    foreach ($existing ?? [] as $key => $prod) {
        if (isset($prod['sku']) && $prod['sku'] === $input['sku']) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Product with given SKU already exists',
                'data' => [
                    'error' => [
                        'code' => 'product_duplicate_sku',
                        'message' => 'SKU already exists.',
                        'data' => [
                            'product_id' => (int)$key,
                            'sku' => $input['sku']
                        ]
                    ]
                ]
            ]);
            exit();
        }
    }
    
    $product_id = time();
    $timestamp = date('c');
    
    $product_data = [
        'id' => $product_id,
        'title' => $input['title'],
        'name' => $input['title'],
        'sku' => $input['sku'],
        'description' => $input['description'] ?? '',
        'images' => $input['images'],
        'category_ids' => $input['category_ids'] ?? [],
        'tags' => $input['tags'] ?? [],
        'properties' => $input['properties'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp
    ];
    
    $result = firebase_put("/products/{$product_id}.json", $product_data);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Product Created',
            'data' => [
                'id' => $product_id,
                'title' => $product_data['title'],
                'sku' => $product_data['sku'],
                'tags' => $product_data['tags'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create product']);
    }
    exit();
}

// POST /products/:id/variations - Create product variations
if (preg_match('/^products\/(.+)\/variations$/', $path, $matches) && $method === 'POST') {
    $product_id = $matches[1];
    $variations = $input['variations'] ?? [];
    
    if (empty($variations)) {
        http_response_code(400);
        echo json_encode(['message' => 'No variations provided']);
        exit();
    }
    
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        exit();
    }
    
    $saved_variations = [];
    $existing_skus = [];
    
    foreach ($product['variations'] ?? [] as $var) {
        if (!empty($var['sku'])) {
            $existing_skus[$var['sku']] = true;
        }
    }
    
    foreach ($variations as $index => $var) {
        $variation_id = (int)($product_id . sprintf("%02d", $index));
        
        if (isset($existing_skus[$var['sku']])) {
            $saved_variations[] = [
                'error' => [
                    'code' => 'variation_duplicate_sku',
                    'message' => 'SKU already exists.',
                    'data' => [
                        'variation_id' => $variation_id,
                        'sku' => $var['sku']
                    ]
                ]
            ];
            continue;
        }
        
        $variation_data = [
            'id' => $variation_id,
            'sku' => $var['sku'],
            'regular_price' => (string)($var['regular_price'] ?? '0'),
            'sale_price' => (string)($var['sale_price'] ?? ''),
            'date_on_sale_from' => $var['date_on_sale_from'] ?? null,
            'date_on_sale_to' => $var['date_on_sale_to'] ?? null,
            'stock_quantity' => (int)($var['stock_quantity'] ?? 0),
            'image' => $var['image'] ?? '',
            'properties' => $var['properties'] ?? []
        ];
        
        firebase_put("/products/{$product_id}/variations/{$index}.json", $variation_data);
        
        $saved_variations[] = [
            'id' => $variation_id,
            'sku' => $var['sku']
        ];
        
        $existing_skus[$var['sku']] = true;
    }
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Product Variations Created',
        'data' => $saved_variations
    ]);
    exit();
}

// DELETE /products/:id - Delete product
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'DELETE') {
    $product_id = $matches[1];
    
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        exit();
    }
    
    $result = firebase_delete("/products/{$product_id}.json");
    
    if ($result) {
        http_response_code(200);
        echo json_encode(['message' => 'Product Deleted Successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to delete product']);
    }
    exit();
}

// ============================================
// ORDERS ENDPOINTS
// ============================================

// GET /orders - List orders with filters
if ($path === 'orders' && $method === 'GET') {
    $orders = firebase_get('/orders.json') ?? [];
    
    $formatted = [];
    foreach ($orders as $key => $order) {
        if (isset($_GET['order_number']) && ($order['order_number'] ?? '') !== $_GET['order_number']) {
            continue;
        }
        
        $formatted[] = format_order_for_movedrop($key, $order);
    }
    
    // Sort by created_at desc
    usort($formatted, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    $total = count($formatted);
    $paginated = array_slice($formatted, $offset, $per_page);
    
    http_response_code(200);
    echo json_encode([
        'data' => $paginated,
        'meta' => [
            'current_page' => $page,
            'from' => $offset + 1,
            'last_page' => ceil($total / $per_page),
            'per_page' => $per_page,
            'to' => min($offset + $per_page, $total),
            'total' => $total
        ]
    ]);
    exit();
}

// POST /orders - Create new order (MoveDrop webhook)
if ($path === 'orders' && $method === 'POST') {
    $order_data = $input;
    
    // Validate required fields
    if (empty($order_data['order_number']) || empty($order_data['line_items'])) {
        http_response_code(422);
        echo json_encode(['message' => 'Invalid order data']);
        exit();
    }
    
    $order_id = 'ORD-' . time() . '-' . rand(100, 999);
    $timestamp = date('c');
    
    // Store in Firebase
    $firebase_order = [
        'order_id' => $order_id,
        'order_number' => $order_data['order_number'],
        'status' => $order_data['status'] ?? 'pending',
        'currency' => $order_data['currency'] ?? 'BDT',
        'total' => $order_data['total'] ?? '0',
        'payment_method' => $order_data['payment_method'] ?? 'cod',
        'shipping_address' => $order_data['shipping_address'] ?? [],
        'customer_notes' => $order_data['customer_notes'] ?? '',
        'line_items' => $order_data['line_items'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
        'source' => 'movedrop'
    ];
    
    $result = firebase_put("/orders/{$order_id}.json", $firebase_order);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Order created successfully',
            'data' => $firebase_order
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create order']);
    }
    exit();
}

// PUT /orders/:id - Update order status
if (preg_match('/^orders\/(.+)$/', $path, $matches) && $method === 'PUT') {
    $order_id = $matches[1];
    $status = $input['status'] ?? '';
    
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    
    if (!in_array($status, $valid_statuses)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'Invalid status',
            'errors' => ['status' => ['Status must be one of: ' . implode(', ', $valid_statuses)]]
        ]);
        exit();
    }
    
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
        exit();
    }
    
    $update_data = [
        'status' => $status,
        'updated_at' => date('c')
    ];
    
    $result = firebase_patch("/orders/{$order_id}.json", $update_data);
    
    if ($result) {
        $updated_order = firebase_get("/orders/{$order_id}.json");
        http_response_code(200);
        echo json_encode([
            'data' => format_order_for_movedrop($order_id, $updated_order)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update order']);
    }
    exit();
}

// POST /orders/:id/timelines - Add order timeline
if (preg_match('/^orders\/(.+)\/timelines$/', $path, $matches) && $method === 'POST') {
    $order_id = $matches[1];
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        http_response_code(422);
        echo json_encode(['message' => 'Message is required']);
        exit();
    }
    
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
        exit();
    }
    
    $timeline_id = time();
    $timeline_data = [
        'id' => $timeline_id,
        'message' => $message,
        'created_at' => date('c')
    ];
    
    $result = firebase_put("/orders/{$order_id}/timelines/{$timeline_id}.json", $timeline_data);
    
    if ($result) {
        $updated_order = firebase_get("/orders/{$order_id}.json");
        http_response_code(200);
        echo json_encode([
            'data' => format_order_for_movedrop($order_id, $updated_order)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to add timeline']);
    }
    exit();
}

// ============================================
// DEFAULT RESPONSE
// ============================================
http_response_code(404);
echo json_encode([
    'message' => 'Endpoint not found',
    'path' => $path
]);

// ============================================
// HELPER FUNCTIONS
// ============================================

function generate_slug($string) {
    $slug = strtolower($string);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

function format_order_for_movedrop($id, $order) {
    return [
        'id' => (int)(is_numeric($id) ? $id : crc32($id)),
        'order_number' => $order['order_number'] ?? '#' . substr($id, -6),
        'status' => $order['status'] ?? 'pending',
        'currency' => $order['currency'] ?? 'BDT',
        'total' => (string)($order['total'] ?? '0'),
        'payment_method' => $order['payment_method'] ?? 'cod',
        'shipping_address' => $order['shipping_address'] ?? [
            'first_name' => 'Customer',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'address_1' => '',
            'address_2' => '',
            'city' => '',
            'state' => '',
            'postcode' => '',
            'country' => 'Bangladesh'
        ],
        'customer_notes' => $order['customer_notes'] ?? '',
        'line_items' => array_map(function($item) {
            return [
                'id' => $item['id'] ?? time(),
                'product_id' => (int)($item['product_id'] ?? 0),
                'name' => $item['name'] ?? '',
                'quantity' => (int)($item['quantity'] ?? 1),
                'total' => (string)($item['total'] ?? '0'),
                'variations' => array_map(function($var) {
                    return [
                        'id' => $var['id'] ?? time(),
                        'variation_id' => (int)($var['variation_id'] ?? 0),
                        'sku' => $var['sku'] ?? '',
                        'quantity' => (int)($var['quantity'] ?? 1),
                        'price' => (string)($var['price'] ?? '0'),
                        'created_at' => $var['created_at'] ?? date('c')
                    ];
                }, $item['variations'] ?? []),
                'created_at' => $item['created_at'] ?? date('c')
            ];
        }, $order['line_items'] ?? []),
        'created_at' => $order['created_at'] ?? date('c')
    ];
}

function firebase_get($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

function firebase_put($endpoint, $data) {
    $url = FIREBASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

function firebase_patch($endpoint, $data) {
    $url = FIREBASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

function firebase_delete($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}
?>
