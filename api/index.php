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
        // Format data for Firestore
        $firestoreData = formatForFirestore($data);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Log for debugging
    error_log("Firestore $method $collection - HTTP $httpCode");
    
    curl_close($ch);
    
    if ($httpCode >= 400) {
        return [
            'error' => true,
            'code' => $httpCode,
            'message' => $response
        ];
    }
    
    return json_decode($response, true);
}

// 5. Format data for Firestore
function formatForFirestore($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_int($value)) {
            $fields[$key] = ['integerValue' => $value];
        } elseif (is_float($value)) {
            $fields[$key] = ['doubleValue' => $value];
        } elseif (is_bool($value)) {
            $fields[$key] = ['booleanValue' => $value];
        } elseif (is_array($value)) {
            // Check if it's a simple array or associative
            if (array_keys($value) !== range(0, count($value) - 1)) {
                // Associative array (object)
                $fields[$key] = ['mapValue' => ['fields' => formatForFirestore($value)]];
            } else {
                // Simple array
                $arrayValues = [];
                foreach ($value as $item) {
                    if (is_string($item)) {
                        $arrayValues[] = ['stringValue' => $item];
                    } elseif (is_int($item)) {
                        $arrayValues[] = ['integerValue' => $item];
                    } elseif (is_float($item)) {
                        $arrayValues[] = ['doubleValue' => $item];
                    } elseif (is_bool($item)) {
                        $arrayValues[] = ['booleanValue' => $item];
                    } elseif (is_array($item)) {
                        $arrayValues[] = ['mapValue' => ['fields' => formatForFirestore($item)]];
                    }
                }
                $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
            }
        } elseif (is_null($value)) {
            $fields[$key] = ['nullValue' => null];
        }
    }
    
    return ['fields' => $fields];
}

// 6. Parse Firestore response to regular array
function parseFirestoreResponse($response) {
    if (!$response || !isset($response['fields'])) {
        return [];
    }
    
    $result = [];
    foreach ($response['fields'] as $key => $value) {
        if (isset($value['stringValue'])) {
            $result[$key] = $value['stringValue'];
        } elseif (isset($value['integerValue'])) {
            $result[$key] = (int)$value['integerValue'];
        } elseif (isset($value['doubleValue'])) {
            $result[$key] = (float)$value['doubleValue'];
        } elseif (isset($value['booleanValue'])) {
            $result[$key] = (bool)$value['booleanValue'];
        } elseif (isset($value['arrayValue'])) {
            $result[$key] = [];
            if (isset($value['arrayValue']['values'])) {
                foreach ($value['arrayValue']['values'] as $item) {
                    if (isset($item['stringValue'])) {
                        $result[$key][] = $item['stringValue'];
                    } elseif (isset($item['integerValue'])) {
                        $result[$key][] = (int)$item['integerValue'];
                    } elseif (isset($item['doubleValue'])) {
                        $result[$key][] = (float)$item['doubleValue'];
                    } elseif (isset($item['booleanValue'])) {
                        $result[$key][] = (bool)$item['booleanValue'];
                    } elseif (isset($item['mapValue'])) {
                        $result[$key][] = parseFirestoreResponse(['fields' => $item['mapValue']['fields']]);
                    }
                }
            }
        } elseif (isset($value['mapValue'])) {
            $result[$key] = parseFirestoreResponse(['fields' => $value['mapValue']['fields']]);
        }
    }
    
    return $result;
}

// 7. Get all documents from a collection
function getCollection($collection) {
    $response = firestoreRequest($collection, 'GET');
    
    $items = [];
    if ($response && !isset($response['error']) && isset($response['documents'])) {
        foreach ($response['documents'] as $doc) {
            $data = parseFirestoreResponse($doc);
            $id = basename($doc['name']);
            $items[$id] = $data;
        }
    }
    
    return $items;
}

// 8. Generate slug
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 9. Pagination
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

// 10. API Routing
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
                    'name' => $webhook['name'] ?? '',
                    'event' => $webhook['event'] ?? '',
                    'delivery_url' => $webhook['delivery_url'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $response = firestoreRequest('webhooks', 'POST', null, $data);
                if ($response && !isset($response['error'])) {
                    $results[] = $data;
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
            $categories = getCollection('categories');
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
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s')
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
            $products = getCollection('products');
            $formatted = [];
            
            foreach ($products as $id => $prod) {
                $formatted[] = [
                    "id" => $id,
                    "title" => $prod['title'] ?? $prod['name'] ?? '',
                    "sku" => $prod['sku'] ?? '',
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
                $response = firestoreRequest('products', 'GET', $productId);
                if (!$response || isset($response['error'])) {
                    http_response_code(404);
                    echo json_encode(["message" => "Product not found"]);
                    break;
                }
                
                $product = parseFirestoreResponse($response);
                $product['variations'] = $variations;
                $product['updated_at'] = date('Y-m-d H:i:s');
                
                // Update product
                firestoreRequest('products', 'PATCH', $productId, $product);
                
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
                
                if (!$title || !$sku) {
                    http_response_code(422);
                    echo json_encode(["message" => "Title and SKU required"]);
                    break;
                }
                
                $data = [
                    'title' => $title,
                    'sku' => $sku,
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $inputData['category_ids'] ?? [],
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
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
            $orders = getCollection('orders');
            $formatted = [];
            
            foreach ($orders as $id => $order) {
                // Filter by order number if provided
                if (isset($_GET['order_number']) && $order['order_number'] !== $_GET['order_number']) {
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
            
            // Get order
            $response = firestoreRequest('orders', 'GET', $orderId);
            if (!$response || isset($response['error'])) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $order = parseFirestoreResponse($response);
            $order['status'] = $status;
            $order['updated_at'] = date('Y-m-d H:i:s');
            
            // Update order
            firestoreRequest('orders', 'PATCH', $orderId, $order);
            
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
            
            // Get order
            $response = firestoreRequest('orders', 'GET', $orderId);
            if (!$response || isset($response['error'])) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $order = parseFirestoreResponse($response);
            
            $timeline = [
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (!isset($order['timelines'])) {
                $order['timelines'] = [];
            }
            $order['timelines'][] = $timeline;
            $order['updated_at'] = date('Y-m-d H:i:s');
            
            // Update order
            firestoreRequest('orders', 'PATCH', $orderId, $order);
            
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
