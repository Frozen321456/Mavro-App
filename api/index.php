<?php
/**
 * Mavro Essence - MoveDrop Final Integration Fix
 * Realtime Database Success Verification
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// আপনার Realtime Database URL
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app/'); 

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ১. মুভড্রপ প্রোডাক্ট লিস্টিং (The Final Fix) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'p' . time();
    $now = date('c');

    // মুভড্রপ সাধারণত ক্যাটাগরি আইডিগুলো ইন্টিজার হিসেবে পাঠায়
    $catIds = isset($inputData['category_ids']) ? array_map('intval', $inputData['category_ids']) : [0];
    
    // মুভড্রপ যে ডাটা ফরম্যাট চায়
    $payload = [
        "id" => $prodId,
        "title" => $inputData['title'] ?? 'Untitled Product',
        "sku" => $inputData['sku'] ?? 'SKU-' . time(),
        "price" => (string)($inputData['regular_price'] ?? '0'),
        "description" => $inputData['description'] ?? '',
        "image" => isset($inputData['images'][0]['src']) ? $inputData['images'][0]['src'] : '',
        "category_ids" => $catIds,
        "created_at" => $now,
        "updated_at" => $now,
        "channel_association" => [
            "custom" => [
                ["category_ids" => $catIds]
            ]
        ]
    ];

    // ডাটাবেসে সেভ করা
    $saveResult = curl_call("products/$prodId.json", 'PUT', $payload);

    // মুভড্রপকে সাকসেস রেসপন্স পাঠানো (এটিই সবচেয়ে জরুরি)
    if ($saveResult !== false) {
        http_response_code(201); // মুভড্রপ এই কোডটি খোঁজে
        echo json_encode([
            "message" => "Product Created",
            "data" => [
                "id" => $prodId,
                "title" => $payload['title'],
                "sku" => $payload['sku'],
                "category_ids" => $catIds,
                "created_at" => $now,
                "updated_at" => $now
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database Save Failed"]);
    }
    exit();
}

// --- ২. ক্যাটাগরি সেকশন ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = time();
        $data = ["id" => $id, "name" => $inputData['name'] ?? 'New Category'];
        curl_call("categories/$id.json", 'PUT', $data);
        echo json_encode(["status" => "success", "data" => $data]);
    } else {
        $res = curl_call("categories.json", 'GET');
        $list = [];
        if ($res && is_array($res)) {
            foreach ($res as $item) { $list[] = $item; }
        }
        echo json_encode(["data" => $list]);
    }
    exit();
}

// --- CURL ফাংশন ---
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
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) return false;
    return json_decode($res, true);
}
