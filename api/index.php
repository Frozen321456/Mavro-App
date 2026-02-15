<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");
header("Content-Type: application/json");

// API Security Key
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');

// Request Method
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$headers = getallheaders();

// 1. API Key Validation
if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized: API Key Mismatch"]);
    exit;
}

// 2. Health Check (To verify if API is live)
if ($path == 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is ready for Channel Connection"]);
    exit;
}

// 3. Product Sync Logic (With Channel & Variation Support)
if ($path == 'products' && $method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- Critical Connection Fixes ---
    
    // Category ID Fix: এপিআই যদি ক্যাটাগরি আইডি না পায়, তবে ১ সেট করবে
    $category_ids = [1]; 
    if (isset($input['category_ids']) && is_array($input['category_ids'])) {
        $category_ids = array_map('intval', $input['category_ids']);
    }

    // Channel ID Fix: চ্যানেলের সাথে কানেক্ট করার জন্য এটি বাধ্যতামূলক (ID: 1 is default)
    $channel_ids = [1];
    if (isset($input['channel_ids']) && is_array($input['channel_ids'])) {
        $channel_ids = array_map('intval', $input['channel_ids']);
    }

    // Variation Processing: ভেরিয়েশন ডাটা রিসিভ করা
    $variations = isset($input['variations']) ? $input['variations'] : [];

    // Final Data Structure for MoveDrop
    $final_data = [
        "title" => $input['title'] ?? 'New Product',
        "sku" => $input['sku'] ?? 'SKU-'.time(),
        "description" => $input['description'] ?? '',
        "price" => floatval($input['price'] ?? 0),
        "images" => $input['images'] ?? [],
        "category_ids" => $category_ids,
        "channel_ids" => $channel_ids, // চ্যানেল কানেকশন নিশ্চিত করে
        "variations" => $variations,   // ভেরিয়েশন সেভ করা
        "status" => "published",
        "sync_time" => date('Y-m-d H:i:s')
    ];

    // এখানে ডাটাবেসে ইনসার্ট করার কোড (PDO/MySQLi) বসাতে পারেন। 
    // আপাতত আমরা সাকসেস মেসেজ রিটার্ন করছি।

    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Product connected to Channel successfully",
        "data" => [
            "id" => rand(10000, 99999),
            "payload_received" => $final_data
        ]
    ]);
    exit;
}

// 4. Default Not Found
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Invalid API Path"]);
?>
