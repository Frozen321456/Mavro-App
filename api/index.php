<?php
/**
 * MoveDrop Custom Channel - Working with Firestore REST API
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

// 4. Firebase Firestore REST API Request
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
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log for debugging
    error_log("Firestore $method $collection - HTTP $httpCode");
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        error_log("Firestore error response: " . $response);
        return null;
    }
    
    return json_decode($response, true);
}

// 5. Parse Firestore documents to simple array
function parseDocuments($response) {
    $items = [];
    
    if (!$response || !isset($response['documents'])) {
        return $items;
    }
    
    foreach ($response['documents'] as $doc) {
        $id = basename($doc['name']);
        $data = [];
        
        if (isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
                // Handle different value types
                if (isset($value['stringValue'])) {
                    $data[$key] = $value['stringValue'];
                } elseif (isset($value['integerValue'])) {
                    $data[$key] = (int)$value['integerValue'];
                } elseif (isset($value['doubleValue'])) {
                    $data[$key] = (float)$value['doubleValue'];
                } elseif (isset($value['booleanValue'])) {
                    $data[$key] = (bool)$value['booleanValue'];
                } elseif (isset($value['arrayValue'])) {
                    $data[$key] = [];
                    if (isset($value['arrayValue']['values'])) {
                        foreach ($value['arrayValue']['values'] as $item) {
                            if (isset($item['stringValue'])) {
                                $data[$key][] = $item['stringValue'];
                            } elseif (isset($item['integerValue'])) {
                                $data[$key][] = (int)$item['integerValue'];
                            }
                        }
                    }
                } elseif (isset($value['mapValue'])) {
                    $data[$key] = $value['mapValue']; // Keep as is for complex objects
                }
            }
        }
        
        $items[$id] = $data;
    }
    
    return $items;
}

// 6. Get single document
function getDocument($collection, $documentId) {
    $response = firestoreRequest($collection, 'GET', $documentId);
    
    if (!$response || !isset($response['fields'])) {
        return null;
    }
    
    $data = [];
    foreach ($response['fields'] as $key => $value) {
        if (isset($value['stringValue'])) {
            $data[$key] = $value['stringValue'];
        } elseif (isset($value['integerValue'])) {
            $data[$key] = (int)$value['integerValue'];
        } elseif (isset($value['doubleValue'])) {
            $data[$key] = (float)$value['doubleValue'];
        } elseif (isset($value['booleanValue'])) {
            $data[$key] = (bool)$value['booleanValue'];
        }
    }
    
    return $data;
}

// 7. Generate slug
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 8. Pagination
function paginate($data, $page = 1, $perPage = 20) {
    if (!is_array($data)) $data = [];
    $data = array_values($data);
    $total = count($data);
    $offset = ($page - 1) * $perPage;
    $items = array_slice($data, $offset, $perPage);
    
    return [
        "data" => $items,
        "meta" => [
            "current_page" => (int)$page,
            "from" => $offset + 1,
            "last_page" => ceil($total / $perPage),
            "per_page" => (int)$perPage,
            "to" => min($offset + $perPage, $total),
            "total" => $total
        ]
    ];
}

// 9. API Routing
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
    
    // Webhooks
    case 'webhooks':
        if ($method === 'POST') {
            $webhooks = $inputData['webhooks'] ?? [];
            $results = [];
            
            foreach ($webhooks as $webhook) {
                $data = [
                    'fields' => [
                        'name' => ['stringValue' => $webhook['name'] ?? ''],
                        'event' => ['stringValue' => $webhook['event'] ?? ''],
                        'delivery_url' => ['stringValue' => $webhook['delivery_url'] ?? ''],
                        'created_at' => ['stringValue' => date('Y-m-d H:i:s')]
                    ]
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

    // Categories
    case 'categories':
        if ($method === 'GET') {
            $response = firestoreRequest('categories', 'GET');
            $categories = parseDocuments($response);
            $formatted = [];
            
            foreach ($categories as $id => $cat) {
                $formatted[] = [
                    "id" => $id,
                    "name" => $cat['name'] ?? '',
                    "slug" => $cat['slug'] ?? generateSlug($cat['name'] ?? ''),
                    "created_at" => $cat['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            $name = $inputData['name'] ?? '';
            if (!$name) {
                http_response_code(422);
                echo json_encode(["message" => "Category name required"]);
                break;
            }
            
            $data = [
                'fields' => [
                    'name' => ['stringValue' => $name],
                    'slug' => ['stringValue' => generateSlug($name)],
                    'created_at' => ['stringValue' => date('Y-m-d H:i:s')]
                ]
            ];
            
            $response = firestoreRequest('categories', 'POST', null, $data);
            
            if ($response && isset($response['name'])) {
                $id = basename($response['name']);
                http_response_code(201);
                echo json_encode([
                    "data" => [
                        "id" => $id,
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
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            // LIST PRODUCTS - FIXED
            $response = firestoreRequest('products', 'GET');
            
            // Log the raw response for debugging
            error_log("Products GET response: " . json_encode($response));
            
            $products = parseDocuments($response);
            $formatted = [];
            
            foreach ($products as $id => $prod) {
                // Skip if it's a dummy document
                if (isset($prod['_dummy']) && $prod['_dummy'] === true) {
                    continue;
                }
                
                $formatted[] = [
                    "id" => $id,
                    "title" => $prod['title'] ?? $prod['name'] ?? 'Untitled',
                    "sku" => $prod['sku'] ?? 'SKU-' . substr($id, 0, 8),
                    "tags" => $prod['tags'] ?? [],
                    "created_at" => $prod['created_at'] ?? date('Y-m-d H:i:s'),
                    "updated_at" => $prod['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode(paginate($formatted, $page, $perPage));
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // Add variations to existing product
                $variations = $inputData['variations'] ?? [];
                
                // Get existing product
                $product = getDocument('products', $productId);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(["message" => "Product not found"]);
                    break;
                }
                
                // Update product with variations
                $updateData = [
                    'fields' => [
                        'variations' => ['arrayValue' => ['values' => array_map(function($var) {
                            return ['mapValue' => ['fields' => [
                                'sku' => ['stringValue' => $var['sku'] ?? ''],
                                'regular_price' => ['doubleValue' => $var['regular_price'] ?? 0],
                                'sale_price' => isset($var['sale_price']) ? ['doubleValue' => $var['sale_price']] : ['nullValue' => null],
                                'stock_quantity' => ['integerValue' => $var['stock_quantity'] ?? 0],
                                'image' => ['stringValue' => $var['image'] ?? ''],
                                'properties' => ['arrayValue' => ['values' => array_map(function($prop) {
                                    return ['mapValue' => ['fields' => [
                                        'name' => ['stringValue' => $prop['name'] ?? ''],
                                        'value' => ['stringValue' => $prop['value'] ?? '']
                                    ]]];
                                }, $var['properties'] ?? [])]]
                            ]]];
                        }, $variations)]],
                        'updated_at' => ['stringValue' => date('Y-m-d H:i:s')]
                    ]
                ];
                
                firestoreRequest('products', 'PATCH', $productId, $updateData);
                
                $results = [];
                foreach ($variations as $index => $var) {
                    $results[] = [
                        "id" => $index + 1,
                        "sku" => $var['sku'] ?? ''
                    ];
                }
                
                echo json_encode([
                    "message" => "Variations Created",
                    "data" => $results
                ]);
                
            } else {
                // Create new product
                $title = $inputData['title'] ?? '';
                $sku = $inputData['sku'] ?? '';
                $category_ids = $inputData['category_ids'] ?? [];
                
                if (!$title || !$sku) {
                    http_response_code(422);
                    echo json_encode(["message" => "Title and SKU required"]);
                    break;
                }
                
                // Ensure category_ids is an array
                if (empty($category_ids)) {
                    $category_ids = [1]; // Default category
                }
                
                // Prepare images
                $images = $inputData['images'] ?? [];
                $imageValues = [];
                foreach ($images as $img) {
                    $imageValues[] = [
                        'mapValue' => [
                            'fields' => [
                                'src' => ['stringValue' => $img['src'] ?? ''],
                                'is_default' => ['booleanValue' => $img['is_default'] ?? false]
                            ]
                        ]
                    ];
                }
                
                // Prepare tags
                $tagValues = [];
                foreach ($inputData['tags'] ?? [] as $tag) {
                    $tagValues[] = ['stringValue' => $tag];
                }
                
                // Prepare properties
                $propertyValues = [];
                foreach ($inputData['properties'] ?? [] as $prop) {
                    $propValues = [];
                    foreach ($prop['values'] ?? [] as $val) {
                        $propValues[] = ['stringValue' => $val];
                    }
                    $propertyValues[] = [
                        'mapValue' => [
                            'fields' => [
                                'name' => ['stringValue' => $prop['name'] ?? ''],
                                'values' => ['arrayValue' => ['values' => $propValues]]
                            ]
                        ]
                    ];
                }
                
                $data = [
                    'fields' => [
                        'title' => ['stringValue' => $title],
                        'sku' => ['stringValue' => $sku],
                        'description' => ['stringValue' => $inputData['description'] ?? ''],
                        'images' => ['arrayValue' => ['values' => $imageValues]],
                        'category_ids' => ['arrayValue' => ['values' => array_map(function($id) {
                            return ['integerValue' => (int)$id];
                        }, $category_ids)]],
                        'tags' => ['arrayValue' => ['values' => $tagValues]],
                        'properties' => ['arrayValue' => ['values' => $propertyValues]],
                        'created_at' => ['stringValue' => date('Y-m-d H:i:s')],
                        'updated_at' => ['stringValue' => date('Y-m-d H:i:s')]
                    ]
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
                            "tags" => $inputData['tags'] ?? [],
                            "created_at" => date('Y-m-d H:i:s'),
                            "updated_at" => date('Y-m-d H:i:s')
                        ]
                    ]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to create product"]);
                }
            }
            
        } elseif ($method === 'DELETE' && $productId) {
            firestoreRequest('products', 'DELETE', $productId);
            echo json_encode(["message" => "Product Deleted"]);
        }
        break;

    // Orders
    case 'orders':
        $orderId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $response = firestoreRequest('orders', 'GET');
            $orders = parseDocuments($response);
            $formatted = [];
            
            foreach ($orders as $id => $order) {
                // Filter by order number if provided
                if (isset($_GET['order_number']) && ($order['order_number'] ?? '') !== $_GET['order_number']) {
                    continue;
                }
                
                $formatted[] = [
                    "id" => $id,
                    "order_number" => $order['order_number'] ?? '',
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
            
        } elseif ($method === 'PUT' && $orderId) {
            $status = $inputData['status'] ?? '';
            $valid = ['pending', 'processing', 'completed', 'cancelled'];
            
            if (!in_array($status, $valid)) {
                http_response_code(422);
                echo json_encode(["message" => "Invalid status"]);
                break;
            }
            
            $updateData = [
                'fields' => [
                    'status' => ['stringValue' => $status],
                    'updated_at' => ['stringValue' => date('Y-m-d H:i:s')]
                ]
            ];
            
            firestoreRequest('orders', 'PATCH', $orderId, $updateData);
            
            // Get updated order
            $order = getDocument('orders', $orderId);
            
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $order['order_number'] ?? '',
                    "status" => $status,
                    "currency" => $order['currency'] ?? 'BDT',
                    "total" => $order['total'] ?? '0',
                    "payment_method" => $order['payment_method'] ?? 'cod',
                    "shipping_address" => $order['shipping_address'] ?? [],
                    "customer_notes" => $order['customer_notes'] ?? '',
                    "line_items" => $order['line_items'] ?? [],
                    "created_at" => $order['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
            
        } elseif ($method === 'POST' && $orderId && $subAction === 'timelines') {
            $message = $inputData['message'] ?? '';
            
            // Get existing order
            $order = getDocument('orders', $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $timelines = $order['timelines'] ?? [];
            $timelines[] = [
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $updateData = [
                'fields' => [
                    'timelines' => ['arrayValue' => ['values' => array_map(function($t) {
                        return ['mapValue' => ['fields' => [
                            'message' => ['stringValue' => $t['message']],
                            'created_at' => ['stringValue' => $t['created_at']]
                        ]]];
                    }, $timelines)]],
                    'updated_at' => ['stringValue' => date('Y-m-d H:i:s')]
                ]
            ];
            
            firestoreRequest('orders', 'PATCH', $orderId, $updateData);
            
            http_response_code(201);
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $order['order_number'] ?? '',
                    "status" => $order['status'] ?? 'pending',
                    "currency" => $order['currency'] ?? 'BDT',
                    "total" => $order['total'] ?? '0',
                    "payment_method" => $order['payment_method'] ?? 'cod',
                    "shipping_address" => $order['shipping_address'] ?? [],
                    "customer_notes" => $order['customer_notes'] ?? '',
                    "line_items" => $order['line_items'] ?? [],
                    "created_at" => $order['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
