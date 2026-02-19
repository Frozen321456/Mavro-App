<?php
/**
 * MAVRO ESSENCE - MoveDrop Custom Channel API
 * Version: 4.0.0
 * Full MoveDrop API Specification Implementation
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

// API Key verification from header
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$apiKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($apiKey !== API_KEY) {
    http_response_code(401);
    echo json_encode([
        'message' => 'Invalid or missing API key'
    ]);
    exit();
}

// Parse request
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);
$path = str_replace('/index.php', '', $path);
$path = ltrim($path, '/');
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Get query parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : ITEMS_PER_PAGE;
$offset = ($page - 1) * $per_page;

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Generate URL slug from string
 */
function generate_slug($string) {
    $slug = strtolower($string);
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim($slug, '-');
    return $slug;
}

/**
 * Format category for MoveDrop API
 */
function format_category($id, $category) {
    $name = $category['name'] ?? $category ?? 'Unnamed Category';
    return [
        'id' => (int)($category['id'] ?? (is_numeric($id) ? $id : abs(crc32($id)))),
        'name' => $name,
        'slug' => $category['slug'] ?? generate_slug($name),
        'created_at' => $category['created_at'] ?? date('c')
    ];
}

/**
 * Format product for MoveDrop API
 */
function format_product($id, $product) {
    $images = [];
    if (!empty($product['images'])) {
        foreach ($product['images'] as $img) {
            if (is_string($img)) {
                $images[] = [
                    'is_default' => false,
                    'src' => $img
                ];
            } else {
                $images[] = $img;
            }
        }
    }
    
    // Ensure at least one default image
    if (!empty($images) && !in_array(true, array_column($images, 'is_default'))) {
        $images[0]['is_default'] = true;
    }
    
    // Format properties
    $properties = [];
    if (!empty($product['properties'])) {
        foreach ($product['properties'] as $prop) {
            if (isset($prop['name']) && isset($prop['values'])) {
                $properties[] = [
                    'name' => $prop['name'],
                    'values' => $prop['values']
                ];
            }
        }
    }
    
    return [
        'id' => (int)($product['id'] ?? (is_numeric($id) ? $id : abs(crc32($id)))),
        'title' => $product['title'] ?? $product['name'] ?? 'Untitled',
        'sku' => $product['sku'] ?? '',
        'description' => $product['description'] ?? '',
        'images' => $images,
        'category_ids' => array_map('intval', $product['category_ids'] ?? []),
        'tags' => $product['tags'] ?? [],
        'properties' => $properties,
        'created_at' => $product['created_at'] ?? date('c'),
        'updated_at' => $product['updated_at'] ?? date('c')
    ];
}

/**
 * Format order for MoveDrop API
 */
function format_order($id, $order) {
    // Format shipping address
    $shipping = $order['shipping_address'] ?? $order['customer'] ?? [];
    
    // Format line items
    $line_items = [];
    foreach ($order['items'] ?? $order['line_items'] ?? [] as $item) {
        $variations = [];
        if (!empty($item['variations'])) {
            foreach ($item['variations'] as $var) {
                $variations[] = [
                    'id' => (int)($var['id'] ?? abs(crc32(uniqid()))),
                    'variation_id' => (int)($var['variation_id'] ?? $var['id'] ?? 0),
                    'sku' => $var['sku'] ?? '',
                    'quantity' => (int)($var['quantity'] ?? 1),
                    'price' => number_format((float)($var['price'] ?? 0), 2, '.', ''),
                    'created_at' => $var['created_at'] ?? $order['created_at'] ?? date('c')
                ];
            }
        } else {
            // Single item without variations
            $variations[] = [
                'id' => (int)abs(crc32(uniqid())),
                'variation_id' => (int)($item['variation_id'] ?? 0),
                'sku' => $item['sku'] ?? '',
                'quantity' => (int)($item['quantity'] ?? 1),
                'price' => number_format((float)($item['price'] ?? 0), 2, '.', ''),
                'created_at' => $order['created_at'] ?? date('c')
            ];
        }
        
        $line_items[] = [
            'id' => (int)($item['id'] ?? abs(crc32(uniqid()))),
            'product_id' => (int)($item['product_id'] ?? $item['id'] ?? 0),
            'name' => $item['name'] ?? 'Product',
            'quantity' => (int)($item['quantity'] ?? 1),
            'total' => number_format((float)($item['total'] ?? 0), 2, '.', ''),
            'variations' => $variations,
            'created_at' => $item['created_at'] ?? $order['created_at'] ?? date('c')
        ];
    }
    
    return [
        'id' => (int)(is_numeric($id) ? $id : abs(crc32($id))),
        'order_number' => $order['order_number'] ?? $id,
        'status' => $order['status'] ?? 'pending',
        'currency' => $order['currency'] ?? 'BDT',
        'total' => number_format((float)($order['total'] ?? 0), 2, '.', ''),
        'payment_method' => $order['payment_method'] ?? ($order['payment']['method'] ?? 'cod'),
        'shipping_address' => [
            'first_name' => $shipping['first_name'] ?? $shipping['name'] ?? explode(' ', $shipping['full_name'] ?? '')[0] ?? 'Customer',
            'last_name' => $shipping['last_name'] ?? (isset($shipping['name']) ? implode(' ', array_slice(explode(' ', $shipping['name']), 1)) : ''),
            'email' => $shipping['email'] ?? $order['customer']['email'] ?? '',
            'phone' => $shipping['phone'] ?? $order['customer']['phone'] ?? '',
            'address_1' => $shipping['address_1'] ?? $shipping['address'] ?? $order['customer']['address'] ?? '',
            'address_2' => $shipping['address_2'] ?? '',
            'city' => $shipping['city'] ?? $order['customer']['city'] ?? 'Dhaka',
            'state' => $shipping['state'] ?? '',
            'postcode' => $shipping['postcode'] ?? $order['customer']['postcode'] ?? '1200',
            'country' => $shipping['country'] ?? 'Bangladesh'
        ],
        'customer_notes' => $order['customer_notes'] ?? $order['note'] ?? '',
        'line_items' => $line_items,
        'created_at' => $order['created_at'] ?? date('c')
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

/**
 * Validate required fields
 */
function validate_required($data, $fields) {
    $errors = [];
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            $errors[$field] = ["The {$field} field is required."];
        }
    }
    return $errors;
}

// ============================================
// WEBHOOKS ENDPOINT
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
    $categories_data = firebase_get('/categories.json') ?? [];
    
    $formatted = [];
    foreach ($categories_data as $key => $category) {
        $formatted[] = format_category($key, $category);
    }
    
    // Sort by ID
    usort($formatted, function($a, $b) {
        return $a['id'] - $b['id'];
    });
    
    // Pagination
    $total = count($formatted);
    $paginated = array_slice($formatted, $offset, $per_page);
    
    $from = $total > 0 ? $offset + 1 : 0;
    $to = min($offset + $per_page, $total);
    $last_page = ceil($total / $per_page);
    
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
    $errors = validate_required($input, ['name']);
    
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The name field is required.',
            'errors' => $errors
        ]);
        exit();
    }
    
    $name = $input['name'];
    
    // Check for duplicate name
    $existing = firebase_get('/categories.json') ?? [];
    foreach ($existing as $cat) {
        if (is_array($cat) && isset($cat['name']) && strtolower($cat['name']) === strtolower($name)) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Category with this name already exists'
            ]);
            exit();
        }
    }
    
    // Generate new ID
    $max_id = 0;
    foreach ($existing as $cat) {
        if (is_array($cat) && isset($cat['id']) && $cat['id'] > $max_id) {
            $max_id = $cat['id'];
        }
    }
    $new_id = $max_id + 1;
    
    $category_data = [
        'id' => $new_id,
        'name' => $name,
        'slug' => generate_slug($name),
        'created_at' => date('c')
    ];
    
    firebase_put("/categories/{$new_id}.json", $category_data);
    
    http_response_code(201);
    echo json_encode([
        'data' => $category_data
    ]);
    exit();
}

// ============================================
// PRODUCTS ENDPOINTS
// ============================================

// POST /products - Create new product
if ($path === 'products' && $method === 'POST') {
    $errors = validate_required($input, ['title', 'sku', 'images']);
    
    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'Validation failed',
            'errors' => $errors
        ]);
        exit();
    }
    
    if (!is_array($input['images']) || empty($input['images'])) {
        http_response_code(422);
        echo json_encode([
            'message' => 'Validation failed',
            'errors' => ['images' => ['At least one image is required.']]
        ]);
        exit();
    }
    
    // Check for duplicate SKU
    $existing = firebase_get('/products.json') ?? [];
    foreach ($existing as $key => $prod) {
        if (isset($prod['sku']) && $prod['sku'] === $input['sku']) {
            http_response_code(400);
            echo json_encode([
                'message' => 'Product with given SKU already exists',
                'data' => [
                    'error' => [
                        'code' => 'product_duplicate_sku',
                        'message' => 'SKU already exists.',
                        'data' => [
                            'product_id' => (int)($prod['id'] ?? abs(crc32($key))),
                            'sku' => $input['sku']
                        ]
                    ]
                ]
            ]);
            exit();
        }
    }
    
    // Generate product ID
    $product_id = time();
    $timestamp = date('c');
    
    $product_data = [
        'id' => $product_id,
        'title' => $input['title'],
        'sku' => $input['sku'],
        'description' => $input['description'] ?? '',
        'images' => $input['images'],
        'category_ids' => array_map('intval', $input['category_ids'] ?? []),
        'tags' => $input['tags'] ?? [],
        'properties' => $input['properties'] ?? [],
        'created_at' => $timestamp,
        'updated_at' => $timestamp
    ];
    
    firebase_put("/products/{$product_id}.json", $product_data);
    
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
    exit();
}

// POST /products/:id/variations - Create product variations
if (preg_match('/^products\/(\d+)\/variations$/', $path, $matches) && $method === 'POST') {
    $product_id = $matches[1];
    $variations = $input['variations'] ?? [];
    
    if (empty($variations)) {
        http_response_code(400);
        echo json_encode(['message' => 'No variations provided']);
        exit();
    }
    
    // Get existing product
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        exit();
    }
    
    $saved_variations = [];
    $existing_skus = [];
    
    // Get existing SKUs
    $existing_variations = $product['variations'] ?? [];
    foreach ($existing_variations as $var) {
        if (!empty($var['sku'])) {
            $existing_skus[$var['sku']] = true;
        }
    }
    
    foreach ($variations as $index => $var) {
        $variation_id = (int)($product_id * 100 + $index + 1);
        
        // Validate required fields for variation
        if (empty($var['sku'])) {
            $saved_variations[] = [
                'error' => [
                    'code' => 'variation_sku_required',
                    'message' => 'SKU is required.',
                    'data' => ['variation_index' => $index]
                ]
            ];
            continue;
        }
        
        // Check for duplicate SKU
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
        
        // Format properties
        $properties = [];
        if (!empty($var['properties'])) {
            foreach ($var['properties'] as $prop) {
                $properties[] = [
                    'name' => $prop['name'] ?? '',
                    'value' => $prop['value'] ?? ''
                ];
            }
        }
        
        $variation_data = [
            'id' => $variation_id,
            'sku' => $var['sku'],
            'regular_price' => number_format((float)($var['regular_price'] ?? 0), 2, '.', ''),
            'sale_price' => isset($var['sale_price']) ? number_format((float)$var['sale_price'], 2, '.', '') : null,
            'date_on_sale_from' => $var['date_on_sale_from'] ?? null,
            'date_on_sale_to' => $var['date_on_sale_to'] ?? null,
            'stock_quantity' => (int)($var['stock_quantity'] ?? 0),
            'image' => $var['image'] ?? '',
            'properties' => $properties
        ];
        
        // Save variation
        firebase_put("/products/{$product_id}/variations/{$variation_id}.json", $variation_data);
        
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
if (preg_match('/^products\/(\d+)$/', $path, $matches) && $method === 'DELETE') {
    $product_id = $matches[1];
    
    $product = firebase_get("/products/{$product_id}.json");
    if (!$product) {
        http_response_code(404);
        echo json_encode(['message' => 'Product not found']);
        exit();
    }
    
    firebase_delete("/products/{$product_id}.json");
    
    http_response_code(200);
    echo json_encode(['message' => 'Product Deleted Successfully']);
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
        // Filter by order number if provided
        if (isset($_GET['order_number']) && ($order['order_number'] ?? '') !== $_GET['order_number']) {
            continue;
        }
        
        // Filter by created_at range if provided
        if (isset($_GET['created_at']) && is_array($_GET['created_at'])) {
            $from = $_GET['created_at'][0] ?? null;
            $to = $_GET['created_at'][1] ?? null;
            $created = $order['created_at'] ?? '';
            
            if ($from && $created < $from) continue;
            if ($to && $created > $to) continue;
        }
        
        $formatted[] = format_order($key, $order);
    }
    
    // Sort by created_at desc
    usort($formatted, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Pagination
    $total = count($formatted);
    $paginated = array_slice($formatted, $offset, $per_page);
    
    $from = $total > 0 ? $offset + 1 : 0;
    $to = min($offset + $per_page, $total);
    $last_page = ceil($total / $per_page);
    
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

// PUT /orders/:id - Update order status
if (preg_match('/^orders\/(\d+)$/', $path, $matches) && $method === 'PUT') {
    $order_id = $matches[1];
    $status = $input['status'] ?? '';
    
    $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
    
    if (!in_array($status, $valid_statuses)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The status field must be one of: ' . implode(', ', $valid_statuses),
            'errors' => ['status' => ['Invalid status value']]
        ]);
        exit();
    }
    
    // Try to find order by ID or order_number
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        // Try to find by order_number
        $all_orders = firebase_get('/orders.json') ?? [];
        $found_key = null;
        foreach ($all_orders as $key => $ord) {
            if (($ord['order_number'] ?? '') === $order_id || $key === $order_id) {
                $order = $ord;
                $found_key = $key;
                break;
            }
        }
        if (!$order) {
            http_response_code(404);
            echo json_encode(['message' => 'Order not found']);
            exit();
        }
        $order_id = $found_key;
    }
    
    firebase_patch("/orders/{$order_id}.json", [
        'status' => $status,
        'updated_at' => date('c')
    ]);
    
    // Get updated order
    $updated = firebase_get("/orders/{$order_id}.json");
    
    http_response_code(200);
    echo json_encode([
        'data' => format_order($order_id, $updated)
    ]);
    exit();
}

// POST /orders/:id/timelines - Add order timeline
if (preg_match('/^orders\/(\d+)\/timelines$/', $path, $matches) && $method === 'POST') {
    $order_id = $matches[1];
    $message = $input['message'] ?? '';
    
    if (empty($message)) {
        http_response_code(422);
        echo json_encode([
            'message' => 'The message field is required.',
            'errors' => ['message' => ['The message field is required.']]
        ]);
        exit();
    }
    
    // Try to find order
    $order = firebase_get("/orders/{$order_id}.json");
    if (!$order) {
        $all_orders = firebase_get('/orders.json') ?? [];
        $found_key = null;
        foreach ($all_orders as $key => $ord) {
            if (($ord['order_number'] ?? '') === $order_id || $key === $order_id) {
                $order = $ord;
                $found_key = $key;
                break;
            }
        }
        if (!$order) {
            http_response_code(404);
            echo json_encode(['message' => 'Order not found']);
            exit();
        }
        $order_id = $found_key;
    }
    
    $timeline_id = time();
    $timeline_data = [
        'id' => $timeline_id,
        'message' => $message,
        'created_at' => date('c')
    ];
    
    firebase_put("/orders/{$order_id}/timelines/{$timeline_id}.json", $timeline_data);
    
    // Get updated order
    $updated = firebase_get("/orders/{$order_id}.json");
    
    http_response_code(200);
    echo json_encode([
        'data' => format_order($order_id, $updated)
    ]);
    exit();
}

// ============================================
// DEFAULT RESPONSE - 404 Not Found
// ============================================
http_response_code(404);
echo json_encode([
    'message' => 'Endpoint not found'
]);
?>
