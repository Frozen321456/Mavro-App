<?php
/**
 * MAVRO ESSENCE - MOVE-DROP OFFICIAL API
 * সম্পূর্ণ MoveDrop স্ট্যান্ডার্ড অনুযায়ী তৈরি
 * Version: 2.0.0
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

// API Key verification
$headers = getallheaders();
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
$request_uri = $_SERVER['REQUEST_URI'];
$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================
// HEALTH CHECK (Required by MoveDrop)
// ============================================
if ($path === 'health' && $method === 'GET') {
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'service' => 'Mavro Essence API'
    ]);
    exit();
}

// ============================================
// GET ALL PRODUCTS (For Sync)
// ============================================
if ($path === 'products' && $method === 'GET') {
    $products = firebase_get('/products.json');
    
    if (!$products) {
        echo json_encode([]);
        exit();
    }
    
    $formatted_products = [];
    foreach ($products as $key => $product) {
        $formatted_products[] = format_product_for_movedrop($key, $product);
    }
    
    echo json_encode($formatted_products);
    exit();
}

// ============================================
// GET SINGLE PRODUCT
// ============================================
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'GET') {
    $product_id = $matches[1];
    $product = firebase_get("/products/{$product_id}.json");
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    echo json_encode(format_product_for_movedrop($product_id, $product));
    exit();
}

// ============================================
// CREATE PRODUCT
// ============================================
if ($path === 'products' && $method === 'POST') {
    $product_id = uniqid('prod_');
    $timestamp = date('c');
    
    // Prepare images
    $images = [];
    if (!empty($input['images'])) {
        foreach ($input['images'] as $img) {
            $images[] = [
                'src' => $img['src'] ?? $img,
                'position' => $img['position'] ?? 0
            ];
        }
    }
    
    // Prepare product data in MoveDrop format
    $product_data = [
        'id' => $product_id,
        'title' => $input['title'] ?? '',
        'description' => $input['description'] ?? '',
        'sku' => $input['sku'] ?? '',
        'regular_price' => (string)($input['regular_price'] ?? '0'),
        'sale_price' => (string)($input['sale_price'] ?? ''),
        'images' => $images,
        'tags' => $input['tags'] ?? [],
        'variants' => $input['variants'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
        // Extra fields for your admin panel
        'name' => $input['title'] ?? '',
        'price' => (string)($input['regular_price'] ?? '0'),
        'category' => $input['category'] ?? ''
    ];
    
    // Save to Firebase
    $result = firebase_put("/products/{$product_id}.json", $product_data);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Product created successfully',
            'data' => [
                'id' => $product_id,
                'title' => $product_data['title'],
                'sku' => $product_data['sku'],
                'regular_price' => $product_data['regular_price'],
                'sale_price' => $product_data['sale_price'],
                'created_at' => $timestamp,
                'updated_at' => $timestamp
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create product']);
    }
    exit();
}

// ============================================
// UPDATE PRODUCT
// ============================================
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'PUT') {
    $product_id = $matches[1];
    $existing = firebase_get("/products/{$product_id}.json");
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['error' => 'Product not found']);
        exit();
    }
    
    // Update fields
    $timestamp = date('c');
    $updated_data = array_merge($existing, $input);
    $updated_data['updated_at'] = $timestamp;
    
    // Handle images if provided
    if (!empty($input['images'])) {
        $images = [];
        foreach ($input['images'] as $img) {
            $images[] = [
                'src' => $img['src'] ?? $img,
                'position' => $img['position'] ?? 0
            ];
        }
        $updated_data['images'] = $images;
    }
    
    $result = firebase_put("/products/{$product_id}.json", $updated_data);
    
    if ($result) {
        echo json_encode([
            'message' => 'Product updated successfully',
            'data' => format_product_for_movedrop($product_id, $updated_data)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update product']);
    }
    exit();
}

// ============================================
// DELETE PRODUCT
// ============================================
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'DELETE') {
    $product_id = $matches[1];
    $result = firebase_delete("/products/{$product_id}.json");
    
    if ($result) {
        echo json_encode([
            'message' => 'Product deleted successfully',
            'id' => $product_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete product']);
    }
    exit();
}

// ============================================
// CREATE PRODUCT VARIATIONS
// ============================================
if (preg_match('/^products\/(.+)\/variations$/', $path, $matches) && $method === 'POST') {
    $product_id = $matches[1];
    $variations = $input['variations'] ?? [];
    
    if (empty($variations)) {
        http_response_code(400);
        echo json_encode(['error' => 'No variations provided']);
        exit();
    }
    
    $saved_variations = [];
    foreach ($variations as $index => $var) {
        $var_id = $product_id . '_var_' . $index;
        $variation_data = [
            'id' => $var_id,
            'sku' => $var['sku'] ?? '',
            'regular_price' => (string)($var['regular_price'] ?? '0'),
            'sale_price' => (string)($var['sale_price'] ?? ''),
            'image' => $var['image'] ?? '',
            'properties' => $var['properties'] ?? []
        ];
        
        firebase_put("/products/{$product_id}/variations/{$index}.json", $variation_data);
        $saved_variations[] = [
            'id' => $var_id,
            'sku' => $variation_data['sku']
        ];
    }
    
    // Update main product price based on first variation
    if (!empty($variations[0])) {
        firebase_patch("/products/{$product_id}.json", [
            'regular_price' => (string)($variations[0]['regular_price'] ?? '0'),
            'sale_price' => (string)($variations[0]['sale_price'] ?? ''),
            'price' => (string)($variations[0]['regular_price'] ?? '0'),
            'updated_at' => date('c')
        ]);
    }
    
    http_response_code(201);
    echo json_encode([
        'message' => 'Product variations created',
        'data' => $saved_variations
    ]);
    exit();
}

// ============================================
// GET ORDERS
// ============================================
if ($path === 'orders' && $method === 'GET') {
    $orders = firebase_get('/orders.json');
    
    if (!$orders) {
        echo json_encode([]);
        exit();
    }
    
    $formatted_orders = [];
    foreach ($orders as $key => $order) {
        $formatted_orders[] = [
            'id' => $key,
            'order_number' => $order['order_number'] ?? '#' . substr($key, -6),
            'customer' => $order['customer'] ?? ['name' => 'Guest'],
            'total' => $order['total'] ?? '0',
            'status' => $order['status'] ?? 'pending',
            'created_at' => $order['created_at'] ?? date('c'),
            'items' => $order['items'] ?? []
        ];
    }
    
    echo json_encode($formatted_orders);
    exit();
}

// ============================================
// UPDATE ORDER STATUS
// ============================================
if (preg_match('/^orders\/(.+)$/', $path, $matches) && $method === 'PUT') {
    $order_id = $matches[1];
    $status = $input['status'] ?? '';
    
    if (!in_array($status, ['pending', 'processing', 'completed', 'cancelled'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        exit();
    }
    
    $result = firebase_patch("/orders/{$order_id}.json", [
        'status' => $status,
        'updated_at' => date('c')
    ]);
    
    if ($result) {
        echo json_encode([
            'message' => 'Order status updated',
            'order_id' => $order_id,
            'status' => $status
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update order']);
    }
    exit();
}

// ============================================
// DEFAULT RESPONSE FOR UNKNOWN ROUTES
// ============================================
http_response_code(404);
echo json_encode([
    'status' => 'error',
    'message' => 'Endpoint not found',
    'path' => $path
]);

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Format product for MoveDrop API
 */
function format_product_for_movedrop($id, $product) {
    $images = [];
    if (!empty($product['images'])) {
        foreach ($product['images'] as $img) {
            if (is_string($img)) {
                $images[] = ['src' => $img, 'position' => 0];
            } else {
                $images[] = $img;
            }
        }
    } elseif (!empty($product['image'])) {
        $images[] = ['src' => $product['image'], 'position' => 0];
    }
    
    $variations = [];
    if (!empty($product['variations'])) {
        foreach ($product['variations'] as $key => $var) {
            $variations[] = [
                'id' => $id . '_var_' . $key,
                'sku' => $var['sku'] ?? '',
                'regular_price' => (string)($var['regular_price'] ?? '0'),
                'sale_price' => (string)($var['sale_price'] ?? ''),
                'image' => $var['image'] ?? '',
                'properties' => $var['properties'] ?? [
                    ['name' => 'Option', 'value' => $var['name'] ?? 'Default']
                ]
            ];
        }
    }
    
    return [
        'id' => $id,
        'title' => $product['title'] ?? $product['name'] ?? 'Untitled',
        'description' => $product['description'] ?? '',
        'sku' => $product['sku'] ?? '',
        'regular_price' => (string)($product['regular_price'] ?? $product['price'] ?? '0'),
        'sale_price' => (string)($product['sale_price'] ?? ''),
        'images' => $images,
        'tags' => $product['tags'] ?? [],
        'variations' => $variations,
        'created_at' => $product['created_at'] ?? date('c'),
        'updated_at' => $product['updated_at'] ?? date('c')
    ];
}

/**
 * Firebase GET request
 */
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

/**
 * Firebase PUT request
 */
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

/**
 * Firebase PATCH request
 */
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

/**
 * Firebase DELETE request
 */
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
