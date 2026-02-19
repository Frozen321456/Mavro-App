<?php
/**
 * MAVRO ESSENCE - MOVE-DROP OFFICIAL API
 * Version: 3.2.0
 * FIXED: Products listing and API connection issues
 */

// Headers - FIXED: Added all necessary headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept, Origin");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests - FIXED: Better OPTIONS handling
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept, Origin");
    header("Access-Control-Max-Age: 3600");
    header("Access-Control-Allow-Credentials: true");
    exit();
}

// Error reporting - FIXED: Better error handling
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/api_error.log');

// Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app');
define('ITEMS_PER_PAGE', 20);
define('CACHE_TIME', 300); // 5 minutes cache

// API Key verification - FIXED: Better header handling
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$apiKey = '';

// Check multiple possible header locations
if (isset($headers['X-API-KEY'])) {
    $apiKey = $headers['X-API-KEY'];
} elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
    $apiKey = $_SERVER['HTTP_X_API_KEY'];
} elseif (isset($_GET['api_key'])) {
    $apiKey = $_GET['api_key'];
}

// Public endpoints that don't require API key
$public_endpoints = ['health', 'products', 'categories'];

$path = $_GET['path'] ?? '';
$path_parts = explode('/', $path);
$base_endpoint = $path_parts[0] ?? '';

// Skip API key verification for public endpoints
if (!in_array($base_endpoint, $public_endpoints) && $apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid or missing API key',
        'code' => 'UNAUTHORIZED'
    ], JSON_PRETTY_PRINT);
    exit();
}

// Parse request
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ============================================
// HEALTH CHECK - FIXED: Added more details
// ============================================
if ($path === 'health' && $method === 'GET') {
    // Test Firebase connection
    $firebase_status = 'unknown';
    $firebase_test = firebase_get('/.json?shallow=true');
    
    if ($firebase_test !== null) {
        $firebase_status = 'connected';
    } else {
        $firebase_status = 'disconnected';
    }
    
    http_response_code(200);
    echo json_encode([
        'status' => 'ok',
        'timestamp' => date('c'),
        'service' => 'Mavro Essence API',
        'version' => '3.2.0',
        'firebase' => $firebase_status,
        'php_version' => phpversion(),
        'memory_usage' => memory_get_usage(true)
    ], JSON_PRETTY_PRINT);
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
    ], JSON_PRETTY_PRINT);
    exit();
}

// ============================================
// CATEGORIES ENDPOINTS
// ============================================

// GET /categories - List categories with pagination
if ($path === 'categories' && $method === 'GET') {
    // Get all categories from Firebase
    $categories_data = firebase_get('/categories.json');
    
    $formatted_categories = [];
    
    if ($categories_data && is_array($categories_data)) {
        foreach ($categories_data as $key => $category) {
            // Handle both indexed and key-value formats
            if (is_array($category)) {
                // Generate slug from name if not present
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
    
    // Sort by ID
    usort($formatted_categories, function($a, $b) {
        return $a['id'] - $b['id'];
    });
    
    // Pagination
    $total = count($formatted_categories);
    $paginated = array_slice($formatted_categories, $offset, $per_page);
    
    // Calculate meta
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
    ], JSON_PRETTY_PRINT);
    exit();
}

// POST /categories - Create new category
if ($path === 'categories' && $method === 'POST') {
    // Get category name from request
    $name = $input['name'] ?? '';
    
    if (empty($name)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The name field is required.',
            'errors' => [
                'name' => ['The name field is required.']
            ]
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Get all existing categories
    $existing_categories = firebase_get('/categories.json') ?? [];
    
    // Check for duplicate name
    foreach ($existing_categories as $cat) {
        if (is_array($cat) && isset($cat['name']) && strtolower($cat['name']) === strtolower($name)) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Category with this name already exists'
            ], JSON_PRETTY_PRINT);
            exit();
        }
    }
    
    // Generate new ID
    $max_id = 0;
    foreach ($existing_categories as $cat) {
        if (is_array($cat) && isset($cat['id']) && $cat['id'] > $max_id) {
            $max_id = $cat['id'];
        }
    }
    $new_id = $max_id + 1;
    
    // Generate slug
    $slug = generate_slug($name);
    $timestamp = date('c');
    
    // Create category data
    $category_data = [
        'id' => $new_id,
        'name' => $name,
        'slug' => $slug,
        'created_at' => $timestamp
    ];
    
    // Save to Firebase using the ID as key
    $save_result = firebase_put("/categories/{$new_id}.json", $category_data);
    
    if ($save_result) {
        http_response_code(201);
        echo json_encode([
            'data' => $category_data
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to create category'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// ============================================
// PRODUCTS ENDPOINTS - FIXED: Complete rewrite
// ============================================

// GET /products - List products (FIXED: Better handling)
if ($path === 'products' && $method === 'GET') {
    try {
        // Get products from Firebase
        $products_data = firebase_get('/products.json');
        
        $formatted_products = [];
        $total_count = 0;
        
        if ($products_data && is_array($products_data)) {
            foreach ($products_data as $key => $product) {
                if (is_array($product)) {
                    $formatted = format_product_for_movedrop($key, $product);
                    if ($formatted) {
                        $formatted_products[] = $formatted;
                    }
                    $total_count++;
                }
            }
        }
        
        // Sort by created_at (newest first)
        usort($formatted_products, function($a, $b) {
            return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
        });
        
        // Apply pagination
        $paginated_products = array_slice($formatted_products, $offset, $per_page);
        
        // Calculate meta
        $last_page = ceil($total_count / $per_page);
        $from = $offset + 1;
        $to = min($offset + $per_page, $total_count);
        
        http_response_code(200);
        echo json_encode([
            'data' => $paginated_products,
            'meta' => [
                'current_page' => $page,
                'from' => $from,
                'last_page' => $last_page,
                'per_page' => $per_page,
                'to' => $to,
                'total' => $total_count
            ]
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
    } catch (Exception $e) {
        error_log("Products API Error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to fetch products',
            'error' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// GET /products/{id} - Get single product (FIXED: New endpoint)
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'GET') {
    $product_id = $matches[1];
    
    $product = firebase_get("/products/{$product_id}.json");
    
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Product not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    $formatted = format_product_for_movedrop($product_id, $product);
    
    http_response_code(200);
    echo json_encode([
        'data' => $formatted
    ], JSON_PRETTY_PRINT);
    exit();
}

// POST /products - Create new product (FIXED: Better validation)
if ($path === 'products' && $method === 'POST') {
    // Validate required fields
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
        ], JSON_PRETTY_PRINT);
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
                            'product_id' => $key,
                            'sku' => $input['sku']
                        ]
                    ]
                ]
            ], JSON_PRETTY_PRINT);
            exit();
        }
    }
    
    // Generate product ID
    $product_id = uniqid('prod_');
    $timestamp = date('c');
    
    // Process images
    $images = [];
    foreach ($input['images'] as $img) {
        if (is_array($img)) {
            $images[] = [
                'is_default' => $img['is_default'] ?? false,
                'src' => $img['src'] ?? ''
            ];
        } elseif (is_string($img)) {
            $images[] = [
                'is_default' => false,
                'src' => $img
            ];
        }
    }
    
    // Ensure at least one default image
    if (!empty($images) && !in_array(true, array_column($images, 'is_default'))) {
        $images[0]['is_default'] = true;
    }
    
    // Prepare product data
    $product_data = [
        'id' => $product_id,
        'title' => $input['title'],
        'name' => $input['title'],
        'sku' => $input['sku'],
        'description' => $input['description'] ?? '',
        'price' => $input['price'] ?? 0,
        'images' => $images,
        'category_ids' => $input['category_ids'] ?? [],
        'tags' => $input['tags'] ?? [],
        'properties' => $input['properties'] ?? [],
        'variations' => $input['variations'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp
    ];
    
    // Save to Firebase
    $result = firebase_put("/products/{$product_id}.json", $product_data);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Product Created',
            'data' => [
                'id' => $product_id,
                'title' => $product_data['title'],
                'sku' => $product_data['sku'],
                'created_at' => $timestamp
            ]
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to create product'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// PUT /products/{id} - Update product (FIXED: New endpoint)
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'PUT') {
    $product_id = $matches[1];
    
    // Check if product exists
    $existing = firebase_get("/products/{$product_id}.json");
    if (!$existing) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Product not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Prepare update data
    $update_data = array_merge($existing, $input);
    $update_data['updated_at'] = date('c');
    
    $result = firebase_put("/products/{$product_id}.json", $update_data);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Product updated successfully',
            'data' => format_product_for_movedrop($product_id, $update_data)
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to update product'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// DELETE /products/{id} - Delete product
if (preg_match('/^products\/(.+)$/', $path, $matches) && $method === 'DELETE') {
    $product_id = $matches[1];
    
    // Check if product exists
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Product not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Delete product
    $result = firebase_delete("/products/{$product_id}.json");
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Product Deleted Successfully'
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to delete product'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// POST /products/:id/variations - Create product variations
if (preg_match('/^products\/(.+)\/variations$/', $path, $matches) && $method === 'POST') {
    $product_id = $matches[1];
    $variations = $input['variations'] ?? [];
    
    if (empty($variations)) {
        http_response_code(400);
        echo json_encode([
            'message' => 'No variations provided'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Get existing product
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Product not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    $saved_variations = [];
    $existing_variations = $product['variations'] ?? [];
    
    foreach ($variations as $index => $var) {
        $variation_id = uniqid('var_');
        
        $variation_data = [
            'id' => $variation_id,
            'sku' => $var['sku'] ?? '',
            'regular_price' => (string)($var['regular_price'] ?? '0'),
            'sale_price' => (string)($var['sale_price'] ?? ''),
            'stock_quantity' => (int)($var['stock_quantity'] ?? 0),
            'image' => $var['image'] ?? '',
            'properties' => $var['properties'] ?? []
        ];
        
        $existing_variations[$variation_id] = $variation_data;
        $saved_variations[] = [
            'id' => $variation_id,
            'sku' => $var['sku'] ?? ''
        ];
    }
    
    // Update product with new variations
    $product['variations'] = $existing_variations;
    $product['updated_at'] = date('c');
    
    $result = firebase_put("/products/{$product_id}.json", $product);
    
    if ($result) {
        http_response_code(201);
        echo json_encode([
            'message' => 'Product Variations Created',
            'data' => $saved_variations
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to save variations'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// ============================================
// ORDERS ENDPOINTS - FIXED: Better handling
// ============================================

// GET /orders - List orders with filters
if ($path === 'orders' && $method === 'GET') {
    $orders = firebase_get('/orders.json') ?? [];
    
    $formatted = [];
    foreach ($orders as $key => $order) {
        // Apply filters
        if (isset($_GET['order_number']) && ($order['order_number'] ?? '') !== $_GET['order_number']) {
            continue;
        }
        
        if (isset($_GET['status']) && ($order['status'] ?? '') !== $_GET['status']) {
            continue;
        }
        
        $formatted[] = format_order_for_movedrop($key, $order);
    }
    
    // Sort by created_at (newest first)
    usort($formatted, function($a, $b) {
        return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
    });
    
    // Paginate
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
    ], JSON_PRETTY_PRINT);
    exit();
}

// GET /orders/{id} - Get single order
if (preg_match('/^orders\/(.+)$/', $path, $matches) && $method === 'GET') {
    $order_id = $matches[1];
    
    $order = firebase_get("/orders/{$order_id}.json");
    
    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Order not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    http_response_code(200);
    echo json_encode([
        'data' => format_order_for_movedrop($order_id, $order)
    ], JSON_PRETTY_PRINT);
    exit();
}

// PUT /orders/{id} - Update order status
if (preg_match('/^orders\/(.+)$/', $path, $matches) && $method === 'PUT') {
    $order_id = $matches[1];
    
    // Check if order exists
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Order not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Update order
    $update_data = array_merge($order, $input);
    $update_data['updated_at'] = date('c');
    
    $result = firebase_put("/orders/{$order_id}.json", $update_data);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Order updated successfully',
            'data' => format_order_for_movedrop($order_id, $update_data)
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to update order'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// POST /orders/:id/timelines - Add order timeline
if (preg_match('/^orders\/(.+)\/timelines$/', $path, $matches) && $method === 'POST') {
    $order_id = $matches[1];
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'Message is required'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Check if order exists
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        http_response_code(404);
        echo json_encode([
            'message' => 'Order not found'
        ], JSON_PRETTY_PRINT);
        exit();
    }
    
    // Add timeline
    $timeline_id = uniqid('timeline_');
    $timeline_data = [
        'id' => $timeline_id,
        'message' => $message,
        'created_at' => date('c')
    ];
    
    $timelines = $order['timelines'] ?? [];
    $timelines[$timeline_id] = $timeline_data;
    
    $order['timelines'] = $timelines;
    $order['updated_at'] = date('c');
    
    $result = firebase_put("/orders/{$order_id}.json", $order);
    
    if ($result) {
        http_response_code(200);
        echo json_encode([
            'message' => 'Timeline added successfully',
            'data' => format_order_for_movedrop($order_id, $order)
        ], JSON_PRETTY_PRINT);
    } else {
        http_response_code(500);
        echo json_encode([
            'message' => 'Failed to add timeline'
        ], JSON_PRETTY_PRINT);
    }
    exit();
}

// ============================================
// DEFAULT RESPONSE - FIXED: Better error message
// ============================================
http_response_code(404);
echo json_encode([
    'status' => 'error',
    'message' => 'Endpoint not found',
    'path' => $path,
    'method' => $method,
    'available_endpoints' => [
        'GET /health',
        'GET /products',
        'GET /products/{id}',
        'POST /products',
        'PUT /products/{id}',
        'DELETE /products/{id}',
        'GET /categories',
        'POST /categories',
        'GET /orders',
        'GET /orders/{id}',
        'PUT /orders/{id}',
        'POST /webhooks'
    ]
], JSON_PRETTY_PRINT);
exit();

// ============================================
// HELPER FUNCTIONS - FIXED: Better error handling
// ============================================

/**
 * Generate URL slug from string
 */
function generate_slug($string) {
    // Convert to lowercase
    $slug = strtolower($string);
    
    // Replace non-alphanumeric characters with hyphens
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    return $slug ?: 'category';
}

/**
 * Format product for MoveDrop API - FIXED: Better null handling
 */
function format_product_for_movedrop($id, $product) {
    if (!is_array($product)) {
        return null;
    }
    
    $images = [];
    if (!empty($product['images'])) {
        foreach ($product['images'] as $img) {
            if (is_string($img)) {
                $images[] = [
                    'is_default' => false,
                    'src' => $img
                ];
            } elseif (is_array($img)) {
                $images[] = [
                    'is_default' => $img['is_default'] ?? false,
                    'src' => $img['src'] ?? ''
                ];
            }
        }
    }
    
    // Ensure at least one default image
    if (!empty($images) && !in_array(true, array_column($images, 'is_default'))) {
        $images[0]['is_default'] = true;
    }
    
    $variations = [];
    if (!empty($product['variations'])) {
        foreach ($product['variations'] as $var_id => $var) {
            if (is_array($var)) {
                $variations[] = [
                    'id' => $var_id,
                    'sku' => $var['sku'] ?? '',
                    'regular_price' => $var['regular_price'] ?? '0',
                    'sale_price' => $var['sale_price'] ?? '',
                    'stock_quantity' => (int)($var['stock_quantity'] ?? 0),
                    'image' => $var['image'] ?? '',
                    'properties' => $var['properties'] ?? []
                ];
            }
        }
    }
    
    return [
        'id' => $id,
        'title' => $product['title'] ?? $product['name'] ?? 'Untitled',
        'sku' => $product['sku'] ?? '',
        'description' => $product['description'] ?? '',
        'price' => (float)($product['price'] ?? 0),
        'images' => $images,
        'category_ids' => $product['category_ids'] ?? [],
        'tags' => $product['tags'] ?? [],
        'properties' => $product['properties'] ?? [],
        'variations' => $variations,
        'created_at' => $product['created_at'] ?? date('c'),
        'updated_at' => $product['updated_at'] ?? date('c')
    ];
}

/**
 * Format order for MoveDrop API - FIXED: Better null handling
 */
function format_order_for_movedrop($id, $order) {
    if (!is_array($order)) {
        return null;
    }
    
    $shipping_address = $order['shipping_address'] ?? [];
    if (!is_array($shipping_address)) {
        $shipping_address = [];
    }
    
    $line_items = [];
    if (!empty($order['items'])) {
        foreach ($order['items'] as $item) {
            if (is_array($item)) {
                $line_items[] = [
                    'id' => $item['id'] ?? uniqid(),
                    'product_id' => $item['product_id'] ?? '',
                    'name' => $item['name'] ?? '',
                    'quantity' => (int)($item['quantity'] ?? 1),
                    'price' => (float)($item['price'] ?? 0),
                    'total' => (float)($item['total'] ?? 0)
                ];
            }
        }
    }
    
    $timelines = [];
    if (!empty($order['timelines'])) {
        foreach ($order['timelines'] as $timeline) {
            if (is_array($timeline)) {
                $timelines[] = [
                    'id' => $timeline['id'] ?? uniqid(),
                    'message' => $timeline['message'] ?? '',
                    'created_at' => $timeline['created_at'] ?? date('c')
                ];
            }
        }
    }
    
    return [
        'id' => $id,
        'order_number' => $order['order_number'] ?? '#' . substr($id, -6),
        'status' => $order['status'] ?? 'pending',
        'currency' => $order['currency'] ?? 'BDT',
        'subtotal' => (float)($order['subtotal'] ?? 0),
        'total' => (float)($order['total'] ?? 0),
        'delivery_charge' => (float)($order['delivery_charge'] ?? 0),
        'payment_method' => $order['payment']['method'] ?? 'cod',
        'payment_status' => $order['payment']['status'] ?? 'pending',
        'customer' => [
            'name' => $order['customer']['name'] ?? '',
            'phone' => $order['customer']['phone'] ?? '',
            'email' => $order['customer']['email'] ?? '',
            'address' => $order['customer']['address'] ?? '',
            'city' => $order['customer']['city'] ?? '',
            'postcode' => $order['customer']['postcode'] ?? ''
        ],
        'shipping_address' => $shipping_address,
        'line_items' => $line_items,
        'timelines' => $timelines,
        'note' => $order['note'] ?? '',
        'coupon' => $order['coupon'] ?? null,
        'created_at' => $order['created_at'] ?? date('c'),
        'updated_at' => $order['updated_at'] ?? date('c')
    ];
}

/**
 * Firebase GET request - FIXED: Better error handling and timeout
 */
function firebase_get($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 3,
        CURLOPT_USERAGENT => 'Mavro-API/3.2.0'
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
    
    error_log("Firebase GET HTTP Error: " . $http_code . " - " . $response);
    return null;
}

/**
 * Firebase PUT request - FIXED: Better error handling
 */
function firebase_put($endpoint, $data) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PUT',
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Firebase PUT Error: " . $curl_error);
        return false;
    }
    
    return $http_code === 200;
}

/**
 * Firebase PATCH request - FIXED: Better error handling
 */
function firebase_patch($endpoint, $data) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Firebase PATCH Error: " . $curl_error);
        return false;
    }
    
    return $http_code === 200;
}

/**
 * Firebase DELETE request - FIXED: Better error handling
 */
function firebase_delete($endpoint) {
    $url = FIREBASE_URL . $endpoint;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        error_log("Firebase DELETE Error: " . $curl_error);
        return false;
    }
    
    return $http_code === 200;
}
?>
