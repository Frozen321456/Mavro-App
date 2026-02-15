<?php
/**
 * Mavro Essence - Official Realtime Database API
 * Fixed for MoveDrop Listing & Shop Sync
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// আপনার দেওয়া Realtime Database URL
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app/'); 
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ১. হেলথ চেক ---
if ($path === 'health') {
    echo json_encode(["status" => "online", "db" => "Realtime-Database-Ready"]);
    exit();
}

// --- ২. ক্যাটাগরি সেকশন (MoveDrop & Admin Support) ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = time(); // Numeric ID
        $data = [
            "id" => $id,
            "name" => $inputData['name']
        ];
        curl_call("categories/$id.json", 'PUT', $data);
        echo json_encode(["status" => "success", "data" => $data]);
    } else {
        $res = curl_call("categories.json", 'GET');
        $list = [];
        if ($res) {
            foreach ($res as $item) { $list[] = $item; }
        }
        echo json_encode(["data" => $list]);
    }
    exit();
}

// --- ৩. প্রোডাক্ট লিস্টিং (MoveDrop-এর সেই এরর ফিক্স) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = 'p' . time();
    $now = date('c');

    $catIds = isset($inputData['category_ids']) ? array_map('intval', $inputData['category_ids']) : [0];
    
    // মুভড্রপ যেভাবে সরাসরি JSON চায়, হুবহু সেই ফরম্যাট
    $payload = [
        "id" => $prodId,
        "title" => $inputData['title'],
        "sku" => $inputData['sku'],
        "price" => (string)($inputData['regular_price'] ?? '0'),
        "description" => $inputData['description'] ?? '',
        "image" => $inputData['images'][0]['src'] ?? '',
        "images" => $inputData['images'] ?? [],
        "category_ids" => $catIds,
        "tags" => $inputData['tags'] ?? [],
        "properties" => $inputData['properties'] ?? [],
        "created_at" => $now,
        "updated_at" => $now,
        // Association mapping for validation success
        "channel_association" => [
            "custom" => [
                ["category_ids" => $catIds]
            ]
        ]
    ];

    curl_call("products/$prodId.json", 'PUT', $payload);

    // মুভড্রপ সাকসেস রেসপন্স (Strict Success)
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => $payload
    ]);
    exit();
}

// --- ৪. প্রোডাক্ট গেট (Shop/Admin এর জন্য) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $res = curl_call("products.json", 'GET');
    $list = [];
    if ($res) {
        foreach ($res as $item) { $list[] = $item; }
    }
    echo json_encode(["data" => $list]);
    exit();
}

// Helper: CURL Call to Firebase
function curl_call($path, $method, $body = null) {
    $ch = curl_init(DATABASE_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}
