<?php
/**
 * Mavro Essence - MoveDrop Standard API (Realtime Database)
 * Implementation based on Official Postman Collection
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app/');
define('AUTH_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026'); // আপনার API Key

// Authentication Check
$headers = apache_request_headers();
$apiKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

// মুভড্রপ যখন X-API-KEY পাঠাবে তখন এটি চেক করবে
/* if ($apiKey !== AUTH_KEY) {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized"]);
    exit();
} 
*/

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ১. ক্যাটাগরি গেট (Paginated List) ---
if ($path === 'categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = curl_call("categories.json", 'GET');
    $items = [];
    if ($res) {
        foreach ($res as $cat) {
            $items[] = [
                "id" => (int)$cat['id'],
                "name" => $cat['name'],
                "slug" => strtolower(str_replace(' ', '-', $cat['name'])),
                "created_at" => $cat['created_at'] ?? date('c')
            ];
        }
    }
    
    // মুভড্রপ মেটা ডেটা আশা করে
    echo json_encode([
        "data" => $items,
        "meta" => [
            "current_page" => 1,
            "last_page" => 1,
            "per_page" => 100,
            "total" => count($items)
        ]
    ]);
    exit();
}

// --- ২. ক্যাটাগরি স্টোর (Create Category) ---
if ($path === 'categories' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = time();
    $name = $inputData['name'] ?? 'New Category';
    $data = [
        "id" => $id,
        "name" => $name,
        "slug" => strtolower(str_replace(' ', '-', $name)),
        "created_at" => date('c')
    ];
    curl_call("categories/$id.json", 'PUT', $data);
    
    http_response_code(201);
    echo json_encode(["data" => $data]);
    exit();
}

// --- ৩. প্রোডাক্ট স্টোর (Create Product) ---
if ($path === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = time();
    $now = date('c');
    
    $payload = [
        "id" => $id,
        "title" => $inputData['title'],
        "sku" => $inputData['sku'],
        "description" => $inputData['description'] ?? '',
        "images" => $inputData['images'] ?? [],
        "category_ids" => array_map('intval', $inputData['category_ids'] ?? []),
        "tags" => $inputData['tags'] ?? [],
        "properties" => $inputData['properties'] ?? [],
        "created_at" => $now,
        "updated_at" => $now
    ];

    // ডাটাবেসে সেভ
    curl_call("products/$id.json", 'PUT', $payload);

    // মুভড্রপ সাকসেস রেসপন্স (হুবহু ডকুমেন্টেশন অনুযায়ী)
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => [
            "id" => $id,
            "title" => $payload['title'],
            "sku" => $payload['sku'],
            "tags" => $payload['tags'],
            "created_at" => $now,
            "updated_at" => $now
        ]
    ]);
    exit();
}

// --- ৪. প্রোডাক্ট ডিলিট ---
if (preg_match('/products\/(\d+)/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $prodId = $matches[1];
    curl_call("products/$prodId.json", 'DELETE');
    echo json_encode(["message" => "Product Deleted Successfully"]);
    exit();
}

// --- ৫. অর্ডার গেট (Retrieve Orders) ---
if ($path === 'orders' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = curl_call("orders.json", 'GET');
    $items = $res ? array_values($res) : [];
    echo json_encode([
        "data" => $items,
        "meta" => ["total" => count($items)]
    ]);
    exit();
}

// Helper: Firebase CURL
function curl_call($path, $method, $body = null) {
    $url = DATABASE_URL . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
