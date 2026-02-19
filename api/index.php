<?php
/**
 * MAVRO ESSENCE API - FIXED VERSION FOR VERCEL
 * Version: 4.0.0
 */

// ============================================
// ERROR HANDLING & CONFIGURATION
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../error.log');

// ============================================
// HEADERS - FIXED CORS
// ============================================
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Max-Age: 86400");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// CONFIGURATION
// ============================================
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app');
define('ITEMS_PER_PAGE', 20);

// ============================================
// API KEY VERIFICATION
// ============================================
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$apiKey = '';

// Check multiple sources for API key
if (isset($headers['X-API-KEY'])) {
    $apiKey = $headers['X-API-KEY'];
} elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

// Public endpoints that don't need API key
$public_endpoints = ['health', 'test'];

// Get the request path
$full_path = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($full_path, PHP_URL_PATH), '/');
$path = str_replace('api/', '', $path);
$path_parts = explode('/', $path);
$endpoint = $path_parts[0] ?: 'health';

// Parse query parameters
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ============================================
// TEST ENDPOINT - To verify API is working
// ============================================
if ($endpoint === 'test' || $endpoint === 'health') {
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'API is working!',
        'timestamp' => date('c'),
        'php_version' => PHP_VERSION,
        'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
        'method' => $method,
        'endpoint' => $endpoint,
        'full_path' => $full_path,
        'path' => $path,
        'headers' => $headers,
        'api_key_provided' => !empty($apiKey)
    ], JSON_PRETTY_PRINT);
    exit();
}

// ============================================
// API KEY CHECK FOR PROTECTED ENDPOINTS
// ============================================
if (!in_array($endpoint, $public_endpoints) && $apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing API key',
        'code' => 'UNAUTHORIZED',
        'endpoint' => $endpoint
    ], JSON_PRETTY_PRINT);
    exit();
}

// ============================================
// ROUTING
// ============================================
switch ($endpoint) {
    // ========== HEALTH CHECK ==========
    case 'health':
        http_response_code(200);
        echo json_encode([
            'status' => 'ok',
            'timestamp' => date('c'),
            'service' => 'Mavro Essence API',
            'version' => '4.0.0'
        ], JSON_PRETTY_PRINT);
        break;

    // ========== PRODUCTS ==========
    case 'products':
        handleProducts($method, $path_parts, $input, $page, $per_page, $offset);
        break;

    // ========== CATEGORIES ==========
    case 'categories':
        handleCategories($method, $input, $page, $per_page, $offset);
        break;

    // ========== ORDERS ==========
    case 'orders':
        handleOrders($method, $path_parts, $input, $page, $per_page, $offset);
        break;

    // ========== DEFAULT ==========
    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Endpoint not found',
            'available_endpoints' => [
                'GET /health',
                'GET /test',
                'GET /products',
                'POST /products',
                'GET /categories',
                'POST /categories',
                'GET /orders'
            ]
        ], JSON_PRETTY_PRINT);
        break;
}

// ============================================
// PRODUCT HANDLER
// ============================================
function handleProducts($method, $path_parts, $input, $page, $per_page, $offset) {
    $product_id = $path_parts[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($product_id) {
                getSingleProduct($product_id);
            } else {
                getAllProducts($page, $per_page, $offset);
            }
            break;
            
        case 'POST':
            createProduct($input);
            break;
            
        case 'PUT':
            if ($product_id) {
                updateProduct($product_id, $input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Product ID required']);
            }
            break;
            
        case 'DELETE':
            if ($product_id) {
                deleteProduct($product_id);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Product ID required']);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
    }
}

function getAllProducts($page, $per_page, $offset) {
    $products_data = firebase_get('/products.json');
    $formatted_products = [];
    
    if ($products_data && is_array($products_data)) {
        foreach ($products_data as $key => $product) {
            if (is_array($product)) {
                $formatted_products[] = format_product($key, $product);
            }
        }
    }
    
    // Sort by created_at (newest first)
    usort($formatted_products, function($a, $b) {
        return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
    });
    
    $total = count($formatted_products);
    $paginated = array_slice($formatted_products, $offset, $per_page);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $paginated,
        'meta' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'last_page' => ceil($total / $per_page)
        ]
    ], JSON_PRETTY_PRINT);
}

function getSingleProduct($id) {
    $product = firebase_get("/products/{$id}.json");
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        return;
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => format_product($id, $product)
    ], JSON_PRETTY_PRINT);
}

function createProduct($input) {
    $errors = [];
    
    if (empty($input['title'])) {
        $errors['title'] = ['The title field is required.'];
    }
    
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(['message' => 'Validation failed', 'errors' => $errors]);
        return;
    }
    
    $product_id = uniqid('prod_');
    $timestamp = date('c');
    
    $product_data = [
        'id' => $product_id,
        'title' => $input['title'],
        'name' => $input['title'],
        'sku' => $input['sku'] ?? '',
        'description' => $input['description'] ?? '',
        'price' => $input['price'] ?? 0,
        'images' => $input['images'] ?? [],
        'category_ids' => $input['category_ids'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp
    ];
    
    $result = firebase_put("/products/{$product_id}.json", $product_data);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Product created successfully',
            'data' => format_product($product_id, $product_data)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create product']);
    }
}

function updateProduct($id, $input) {
    $existing = firebase_get("/products/{$id}.json");
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        return;
    }
    
    $update_data = array_merge($existing, $input);
    $update_data['updated_at'] = date('c');
    
    $result = firebase_put("/products/{$id}.json", $update_data);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Product updated successfully',
            'data' => format_product($id, $update_data)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update product']);
    }
}

function deleteProduct($id) {
    $product = firebase_get("/products/{$id}.json");
    
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        return;
    }
    
    $result = firebase_delete("/products/{$id}.json");
    
    if ($result) {
        http_response_code(200);
        echo json_encode(['message' => 'Product deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to delete product']);
    }
}

// ============================================
// CATEGORIES HANDLER
// ============================================
function handleCategories($method, $input, $page, $per_page, $offset) {
    switch ($method) {
        case 'GET':
            getAllCategories($page, $per_page, $offset);
            break;
        case 'POST':
            createCategory($input);
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
    }
}

function getAllCategories($page, $per_page, $offset) {
    $categories_data = firebase_get('/categories.json');
    $formatted_categories = [];
    
    if ($categories_data && is_array($categories_data)) {
        foreach ($categories_data as $key => $category) {
            if (is_array($category)) {
                $formatted_categories[] = [
                    'id' => $key,
                    'name' => $category['name'] ?? 'Unnamed',
                    'slug' => $category['slug'] ?? generate_slug($category['name'] ?? ''),
                    'created_at' => $category['created_at'] ?? date('c')
                ];
            }
        }
    }
    
    $total = count($formatted_categories);
    $paginated = array_slice($formatted_categories, $offset, $per_page);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $paginated,
        'meta' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'last_page' => ceil($total / $per_page)
        ]
    ], JSON_PRETTY_PRINT);
}

function createCategory($input) {
    $name = $input['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The name field is required.',
            'errors' => ['name' => ['The name field is required.']]
        ]);
        return;
    }
    
    $category_id = uniqid('cat_');
    $slug = generate_slug($name);
    
    $category_data = [
        'name' => $name,
        'slug' => $slug,
        'created_at' => date('c')
    ];
    
    $result = firebase_put("/categories/{$category_id}.json", $category_data);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Category created successfully',
            'data' => [
                'id' => $category_id,
                'name' => $name,
                'slug' => $slug,
                'created_at' => date('c')
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to create category']);
    }
}

// ============================================
// ORDERS HANDLER
// ============================================
function handleOrders($method, $path_parts, $input, $page, $per_page, $offset) {
    $order_id = $path_parts[1] ?? null;
    
    switch ($method) {
        case 'GET':
            if ($order_id) {
                getSingleOrder($order_id);
            } else {
                getAllOrders($page, $per_page, $offset);
            }
            break;
        case 'PUT':
            if ($order_id) {
                updateOrder($order_id, $input);
            } else {
                http_response_code(400);
                echo json_encode(['message' => 'Order ID required']);
            }
            break;
        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
    }
}

function getAllOrders($page, $per_page, $offset) {
    $orders_data = firebase_get('/orders.json');
    $formatted_orders = [];
    
    if ($orders_data && is_array($orders_data)) {
        foreach ($orders_data as $key => $order) {
            if (is_array($order)) {
                $formatted_orders[] = format_order($key, $order);
            }
        }
    }
    
    // Sort by created_at (newest first)
    usort($formatted_orders, function($a, $b) {
        return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
    });
    
    $total = count($formatted_orders);
    $paginated = array_slice($formatted_orders, $offset, $per_page);
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => $paginated,
        'meta' => [
            'current_page' => $page,
            'per_page' => $per_page,
            'total' => $total,
            'last_page' => ceil($total / $per_page)
        ]
    ], JSON_PRETTY_PRINT);
}

function getSingleOrder($id) {
    $order = firebase_get("/orders/{$id}.json");
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
        return;
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'data' => format_order($id, $order)
    ], JSON_PRETTY_PRINT);
}

function updateOrder($id, $input) {
    $existing = firebase_get("/orders/{$id}.json");
    
    if (!$existing) {
        http_response_code(404);
        echo json_encode(['message' => 'Order not found']);
        return;
    }
    
    $update_data = array_merge($existing, $input);
    $update_data['updated_at'] = date('c');
    
    $result = firebase_put("/orders/{$id}.json", $update_data);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Order updated successfully',
            'data' => format_order($id, $update_data)
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['message' => 'Failed to update order']);
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function generate_slug($string) {
    $slug = strtolower($string);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug ?: 'category';
}

function format_product($id, $product) {
    return [
        'id' => $id,
        'title' => $product['title'] ?? $product['name'] ?? 'Untitled',
        'sku' => $product['sku'] ?? '',
        'description' => $product['description'] ?? '',
        'price' => (float)($product['price'] ?? 0),
        'images' => $product['images'] ?? [],
        'category_ids' => $product['category_ids'] ?? [],
        'created_at' => $product['created_at'] ?? date('c'),
        'updated_at' => $product['updated_at'] ?? date('c')
    ];
}

function format_order($id, $order) {
    return [
        'id' => $id,
        'order_number' => $order['order_number'] ?? '#' . substr($id, -6),
        'status' => $order['status'] ?? 'pending',
        'total' => (float)($order['total'] ?? 0),
        'customer' => $order['customer'] ?? ['name' => ''],
        'payment_method' => $order['payment']['method'] ?? 'cod',
        'created_at' => $order['created_at'] ?? date('c')
    ];
}

// ============================================
// FIREBASE FUNCTIONS
// ============================================

function firebase_get($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Firebase GET Error: " . $curl_error);
        return null;
    }
    
    if ($http_code === 200) {
        return json_decode($response, true);
    }
    
    return null;
}

function firebase_put($endpoint, $data) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}

function firebase_delete($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $http_code === 200;
}
