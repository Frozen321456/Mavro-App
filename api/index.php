<?php
/**
 * MoveDrop Custom Channel - Fixed for Firestore 2026
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
define('FIREBASE_API_KEY', 'AIzaSyAB7dyaJwkadV7asGOhj6TCN5it5pCWg10');
define('FIREBASE_PROJECT_ID', 'espera-mavro-6ddc5');

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

// 4. Helper: Firebase Firestore REST API Request
function firestore($collection, $method = 'GET', $documentId = null, $body = null) {
    $accessToken = getFirebaseAccessToken();
    if (!$accessToken) {
        return null;
    }

    // Build URL for Firestore REST API
    $baseUrl = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents";
    
    if ($documentId) {
        $url = $baseUrl . "/" . $collection . "/" . $documentId;
    } else {
        $url = $baseUrl . "/" . $collection;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ]);

    if ($body) {
        // Convert to Firestore document format
        $firestoreBody = convertToFirestoreFormat($body);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreBody));
    }

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 400) {
        error_log("Firestore error ($httpCode): " . $res);
        return null;
    }

    return json_decode($res, true);
}

// 5. Get Firebase Access Token using Service Account
function getFirebaseAccessToken() {
    // For simplicity, we'll use the API key method
    // In production, use service account JSON
    return FIREBASE_API_KEY;
}

// 6. Convert to Firestore Document Format
function convertToFirestoreFormat($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_string($value)) {
            $fields[$key] = ['stringValue' => $value];
        } elseif (is_numeric($value)) {
            if (is_float($value)) {
                $fields[$key] = ['doubleValue' => $value];
            } else {
                $fields[$key] = ['integerValue' => $value];
            }
        } elseif (is_bool($value)) {
            $fields[$key] = ['booleanValue' => $value];
        } elseif (is_array($value)) {
            if (isset($value[0]) && !is_assoc($value)) {
                // Array
                $arrayValues = [];
                foreach ($value as $item) {
                    $arrayValues[] = convertToFirestoreValue($item);
                }
                $fields[$key] = ['arrayValue' => ['values' => $arrayValues]];
            } else {
                // Object/Map
                $fields[$key] = ['mapValue' => ['fields' => convertToFirestoreFormat($value)]];
            }
        } elseif (is_null($value)) {
            $fields[$key] = ['nullValue' => null];
        }
    }
    return ['fields' => $fields];
}

// 7. Helper to check if array is associative
function is_assoc($array) {
    return array_keys($array) !== range(0, count($array) - 1);
}

// 8. Convert single value to Firestore format
function convertToFirestoreValue($value) {
    if (is_string($value)) {
        return ['stringValue' => $value];
    } elseif (is_numeric($value)) {
        if (is_float($value)) {
            return ['doubleValue' => $value];
        } else {
            return ['integerValue' => $value];
        }
    } elseif (is_bool($value)) {
        return ['booleanValue' => $value];
    } elseif (is_array($value)) {
        if (isset($value[0]) && !is_assoc($value)) {
            $arrayValues = [];
            foreach ($value as $item) {
                $arrayValues[] = convertToFirestoreValue($item);
            }
            return ['arrayValue' => ['values' => $arrayValues]];
        } else {
            return ['mapValue' => ['fields' => convertToFirestoreFormat($value)]];
        }
    } elseif (is_null($value)) {
        return ['nullValue' => null];
    }
    return ['nullValue' => null];
}

// 9. Convert Firestore response to regular array
function convertFromFirestoreFormat($firestoreDoc) {
    if (!isset($firestoreDoc['fields'])) {
        return [];
    }
    
    $result = [];
    foreach ($firestoreDoc['fields'] as $key => $value) {
        $type = array_key_first($value);
        switch ($type) {
            case 'stringValue':
                $result[$key] = $value['stringValue'];
                break;
            case 'integerValue':
                $result[$key] = (int)$value['integerValue'];
                break;
            case 'doubleValue':
                $result[$key] = (float)$value['doubleValue'];
                break;
            case 'booleanValue':
                $result[$key] = (bool)$value['booleanValue'];
                break;
            case 'arrayValue':
                $result[$key] = [];
                if (isset($value['arrayValue']['values'])) {
                    foreach ($value['arrayValue']['values'] as $item) {
                        $itemType = array_key_first($item);
                        $result[$key][] = $item[$itemType] ?? null;
                    }
                }
                break;
            case 'mapValue':
                $result[$key] = convertFromFirestoreFormat(['fields' => $value['mapValue']['fields']]);
                break;
            default:
                $result[$key] = null;
        }
    }
    return $result;
}

// 10. Generate slug from name
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 11. Pagination helper (MoveDrop format)
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

// 12. API Routing Setup
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

// Authentication (except health)
verifyKey();

// Log request for debugging
error_log("MoveDrop API Request: $method $requestPath");

// --- Main Router ---
switch ($pathParts[0]) {
    
    // Webhooks Endpoints
    case 'webhooks':
        if ($method === 'POST') {
            $webhooks = $inputData['webhooks'] ?? [];
            $storedWebhooks = [];
            
            foreach ($webhooks as $webhook) {
                $webhookData = [
                    'name' => $webhook['name'] ?? '',
                    'event' => $webhook['event'] ?? '',
                    'delivery_url' => $webhook['delivery_url'] ?? '',
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                // Add to Firestore
                $result = firestore('webhooks', 'POST', null, $webhookData);
                if ($result) {
                    $storedWebhooks[] = $webhookData;
                }
            }
            
            http_response_code(201);
            echo json_encode([
                "message" => "MoveDrop Webhooks Registered",
                "data" => $storedWebhooks
            ]);
        }
        break;

    // Categories Endpoints
    case 'categories':
        if ($method === 'GET') {
            // Get categories from Firestore
            $result = firestore('categories', 'GET');
            $categories = [];
            
            if ($result && isset($result['documents'])) {
                foreach ($result['documents'] as $doc) {
                    $catData = convertFromFirestoreFormat($doc);
                    $catId = basename($doc['name']);
                    $categories[] = [
                        "id" => $catId,
                        "name" => $catData['name'] ?? '',
                        "slug" => $catData['slug'] ?? generateSlug($catData['name'] ?? ''),
                        "created_at" => $catData['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            $response = paginate($categories, $page, $perPage);
            echo json_encode($response);
            
        } elseif ($method === 'POST') {
            $name = $inputData['name'] ?? '';
            if (empty($name)) {
                http_response_code(422);
                echo json_encode(["message" => "Category name is required"]);
                break;
            }
            
            $categoryData = [
                'name' => $name,
                'slug' => generateSlug($name),
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $result = firestore('categories', 'POST', null, $categoryData);
            
            if ($result && isset($result['name'])) {
                $catId = basename($result['name']);
                http_response_code(201);
                echo json_encode([
                    "data" => [
                        "id" => $catId,
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

    // Products Endpoints
    case 'products':
        $productId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            // Get all products
            $result = firestore('products', 'GET');
            $products = [];
            
            if ($result && isset($result['documents'])) {
                foreach ($result['documents'] as $doc) {
                    $prodData = convertFromFirestoreFormat($doc);
                    $prodId = basename($doc['name']);
                    $products[] = [
                        "id" => $prodId,
                        "title" => $prodData['title'] ?? $prodData['name'] ?? '',
                        "sku" => $prodData['sku'] ?? '',
                        "tags" => $prodData['tags'] ?? [],
                        "created_at" => $prodData['created_at'] ?? date('Y-m-d H:i:s'),
                        "updated_at" => $prodData['updated_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            $response = paginate($products, $page, $perPage);
            echo json_encode($response);
            
        } elseif ($method === 'POST') {
            if ($productId && $subAction === 'variations') {
                // POST /products/:id/variations
                $variations = $inputData['variations'] ?? [];
                
                // Get existing product
                $product = firestore('products', 'GET', $productId);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(["message" => "Product not found"]);
                    break;
                }
                
                $productData = convertFromFirestoreFormat($product);
                $productData['variations'] = $variations;
                $productData['updated_at'] = date('Y-m-d H:i:s');
                
                // Update product with variations
                $updateResult = firestore('products', 'PATCH', $productId, $productData);
                
                $variationResults = [];
                foreach ($variations as $index => $var) {
                    $variationResults[] = [
                        "id" => $index + 1,
                        "sku" => $var['sku']
                    ];
                }
                
                echo json_encode([
                    "message" => "Product Variations Created",
                    "data" => $variationResults
                ]);
                
            } else {
                // POST /products - Create product
                $title = $inputData['title'] ?? '';
                $sku = $inputData['sku'] ?? '';
                
                if (empty($title) || empty($sku)) {
                    http_response_code(422);
                    echo json_encode(["message" => "Title and SKU are required"]);
                    break;
                }
                
                // Check for duplicate SKU (would need to query all products)
                // For simplicity, we'll skip duplicate check here
                
                $productData = [
                    'title' => $title,
                    'sku' => $sku,
                    'description' => $inputData['description'] ?? '',
                    'images' => $inputData['images'] ?? [],
                    'category_ids' => $inputData['category_ids'] ?? [],
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'variations' => [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $result = firestore('products', 'POST', null, $productData);
                
                if ($result && isset($result['name'])) {
                    $prodId = basename($result['name']);
                    http_response_code(201);
                    echo json_encode([
                        "message" => "Product Created",
                        "data" => [
                            "id" => $prodId,
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
            // DELETE /products/:id
            $result = firestore('products', 'DELETE', $productId);
            echo json_encode(["message" => "Product Deleted Successfully"]);
        }
        break;

    // Orders Endpoints
    case 'orders':
        $orderId = $pathParts[1] ?? null;
        $subAction = $pathParts[2] ?? null;

        if ($method === 'GET') {
            $result = firestore('orders', 'GET');
            $orders = [];
            
            if ($result && isset($result['documents'])) {
                foreach ($result['documents'] as $doc) {
                    $orderData = convertFromFirestoreFormat($doc);
                    $orderId = basename($doc['name']);
                    
                    // Filter by order_number if provided
                    if (isset($_GET['order_number']) && !empty($_GET['order_number'])) {
                        if (($orderData['order_number'] ?? '') !== $_GET['order_number']) {
                            continue;
                        }
                    }
                    
                    $orders[] = [
                        "id" => $orderId,
                        "order_number" => $orderData['order_number'] ?? '',
                        "status" => $orderData['status'] ?? 'pending',
                        "currency" => $orderData['currency'] ?? 'BDT',
                        "total" => $orderData['total'] ?? '0',
                        "payment_method" => $orderData['payment_method'] ?? 'cod',
                        "shipping_address" => $orderData['shipping_address'] ?? [],
                        "customer_notes" => $orderData['customer_notes'] ?? '',
                        "line_items" => $orderData['line_items'] ?? [],
                        "created_at" => $orderData['created_at'] ?? date('Y-m-d H:i:s')
                    ];
                }
            }
            
            $response = paginate($orders, $page, $perPage);
            echo json_encode($response);
            
        } elseif ($method === 'PUT' && $orderId) {
            // PUT /orders/:id (Update Status)
            $status = $inputData['status'] ?? '';
            $validStatuses = ['pending', 'processing', 'completed', 'cancelled'];
            
            if (!in_array($status, $validStatuses)) {
                http_response_code(422);
                echo json_encode(["message" => "Invalid status. Must be one of: " . implode(', ', $validStatuses)]);
                break;
            }
            
            // Get existing order
            $order = firestore('orders', 'GET', $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $orderData = convertFromFirestoreFormat($order);
            $orderData['status'] = $status;
            $orderData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update order
            firestore('orders', 'PATCH', $orderId, $orderData);
            
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $orderData['order_number'] ?? '',
                    "status" => $status,
                    "currency" => $orderData['currency'] ?? 'BDT',
                    "total" => $orderData['total'] ?? '0',
                    "payment_method" => $orderData['payment_method'] ?? 'cod',
                    "shipping_address" => $orderData['shipping_address'] ?? [],
                    "customer_notes" => $orderData['customer_notes'] ?? '',
                    "line_items" => $orderData['line_items'] ?? [],
                    "created_at" => $orderData['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
            
        } elseif ($method === 'POST' && $orderId && $subAction === 'timelines') {
            // POST /orders/:id/timelines
            $message = $inputData['message'] ?? '';
            
            // Get existing order
            $order = firestore('orders', 'GET', $orderId);
            if (!$order) {
                http_response_code(404);
                echo json_encode(["message" => "Order not found"]);
                break;
            }
            
            $orderData = convertFromFirestoreFormat($order);
            
            $timeline = [
                'message' => $message,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            if (!isset($orderData['timelines'])) {
                $orderData['timelines'] = [];
            }
            $orderData['timelines'][] = $timeline;
            $orderData['updated_at'] = date('Y-m-d H:i:s');
            
            // Update order with timeline
            firestore('orders', 'PATCH', $orderId, $orderData);
            
            http_response_code(201);
            echo json_encode([
                "data" => [
                    "id" => $orderId,
                    "order_number" => $orderData['order_number'] ?? '',
                    "status" => $orderData['status'] ?? 'pending',
                    "currency" => $orderData['currency'] ?? 'BDT',
                    "total" => $orderData['total'] ?? '0',
                    "payment_method" => $orderData['payment_method'] ?? 'cod',
                    "shipping_address" => $orderData['shipping_address'] ?? [],
                    "customer_notes" => $orderData['customer_notes'] ?? '',
                    "line_items" => $orderData['line_items'] ?? [],
                    "created_at" => $orderData['created_at'] ?? date('Y-m-d H:i:s')
                ]
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
