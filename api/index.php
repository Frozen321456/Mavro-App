<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");
header("Content-Type: application/json");

// API Configuration
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');

// Request Handling
$method = $_SERVER['REQUEST_METHOD'];
$path = isset($_GET['path']) ? $_GET['path'] : '';
$headers = getallheaders();

// API Key Validation
if (!isset($headers['X-API-KEY']) || $headers['X-API-KEY'] !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized Access"]);
    exit;
}

// Health Check
if ($path == 'health') {
    echo json_encode(["status" => "online", "message" => "MoveDrop API is running"]);
    exit;
}

// Product Creation logic
if ($path == 'products' && $method == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    // --- CATEGORY ID FIX LOGIC ---
    // এপিআই যদি ক্যাটাগরি আইডি না পায় বা ভুল পায় তবে এটি ১ সেট করে দিবে
    $category_ids = [1]; 
    if (isset($input['category_ids']) && is_array($input['category_ids']) && !empty($input['category_ids'])) {
        $category_ids = array_map('intval', $input['category_ids']); // স্ট্রিং থাকলেও নাম্বার বানিয়ে দিবে
    }

    // আপনার ডাটাবেস বা অন্য সিস্টেমে পাঠানোর আগে ডাটা ক্লিন করা
    $product_data = [
        "title" => $input['title'] ?? 'Untitled',
        "sku" => $input['sku'] ?? 'NO-SKU',
        "description" => $input['description'] ?? '',
        "price" => $input['price'] ?? 0,
        "images" => $input['images'] ?? [],
        "category_ids" => $category_ids // এখন এটি ১০০% ফিক্সড
    ];

    // এখানে আপনার ডাটাবেস সেভ করার কোড বসবে
    // উদাহরণস্বরূপ আমরা সাকসেস মেসেজ দিচ্ছি:
    http_response_code(201);
    echo json_encode([
        "status" => "success",
        "message" => "Product created successfully",
        "data" => [
            "id" => rand(1000, 9999), // Mock ID
            "sync_status" => "active"
        ]
    ]);
    exit;
}

// Category List Fetch
if ($path == 'categories' && $method == 'GET') {
    // ডিফল্ট কিছু ক্যাটাগরি রিটার্ন করছে
    echo json_encode([
        "status" => "success",
        "data" => [
            ["id" => 1, "name" => "Uncategorized"],
            ["id" => 2, "name" => "Perfumes"],
            ["id" => 3, "name" => "Attar"]
        ]
    ]);
    exit;
}

http_response_code(404);
echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
?>
