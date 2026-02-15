<?php
/**
 * Mavro Essence - Category & Product Final Fix
 * API Key: MAVRO-ESSENCE-SECURE-KEY-2026
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// Firebase Helper Function
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

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

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
            $items = $data ? array_values($data) : [];
            echo json_encode(["data" => $items]);
        } 
        elseif ($method === 'POST') {
            // ক্যাটাগরি আইডি জেনারেশন
            $catId = 'cat_' . bin2hex(random_bytes(3));
            $newCat = [
                'id' => $catId,
                'name' => $inputData['name'] ?? 'New Category',
                'slug' => strtolower(str_replace(' ', '-', $inputData['name'] ?? 'new-cat')),
                'created_at' => date('c')
            ];
            // এখানে PUT ব্যবহার করছি যাতে সরাসরি catId দিয়ে ডাটাবেসে সেভ হয়
            firebase('/categories/' . $catId, 'PUT', $newCat);
            echo json_encode(["data" => $newCat]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            // MoveDrop এর category_ids হ্যান্ডলিং
            $categoryIds = $inputData['category_ids'] ?? [];
            if (empty($categoryIds)) { $categoryIds = ["uncategorized"]; }

            $productId = $id ?? 'prod_' . time();
            
            // শপ পেজ এবং MoveDrop—উভয়কে খুশি করার জন্য ডাটা স্ট্রাকচার
            $productData = [
                'id' => $productId,
                'name' => $inputData['title'] ?? 'Unnamed Product',
                'title' => $inputData['title'] ?? 'Unnamed Product',
                'sku' => $inputData['sku'] ?? 'SKU-'.time(),
                'price' => (float)($inputData['regular_price'] ?? 0),
                'image' => $inputData['images'][0]['src'] ?? '',
                'images' => $inputData['images'] ?? [],
                'description' => $inputData['description'] ?? '',
                'category' => (string)$categoryIds[0], // শপ পেজের জন্য
                'category_ids' => $categoryIds,        // MoveDrop এর জন্য
                'channel_association' => [
                    'custom' => [
                        [ 'category_ids' => $categoryIds ]
                    ]
                ],
                'created_at' => date('c')
            ];

            firebase('/products/' . $productId, 'PUT', $productData);
            http_response_code(201);
            echo json_encode(["message" => "Product Created", "data" => $productData]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
