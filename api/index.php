<?php
// ============================================
// SINGLE FILE API - EVERYTHING IN ONE PLACE
// ============================================

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the path
$request_uri = $_SERVER['REQUEST_URI'];
$path = str_replace('/api/', '', parse_url($request_uri, PHP_URL_PATH));
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data if any
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================
// ROUTING - Everything in one place
// ============================================

if ($path === 'test' || $path === 'health' || $path === '') {
    // Test endpoint
    echo json_encode([
        'status' => 'success',
        'message' => 'API is working!',
        'time' => date('c'),
        'url' => $request_uri,
        'endpoints' => [
            '/api/test',
            '/api/health',
            '/api/products',
            '/api/categories',
            '/api/orders'
        ]
    ], JSON_PRETTY_PRINT);
}
elseif ($path === 'products') {
    // Products endpoint
    $products = [
        [
            'id' => 'p1',
            'name' => 'Black T-Shirt',
            'price' => 850,
            'category' => 'Fashion',
            'image' => 'https://via.placeholder.com/300'
        ],
        [
            'id' => 'p2',
            'name' => 'Blue Jeans',
            'price' => 1850,
            'category' => 'Fashion',
            'image' => 'https://via.placeholder.com/300'
        ],
        [
            'id' => 'p3',
            'name' => 'Leather Bag',
            'price' => 2500,
            'category' => 'Accessories',
            'image' => 'https://via.placeholder.com/300'
        ],
        [
            'id' => 'p4',
            'name' => 'Sports Shoes',
            'price' => 2200,
            'category' => 'Footwear',
            'image' => 'https://via.placeholder.com/300'
        ],
        [
            'id' => 'p5',
            'name' => 'Smart Watch',
            'price' => 3500,
            'category' => 'Electronics',
            'image' => 'https://via.placeholder.com/300'
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'data' => $products,
        'count' => count($products)
    ], JSON_PRETTY_PRINT);
}
elseif ($path === 'categories') {
    // Categories endpoint
    $categories = [
        ['id' => 'c1', 'name' => 'Fashion', 'count' => 15],
        ['id' => 'c2', 'name' => 'Electronics', 'count' => 8],
        ['id' => 'c3', 'name' => 'Footwear', 'count' => 12],
        ['id' => 'c4', 'name' => 'Accessories', 'count' => 10],
        ['id' => 'c5', 'name' => 'Home & Living', 'count' => 7]
    ];
    
    echo json_encode([
        'status' => 'success',
        'data' => $categories,
        'count' => count($categories)
    ], JSON_PRETTY_PRINT);
}
elseif ($path === 'orders') {
    // Orders endpoint
    $orders = [
        [
            'id' => 'o1',
            'number' => '#ORD-001',
            'customer' => 'Rahim Khan',
            'total' => 1850,
            'status' => 'pending',
            'date' => date('c')
        ],
        [
            'id' => 'o2',
            'number' => '#ORD-002',
            'customer' => 'Karima Begum',
            'total' => 3500,
            'status' => 'completed',
            'date' => date('c')
        ],
        [
            'id' => 'o3',
            'number' => '#ORD-003',
            'customer' => 'Shakil Ahmed',
            'total' => 2200,
            'status' => 'processing',
            'date' => date('c')
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'data' => $orders,
        'count' => count($orders)
    ], JSON_PRETTY_PRINT);
}
elseif (strpos($path, 'products/') === 0) {
    // Single product endpoint (e.g., /api/products/p1)
    $id = str_replace('products/', '', $path);
    
    // Return a single product
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $id,
            'name' => 'Product ' . $id,
            'price' => 1500,
            'description' => 'This is a detailed product description',
            'images' => ['https://via.placeholder.com/600'],
            'category' => 'General'
        ]
    ], JSON_PRETTY_PRINT);
}
else {
    // 404 - Not found
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Endpoint not found',
        'path' => $path,
        'available' => ['test', 'health', 'products', 'categories', 'orders', 'products/{id}']
    ], JSON_PRETTY_PRINT);
}
