<?php
// ============================================
// SINGLE FILE API - COMPLETE WORKING VERSION
// ============================================

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the requested path
$request_uri = $_SERVER['REQUEST_URI'];
$path = str_replace('/api/', '', parse_url($request_uri, PHP_URL_PATH));
$path = trim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Get POST data if any
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================
// ROUTING - All endpoints in one place
// ============================================

// Test endpoint (default)
if ($path === '' || $path === 'test' || $path === 'health') {
    echo json_encode([
        'status' => 'success',
        'message' => '✅ API is working perfectly!',
        'timestamp' => date('Y-m-d H:i:s'),
        'php_version' => phpversion(),
        'endpoints' => [
            'GET /api/test' => 'Test API connection',
            'GET /api/products' => 'Get all products',
            'GET /api/categories' => 'Get all categories',
            'GET /api/orders' => 'Get all orders',
            'GET /api/products/{id}' => 'Get single product'
        ]
    ], JSON_PRETTY_PRINT);
}

// Products endpoint
elseif ($path === 'products') {
    $products = [
        [
            'id' => 'prod_1',
            'name' => 'Black Premium T-Shirt',
            'price' => 850,
            'category' => 'Fashion',
            'in_stock' => true,
            'image' => 'https://via.placeholder.com/300/000000/ffffff?text=T-Shirt'
        ],
        [
            'id' => 'prod_2',
            'name' => 'Slim Fit Denim Jeans',
            'price' => 1850,
            'category' => 'Fashion',
            'in_stock' => true,
            'image' => 'https://via.placeholder.com/300/1a237e/ffffff?text=Jeans'
        ],
        [
            'id' => 'prod_3',
            'name' => 'Genuine Leather Bag',
            'price' => 2500,
            'category' => 'Accessories',
            'in_stock' => false,
            'image' => 'https://via.placeholder.com/300/8b4513/ffffff?text=Bag'
        ],
        [
            'id' => 'prod_4',
            'name' => 'Running Sports Shoes',
            'price' => 2200,
            'category' => 'Footwear',
            'in_stock' => true,
            'image' => 'https://via.placeholder.com/300/ff6d00/ffffff?text=Shoes'
        ],
        [
            'id' => 'prod_5',
            'name' => 'Smart Watch Fitness Tracker',
            'price' => 3500,
            'category' => 'Electronics',
            'in_stock' => true,
            'image' => 'https://via.placeholder.com/300/2962ff/ffffff?text=Watch'
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'count' => count($products),
        'data' => $products
    ], JSON_PRETTY_PRINT);
}

// Single product endpoint (e.g., /api/products/prod_1)
elseif (strpos($path, 'products/') === 0) {
    $product_id = str_replace('products/', '', $path);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'id' => $product_id,
            'name' => 'Product ' . $product_id,
            'price' => 1500,
            'description' => 'This is a detailed product description with all specifications.',
            'images' => [
                'https://via.placeholder.com/600/ff385c/ffffff?text=Image+1',
                'https://via.placeholder.com/600/000000/ffffff?text=Image+2'
            ],
            'category' => 'General',
            'in_stock' => true,
            'reviews' => 12,
            'rating' => 4.5
        ]
    ], JSON_PRETTY_PRINT);
}

// Categories endpoint
elseif ($path === 'categories') {
    $categories = [
        ['id' => 'cat_1', 'name' => 'Fashion', 'slug' => 'fashion', 'product_count' => 25],
        ['id' => 'cat_2', 'name' => 'Electronics', 'slug' => 'electronics', 'product_count' => 18],
        ['id' => 'cat_3', 'name' => 'Footwear', 'slug' => 'footwear', 'product_count' => 15],
        ['id' => 'cat_4', 'name' => 'Accessories', 'slug' => 'accessories', 'product_count' => 22],
        ['id' => 'cat_5', 'name' => 'Home & Living', 'slug' => 'home-living', 'product_count' => 12]
    ];
    
    echo json_encode([
        'status' => 'success',
        'count' => count($categories),
        'data' => $categories
    ], JSON_PRETTY_PRINT);
}

// Orders endpoint
elseif ($path === 'orders') {
    $orders = [
        [
            'id' => 'ord_1',
            'order_number' => 'ORD-2024-001',
            'customer_name' => 'Rahim Khan',
            'customer_phone' => '01712345678',
            'total' => 1850,
            'status' => 'pending',
            'payment_method' => 'Cash on Delivery',
            'created_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ],
        [
            'id' => 'ord_2',
            'order_number' => 'ORD-2024-002',
            'customer_name' => 'Karima Begum',
            'customer_phone' => '01812345678',
            'total' => 3500,
            'status' => 'completed',
            'payment_method' => 'bKash',
            'created_at' => date('Y-m-d H:i:s', strtotime('-1 day'))
        ],
        [
            'id' => 'ord_3',
            'order_number' => 'ORD-2024-003',
            'customer_name' => 'Shakil Ahmed',
            'customer_phone' => '01912345678',
            'total' => 2200,
            'status' => 'processing',
            'payment_method' => 'Nagad',
            'created_at' => date('Y-m-d H:i:s')
        ]
    ];
    
    echo json_encode([
        'status' => 'success',
        'count' => count($orders),
        'data' => $orders
    ], JSON_PRETTY_PRINT);
}

// 404 - Not found
else {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Endpoint not found',
        'requested_path' => $path,
        'available_endpoints' => [
            '/api/test',
            '/api/products',
            '/api/products/{id}',
            '/api/categories',
            '/api/orders'
        ]
    ], JSON_PRETTY_PRINT);
}
?>
