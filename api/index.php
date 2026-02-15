<?php
/**
 * MoveDrop Custom Channel - Final Fix 2026
 * Website: https://mavro-app.vercel.app/
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
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

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

// 4. Helper: Firebase Request
function firebase($path, $method = 'GET', $data = null) {
    $url = FIREBASE_URL . $path . '.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 5. Helper: Pagination
function paginate($data, $page, $perPage) {
    if (!$data) return ["data" => [], "meta" => ["total" => 0]];
    $items = array_values($data);
    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $pagedData = array_slice($items, $offset, $perPage);
    
    return [
        "data" => $pagedData,
        "meta" => [
            "current_page" => (int)$page,
            "last_page" => ceil($total / $perPage),
            "per_page" => (int)$perPage,
            "total" => $total
        ]
    ];
}

// --- Main logic ---
verifyKey();

$method = $_SERVER['REQUEST_METHOD'];
$inputData = json_decode(file_get_contents('php://input'), true);
$pathInfo = $_GET['path'] ?? '';
$parts = explode('/', trim($pathInfo, '/'));
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;

switch ($resource) {
    case 'categories':
        if ($method === 'GET') {
            $data = firebase('/categories');
            echo json_encode(paginate($data, $_GET['page'] ?? 1, $_GET['per_page'] ?? 20));
        } elseif ($method === 'POST') {
            $catId = uniqid();
            $newCat = [
                'id' => $catId,
                'name' => $inputData['name'] ?? 'Unnamed',
                'slug' => strtolower(str_replace(' ', '-', $inputData['name'] ?? 'unnamed')),
                'created_at' => date('c')
            ];
            firebase('/categories/' . $catId, 'PUT', $newCat);
            echo json_encode(["data" => $newCat]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            $subAction = $parts[2] ?? '';
            
            if ($id && $subAction === 'variations') {
                firebase("/products/$id/variations", 'PATCH', ['variations' => $inputData['variations']]);
                echo json_encode(["message" => "Variations Added", "data" => $inputData['variations']]);
            } else {
                // প্রোডাক্ট লিস্টিং এর সময় ক্যাটাগরি চেক
                $categoryIds = $inputData['category_ids'] ?? [];
                
                // যদি category_ids একদমই না থাকে তবে ডিফল্ট একটা আইডি এসাইন করা (এরর রোধ করতে)
                if (empty($categoryIds)) {
                    $categoryIds = ["default_cat"];
                }

                $productId = uniqid('prod_');
                
                // Firestore compatible structure: 'category' ফিল্ডে প্রথম আইডিটি স্ট্রিং হিসেবে রাখা
                $productData = [
                    'id' => $productId,
                    'title' => $inputData['title'] ?? '',
                    'name' => $inputData['title'] ?? '', // আপনার shop.html 'name' ব্যবহার করে
                    'sku' => $inputData['sku'] ?? '',
                    'description' => $inputData['description'] ?? '',
                    'price' => isset($inputData['regular_price']) ? (float)$inputData['regular_price'] : 0,
                    'image' => isset($inputData['images'][0]['src']) ? $inputData['images'][0]['src'] : '',
                    'images' => $inputData['images'] ?? [],
                    'category' => (string)$categoryIds[0], // Firestore logic
                    'category_ids' => $categoryIds,        // MoveDrop logic
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('c')
                ];

                firebase('/products/' . $productId, 'PUT', $productData);

                http_response_code(201);
                echo json_encode([
                    "message" => "Product Created",
                    "data" => $productData
                ]);
            }
        } elseif ($method === 'DELETE' && $id) {
            firebase("/products/$id", 'DELETE');
            echo json_encode(["message" => "Product Deleted"]);
        }
        break;

    case 'orders':
        if ($method === 'GET') {
            $data = firebase('/orders');
            echo json_encode(paginate($data, $_GET['page'] ?? 1, $_GET['per_page'] ?? 20));
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
