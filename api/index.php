<?php
/**
 * MoveDrop Custom Channel - Working with MoveDrop Category IDs
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
define('FIREBASE_PROJECT_ID', 'espera-mavro-6ddc5');
define('FIREBASE_API_KEY', 'AIzaSyAB7dyaJwkadV7asGOhj6TCN5it5pCWg10');

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

// 4. Firestore REST API Request
function firestoreRequest($collection, $method = 'GET', $documentId = null, $data = null) {
    $baseUrl = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents";
    
    // Build URL
    if ($documentId) {
        $url = $baseUrl . "/" . $collection . "/" . $documentId;
    } else {
        $url = $baseUrl . "/" . $collection;
    }
    
    // Add API key
    $url .= "?key=" . FIREBASE_API_KEY;
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    
    // Add body for POST/PUT/PATCH
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        // Convert to Firestore format
        $firestoreData = ['fields' => []];
        
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $firestoreData['fields'][$key] = ['stringValue' => $value];
            } elseif (is_int($value)) {
                $firestoreData['fields'][$key] = ['integerValue' => $value];
            } elseif (is_float($value)) {
                $firestoreData['fields'][$key] = ['doubleValue' => $value];
            } elseif (is_bool($value)) {
                $firestoreData['fields'][$key] = ['booleanValue' => $value];
            } elseif (is_array($value)) {
                // Handle arrays
                $arrayValues = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $arrayValues[] = ['stringValue' => $item];
                    } elseif (is_int($item)) {
                        $arrayValues[] = ['integerValue' => $item];
                    }
                }
                $firestoreData['fields'][$key] = ['arrayValue' => ['values' => $arrayValues]];
            }
        }
        
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        error_log("Firestore error: " . $response);
        return null;
    }
    
    return json_decode($response, true);
}

// 5. Parse Firestore document to simple array
function parseFirestoreDocument($doc) {
    $result = [];
    
    if (isset($doc['name'])) {
        $result['id'] = basename($doc['name']);
    }
    
    if (isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            if (isset($value['stringValue'])) {
                $result[$key] = $value['stringValue'];
            } elseif (isset($value['integerValue'])) {
                $result[$key] = (int)$value['integerValue'];
            } elseif (isset($value['arrayValue']) && isset($value['arrayValue']['values'])) {
                $result[$key] = [];
                foreach ($value['arrayValue']['values'] as $item) {
                    if (isset($item['stringValue'])) {
                        $result[$key][] = $item['stringValue'];
                    } elseif (isset($item['integerValue'])) {
                        $result[$key][] = (int)$item['integerValue'];
                    }
                }
            }
        }
    }
    
    return $result;
}

// 6. Get all documents from a collection
function getCollection($collection) {
    $response = firestoreRequest($collection, 'GET');
    $items = [];
    
    if ($response && isset($response['documents'])) {
        foreach ($response['documents'] as $doc) {
            $items[] = parseFirestoreDocument($doc);
        }
    }
    
    return $items;
}

// 7. Generate slug
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 8. Pagination helper
function paginate($data, $page = 1, $perPage = 20) {
    if (!is_array($data)) $data = [];
    $total = count($data);
    $offset = ($page - 1) * $perPage;
    $items = array_slice($data, $offset, $perPage);
    
    return [
        "data" => $items,
        "meta" => [
            "current_page" => (int)$page,
            "from" => $offset + 1,
            "last_page" => max(1, ceil($total / $perPage)),
            "per_page" => (int)$perPage,
            "to" => min($offset + $perPage, $total),
            "total" => $total
        ]
    ];
}

// 9. Store MoveDrop category mapping
function saveCategoryMapping($moveDropId, $firestoreId, $name) {
    $mapping = [
        'moveDropId' => $moveDropId,
        'firestoreId' => $firestoreId,
        'name' => $name,
        'created_at' => date('Y-m-d H:i:s')
    ];
    firestoreRequest('category_mapping', 'POST', null, $mapping);
}

// 10. Get MoveDrop category ID from Firestore ID
function getMoveDropCategoryId($firestoreId) {
    // For now, return a numeric ID based on Firestore ID
    // In production, you'd query the category_mapping collection
    $hash = 0;
    for ($i = 0; $i < strlen($firestoreId); $i++) {
        $hash = (($hash << 5) - $hash) + ord($firestoreId[$i]);
    }
    return abs($hash) % 1000 ?: 1;
}

// 11. API Routing
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

// Authentication
verifyKey();

error_log("MoveDrop API: $method $requestPath");

// --- Router ---
switch ($pathParts[0]) {
    
    // Webhooks - Store MoveDrop webhooks
    case 'webhooks':
        if ($method === 'POST') {
            $webhooks = $inputData['webhooks'] ?? [];
            $results = [];
            
            foreach ($webhooks as $webhook) {
                $data = [
                    'name' => $webhook['name'] ?? '',
                    'event' => $webhook['event'] ?? '',
                    'delivery_url' => $webhook['delivery_url'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $response = firestoreRequest('webhooks', 'POST', null, $data);
                if ($response) {
                    $results[] = $webhook;
                }
            }
            
            http_response_code(201);
            echo json_encode([
                "message" => "Webhooks Registered",
                "data" => $results
            ]);
        }
        break;

    // Categories - MoveDrop is requesting categories
    case 'categories':
        if ($method === 'GET') {
            // Return categories in the format MoveDrop expects
            $categories = getCollection('categories');
            
            $formatted = [];
            foreach ($categories as $index => $cat) {
                // Generate a numeric ID for MoveDrop
                $moveDropId = getMoveDropCategoryId($cat['id'] ?? 'cat_' . $index);
                
                $formatted[] = [
                    "id" => $moveDropId,
                    "name" => $cat['name'] ?? 'Unnamed',
                    "slug" => $cat['slug'] ?? generateSlug($cat['name'] ?? ''),
                    "created_at" => $cat['created_at'] ?? date('Y-m-d H:i:s')
                ];
                
                // Store the mapping
                saveCategoryMapping($moveDropId, $cat['id'] ?? '', $cat['name'] ?? '');
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            // MoveDrop is creating a category in our system
            $name = $inputData['name'] ?? '';
            if (!$name) {
                http_response_code(422);
                echo json_encode(["message" => "Category name required"]);
                break;
            }
            
            // Save to our Firestore
            $data = [
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s'),
                'source' => 'movedrop'
            ];
            
            $response = firestoreRequest('categories', 'POST', null, $data);
            
            if ($response && isset($response['name'])) {
                $firestoreId = basename($response['name']);
                $moveDropId = getMoveDropCategoryId($firestoreId);
                
                // Store mapping
                saveCategoryMapping($moveDropId, $firestoreId, $name);
                
                http_response_code(201);
                echo json_encode([
                    "data" => [
                        "id" => $moveDropId,
                        "name" => $name,
                        "slug" => generateSlug($name),
                        "created_at" => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create category"]);
            }
        }
        break;

    // Products
    case 'products':
        $productId = $pathParts[1] ?? null;

        if ($method === 'GET') {
            // Return products to MoveDrop
            $products = getCollection('products');
            
            $formatted = [];
            foreach ($products as $prod) {
                // Get category IDs for MoveDrop
                $categoryIds = [];
                if (isset($prod['category_id'])) {
                    $categoryIds[] = getMoveDropCategoryId($prod['category_id']);
                }
                if (isset($prod['category_ids']) && is_array($prod['category_ids'])) {
                    foreach ($prod['category_ids'] as $catId) {
                        $categoryIds[] = getMoveDropCategoryId($catId);
                    }
                }
                
                // Ensure at least one category ID
                if (empty($categoryIds)) {
                    $categoryIds = [1]; // Default category
                }
                
                $formatted[] = [
                    "id" => $prod['id'] ?? uniqid(),
                    "title" => $prod['title'] ?? $prod['name'] ?? '',
                    "sku" => $prod['sku'] ?? '',
                    "category_ids" => $categoryIds, // CRITICAL: Must be array of numbers
                    "tags" => $prod['tags'] ?? [],
                    "created_at" => $prod['created_at'] ?? date('Y-m-d H:i:s'),
                    "updated_at" => $prod['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            // MoveDrop is creating a product
            $title = $inputData['title'] ?? '';
            $sku = $inputData['sku'] ?? '';
            $category_ids = $inputData['category_ids'] ?? [];
            
            if (!$title || !$sku) {
                http_response_code(422);
                echo json_encode(["message" => "Title and SKU required"]);
                break;
            }
            
            // CRITICAL: Ensure category_ids is an array of numbers
            if (empty($category_ids)) {
                $category_ids = [1]; // Default category
            }
            
            // Save to our Firestore
            $data = [
                'title' => $title,
                'sku' => $sku,
                'description' => $inputData['description'] ?? '',
                'category_ids' => $category_ids, // Store MoveDrop category IDs
                'tags' => $inputData['tags'] ?? [],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $response = firestoreRequest('products', 'POST', null, $data);
            
            if ($response && isset($response['name'])) {
                $id = basename($response['name']);
                http_response_code(201);
                echo json_encode([
                    "message" => "Product Created",
                    "data" => [
                        "id" => $id,
                        "title" => $title,
                        "sku" => $sku,
                        "category_ids" => $category_ids,
                        "tags" => $inputData['tags'] ?? [],
                        "created_at" => date('Y-m-d H:i:s'),
                        "updated_at" => date('Y-m-d H:i:s')
                    ]
                ]);
            } else {
                http_response_code(500);
                echo json_encode(["message" => "Failed to create product"]);
            }
        } elseif ($method === 'DELETE' && $productId) {
            // Delete product
            firestoreRequest('products', 'DELETE', $productId);
            echo json_encode(["message" => "Product Deleted Successfully"]);
        }
        break;

    // Orders
    case 'orders':
        if ($method === 'GET') {
            $orders = getCollection('orders');
            
            $formatted = [];
            foreach ($orders as $order) {
                $formatted[] = [
                    "id" => $order['id'] ?? uniqid(),
                    "order_number" => $order['order_number'] ?? $order['id'],
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
            
            echo json_encode(paginate($formatted, $page, $perPage));
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
