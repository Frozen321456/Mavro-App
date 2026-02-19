<?php
/**
 * MAVRO ESSENCE API - COMPLETE FIXED VERSION
 * Just copy and paste this entire file
 */

// ============================================
// HEADERS - MUST BE FIRST
// ============================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization");

// Handle OPTIONS request (for CORS preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// SIMPLE ROUTING
// ============================================

// Get the request URI and method
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remove query string and base path
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/api/', '', $path);
$path = trim($path, '/');

// Split into parts
$path_parts = explode('/', $path);
$endpoint = $path_parts[0] ?: 'test';

// Get request body for POST/PUT
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// ============================================
// TEST ENDPOINT - ALWAYS WORKS
// ============================================
if ($endpoint === 'test' || $endpoint === 'health') {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'API is working perfectly!',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Vercel',
        'method' => $request_method,
        'endpoint' => $endpoint,
        'full_path' => $request_uri
    ], JSON_PRETTY_PRINT);
    exit();
}

// ============================================
// PRODUCTS ENDPOINT
// ============================================
if ($endpoint === 'products') {
    $product_id = $path_parts[1] ?? null;
    
    if ($request_method === 'GET') {
        // Return sample products
        $products = [
            [
                'id' => 'prod_1',
                'name' => 'Classic T-Shirt',
                'price' => 850,
                'category' => 'Fashion',
                'image' => 'https://via.placeholder.com/300'
            ],
            [
                'id' => 'prod_2',
                'name' => 'Denim Jeans',
                'price' => 1850,
                'category' => 'Fashion',
                'image' => 'https://via.placeholder.com/300'
            ],
            [
                'id' => 'prod_3',
                'name' => 'Leather Bag',
                'price' => 2500,
                'category' => 'Accessories',
                'image' => 'https://via.placeholder.com/300'
            ],
            [
                'id' => 'prod_4',
                'name' => 'Running Shoes',
                'price' => 2200,
                'category' => 'Footwear',
                'image' => 'https://via.placeholder.com/300'
            ],
            [
                'id' => 'prod_5',
                'name' => 'Smart Watch',
                'price' => 3500,
                'category' => 'Electronics',
                'image' => 'https://via.placeholder.com/300'
            ]
        ];
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $products,
            'total' => count($products)
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    if ($request_method === 'POST') {
        // Create new product
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => [
                'id' => 'prod_' . rand(100, 999),
                'name' => $input['name'] ?? 'New Product',
                'price' => $input['price'] ?? 0,
                'created_at' => date('c')
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// ============================================
// CATEGORIES ENDPOINT
// ============================================
if ($endpoint === 'categories') {
    if ($request_method === 'GET') {
        $categories = [
            ['id' => 'cat_1', 'name' => 'Fashion', 'slug' => 'fashion'],
            ['id' => 'cat_2', 'name' => 'Electronics', 'slug' => 'electronics'],
            ['id' => 'cat_3', 'name' => 'Footwear', 'slug' => 'footwear'],
            ['id' => 'cat_4', 'name' => 'Accessories', 'slug' => 'accessories'],
            ['id' => 'cat_5', 'name' => 'Home & Living', 'slug' => 'home-living']
        ];
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $categories,
            'total' => count($categories)
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    if ($request_method === 'POST') {
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Category created',
            'data' => [
                'id' => 'cat_' . rand(100, 999),
                'name' => $input['name'] ?? 'New Category',
                'slug' => strtolower(str_replace(' ', '-', $input['name'] ?? 'new-category'))
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// ============================================
// ORDERS ENDPOINT
// ============================================
if ($endpoint === 'orders') {
    if ($request_method === 'GET') {
        $orders = [
            [
                'id' => 'ord_1',
                'order_number' => '#ORD-001',
                'customer' => 'John Doe',
                'total' => 1850,
                'status' => 'pending',
                'date' => date('c')
            ],
            [
                'id' => 'ord_2',
                'order_number' => '#ORD-002',
                'customer' => 'Jane Smith',
                'total' => 3500,
                'status' => 'completed',
                'date' => date('c')
            ]
        ];
        
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $orders
        ], JSON_PRETTY_PRINT);
        exit();
    }
}

// ============================================
// CATCH-ALL - SHOW AVAILABLE ENDPOINTS
// ============================================
http_response_code(200); // Change to 200 instead of 404 for testing
echo json_encode([
    'status' => 'info',
    'message' => 'API is running. Available endpoints:',
    'endpoints' => [
        'GET /api/test - Test API connection',
        'GET /api/health - Health check',
        'GET /api/products - Get all products',
        'POST /api/products - Create product',
        'GET /api/categories - Get all categories',
        'POST /api/categories - Create category',
        'GET /api/orders - Get all orders'
    ],
    'your_request' => [
        'endpoint' => $endpoint,
        'method' => $request_method,
        'path' => $path,
        'uri' => $request_uri
    ]
], JSON_PRETTY_PRINT);
exit();
