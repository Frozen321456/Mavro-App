<?php
/**
 * MoveDrop Custom Channel - Final Fix for Firestore Compatibility
 * Host: https://mavro-app.vercel.app/
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

// Helper: Firebase Request
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
            echo json_encode(["data" => $items, "meta" => ["total" => count($items)]]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            // ১. ক্যাটাগরি আইডি হ্যান্ডলিং (মেইন ফিক্স)
            $raw_cat_ids = $inputData['category_ids'] ?? [];
            
            // যদি MoveDrop থেকে ক্যাটাগরি না আসে, তবে জোর করে একটি 'default' আইডি বসানো
            if (empty($raw_cat_ids)) {
                $raw_cat_ids = ["mavro_general"];
            }

            // ২. আইডি জেনারেশন (Firestore স্টাইল)
            $productId = $id ?? 'prod_' . bin2hex(random_bytes(4));

            // ৩. ডাটা স্ট্রাকচার (আপনার Firestore ফিল্ড + MoveDrop ফিল্ড)
            $finalProduct = [
                'id' => $productId,
                'name' => $inputData['title'] ?? 'New Product', // shop.html এর জন্য
                'title' => $inputData['title'] ?? 'New Product', // MoveDrop এর জন্য
                'sku' => $inputData['sku'] ?? '',
                'price' => (float)($inputData['regular_price'] ?? 0),
                'image' => $inputData['images'][0]['src'] ?? '', // shop.html এর জন্য
                'description' => $inputData['description'] ?? '',
                'category' => (string)$raw_cat_ids[0], // Firestore এ স্ট্রিং হিসেবে থাকবে
                'category_ids' => $raw_cat_ids,         // MoveDrop এই ফিল্ডটিই খুঁজছে
                'images' => $inputData['images'] ?? [],
                'status' => 'active',
                'created_at' => date('c')
            ];

            // ফায়ারবেসে সেভ করা
            firebase('/products/' . $productId, 'PUT', $finalProduct);

            http_response_code(201);
            echo json_encode([
                "message" => "Product Created",
                "data" => $finalProduct
            ]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "api" => "Mavro Essence v2.1"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
