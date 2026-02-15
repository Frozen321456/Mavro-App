<?php
/**
 * Mavro Essence - THE FINAL DEBUG VERSION
 * Realtime Database API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// আপনার সঠিক URL
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app/');
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// --- ৩. প্রোডাক্ট লিস্টিং (MoveDrop-এর জন্য ১০০% ফিক্স) ---
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'p' . time();
    $now = date('c');

    // মুভড্রপ থেকে আসা ডাটা প্রসেসিং
    $catIds = isset($inputData['category_ids']) ? array_map('intval', $inputData['category_ids']) : [1];
    
    $payload = [
        "id" => $prodId,
        "title" => $inputData['title'] ?? 'No Title',
        "sku" => $inputData['sku'] ?? 'No SKU',
        "price" => (string)($inputData['regular_price'] ?? '0'),
        "description" => $inputData['description'] ?? '',
        "image" => $inputData['images'][0]['src'] ?? '',
        "category_ids" => $catIds,
        "created_at" => $now,
        "channel_association" => [
            "custom" => [
                ["category_ids" => $catIds]
            ]
        ]
    ];

    // ডাটাবেসে সেভ করার চেষ্টা
    $res = curl_call("products/$prodId.json", 'PUT', $payload);

    if ($res) {
        http_response_code(201);
        echo json_encode([
            "message" => "Product Created",
            "data" => $payload
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            "status" => "error",
            "message" => "Database Connection Failed. Check Rules or URL."
        ]);
    }
    exit();
}

// --- ক্যাটাগরি ফিক্স ---
if (strpos($path, 'categories') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = time();
        $data = ["id" => $id, "name" => $inputData['name']];
        curl_call("categories/$id.json", 'PUT', $data);
        echo json_encode(["status" => "success", "data" => $data]);
    } else {
        $res = curl_call("categories.json", 'GET') ?? [];
        echo json_encode(["data" => array_values((array)$res)]);
    }
    exit();
}

function curl_call($path, $method, $body = null) {
    $ch = curl_init(DATABASE_URL . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // SSL সমস্যা এড়াতে
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    if(curl_errno($ch)) { return false; }
    curl_close($ch);
    return json_decode($res, true);
}
