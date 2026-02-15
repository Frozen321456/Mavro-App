<?php
/**
 * MoveDrop Custom Channel - Simplified Working Version
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

// 4. Simple function to get data from Firestore
function getFirestoreData($collection) {
    $url = "https://firestore.googleapis.com/v1/projects/" . FIREBASE_PROJECT_ID . "/databases/(default)/documents/" . $collection . "?key=" . FIREBASE_API_KEY;
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        return [];
    }
    
    return json_decode($response, true);
}

// 5. Simple function to parse Firestore documents
function parseFirestoreDocuments($response) {
    $results = [];
    
    if (!isset($response['documents'])) {
        return $results;
    }
    
    foreach ($response['documents'] as $doc) {
        $id = basename($doc['name']);
        $data = ['id' => $id];
        
        if (isset($doc['fields'])) {
            foreach ($doc['fields'] as $key => $value) {
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
                }
            }
        }
        
        $results[] = $data;
    }
    
    return $results;
}

// 6. Generate slug
function generateSlug($name) {
    $slug = strtolower(trim($name));
    $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
    $slug = preg_replace('/-+/', '-', $slug);
    return trim($slug, '-');
}

// 7. API Routing
$requestPath = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pathParts = explode('/', rtrim($requestPath, '/'));

// Health Check
if ($requestPath === 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is ready"]);
    exit();
}

// Authentication for all other endpoints
verifyKey();

// Log request
error_log("MoveDrop API Request: $method $requestPath");

// Simple router
switch ($pathParts[0]) {
    
    // Categories
    case 'categories':
        if ($method === 'GET') {
            $response = getFirestoreData('categories');
            $docs = parseFirestoreDocuments($response);
            
            $categories = [];
            foreach ($docs as $doc) {
                $categories[] = [
                    "id" => $doc['id'],
                    "name" => $doc['name'] ?? 'Unnamed',
                    "slug" => generateSlug($doc['name'] ?? ''),
                    "created_at" => $doc['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode([
                "data" => $categories,
                "meta" => [
                    "current_page" => 1,
                    "from" => 1,
                    "last_page" => 1,
                    "per_page" => count($categories),
                    "to" => count($categories),
                    "total" => count($categories)
                ]
            ]);
        }
        break;

    // Products
    case 'products':
        if ($method === 'GET') {
            $response = getFirestoreData('products');
            $docs = parseFirestoreDocuments($response);
            
            $products = [];
            foreach ($docs as $doc) {
                // Skip dummy documents
                if (isset($doc['_dummy'])) continue;
                
                $products[] = [
                    "id" => $doc['id'],
                    "title" => $doc['title'] ?? $doc['name'] ?? 'Untitled',
                    "sku" => $doc['sku'] ?? 'SKU-' . substr($doc['id'], 0, 8),
                    "tags" => isset($doc['tags']) ? explode(',', $doc['tags']) : [],
                    "created_at" => $doc['created_at'] ?? date('Y-m-d H:i:s'),
                    "updated_at" => $doc['updated_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode([
                "data" => $products,
                "meta" => [
                    "current_page" => 1,
                    "from" => 1,
                    "last_page" => 1,
                    "per_page" => count($products),
                    "to" => count($products),
                    "total" => count($products)
                ]
            ]);
        }
        break;

    // Orders
    case 'orders':
        if ($method === 'GET') {
            $response = getFirestoreData('orders');
            $docs = parseFirestoreDocuments($response);
            
            $orders = [];
            foreach ($docs as $doc) {
                if (isset($doc['_dummy'])) continue;
                
                $orders[] = [
                    "id" => $doc['id'],
                    "order_number" => $doc['order_number'] ?? $doc['id'],
                    "status" => $doc['status'] ?? 'pending',
                    "currency" => $doc['currency'] ?? 'BDT',
                    "total" => $doc['total'] ?? '0',
                    "payment_method" => $doc['payment_method'] ?? 'cod',
                    "shipping_address" => [],
                    "customer_notes" => "",
                    "line_items" => [],
                    "created_at" => $doc['created_at'] ?? date('Y-m-d H:i:s')
                ];
            }
            
            echo json_encode([
                "data" => $orders,
                "meta" => [
                    "current_page" => 1,
                    "from" => 1,
                    "last_page" => 1,
                    "per_page" => count($orders),
                    "to" => count($orders),
                    "total" => count($orders)
                ]
            ]);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
