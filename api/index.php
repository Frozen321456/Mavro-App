<?php
/**
 * MoveDrop Custom Channel - Final Corrected Version
 * Fixes: channel_association.custom.0.category_ids.0 error
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

$inputData = json_decode(file_get_contents('php://input'), true);
$pathInfo = $_GET['path'] ?? '';
$parts = explode('/', trim($pathInfo, '/'));
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;

switch ($resource) {
    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $data = firebase('/categories');
            $categories = $data ? array_values($data) : [];
            // MoveDrop expects a list of categories
            echo json_encode(["data" => $categories]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ১. ক্যাটাগরি আইডি ম্যানেজমেন্ট (সবচেয়ে গুরুত্বপূর্ণ অংশ)
            // MoveDrop এর পাঠানো ডাটা থেকে category_ids বের করা
            $category_ids = $inputData['category_ids'] ?? [];
            
            // যদি MoveDrop ডাটা না পাঠায়, তবে একটি ডিফল্ট আইডি সেট করা যাতে এরর না দেয়
            if (empty($category_ids)) {
                $category_ids = ["mavro_essence_general"]; 
            }

            $productId = $id ?? 'prod_' . time();

            // ২. ডাটা স্ট্রাকচার তৈরি
            // এখানে category_ids ফিল্ডটি রাখা হয়েছে MoveDrop কে খুশি করতে
            // আর category (string) রাখা হয়েছে আপনার Firestore এর আগের লজিক ঠিক রাখতে
            $finalProduct = [
                'id' => $productId,
                'name' => $inputData['title'] ?? 'Unnamed Product',
                'title' => $inputData['title'] ?? 'Unnamed Product',
                'sku' => $inputData['sku'] ?? 'SKU-' . time(),
                'price' => (float)($inputData['regular_price'] ?? 0),
                'image' => $inputData['images'][0]['src'] ?? '',
                'images' => $inputData['images'] ?? [],
                'description' => $inputData['description'] ?? '',
                'category' => (string)$category_ids[0], // Firestore এর জন্য প্রথম আইডিটি স্ট্রিং হিসেবে
                'category_ids' => $category_ids,        // MoveDrop এই ফিল্ডটিই খুঁজছে
                'channel_association' => [
                    'custom' => [
                        [
                            'category_ids' => $category_ids
                        ]
                    ]
                ],
                'created_at' => date('c')
            ];

            // ফায়ারবেসে সেভ করা
            firebase('/products/' . $productId, 'PUT', $finalProduct);

            // ৩. রেসপন্স পাঠানো
            http_response_code(201);
            echo json_encode([
                "message" => "Product Created",
                "data" => $finalProduct
            ]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Endpoint not found"]);
        break;
}
