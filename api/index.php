<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Supports: Category Addition, Regular/Sale Price, Variations, and Deletion
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app');

// সিকিউরিটি চেক
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$inputData = json_decode(file_get_contents('php://input'), true);

// ১. পন্য তৈরি (ক্যাটাগরি ফিক্স সহ)
if ($path === 'products' && $method === 'POST') {
    $prodId = time();
    $now = date('c');

    // ইমেজ প্রসেসিং
    $images = [];
    if (!empty($inputData['images'])) {
        foreach ($inputData['images'] as $img) {
            $images[] = $img['src'];
        }
    }

    // ক্যাটাগরি প্রসেসিং (মুভড্রপ থেকে আসা category_ids)
    $categoryList = $inputData['category_ids'] ?? [];

    $productData = [
        "id" => $prodId,
        "title" => (string)$inputData['title'],
        "sku" => (string)$inputData['sku'],
        "description" => (string)($inputData['description'] ?? ''),
        "images" => $images,
        "category_ids" => $categoryList, // ক্যাটাগরি আইডি এখানে সেভ হবে
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now,
        "regular_price" => "0", // ডিফল্ট
        "sale_price" => ""
    ];

    firebaseRequest("PUT", "/products/$prodId.json", $productData);

    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => [
            "id" => $prodId,
            "title" => $inputData['title'],
            "sku" => $inputData['sku'],
            "created_at" => $now
        ]
    ]);
    exit();
}

// ২. ভ্যারিয়েশন তৈরি (সঠিক নাম এবং প্রাইস সহ)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $parentPid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = [];

    foreach ($vars as $index => $v) {
        // প্রপার্টিজ থেকে নাম বের করা (যেমন: Color/Size)
        $nameParts = [];
        if (!empty($v['properties'])) {
            foreach ($v['properties'] as $p) {
                $nameParts[] = $p['value'];
            }
        }
        $varName = !empty($nameParts) ? implode(" ", $nameParts) : $v['sku'];

        $varData = [
            "sku" => $v['sku'],
            "regular_price" => (string)$v['regular_price'],
            "sale_price" => (string)($v['sale_price'] ?? ''),
            "image" => $v['image'],
            "name" => $varName,
            "stock" => $v['stock_quantity'] ?? 0
        ];

        firebaseRequest("PUT", "/products/$parentPid/variations/$index.json", $varData);
        
        // মেইন প্রোডাক্টের প্রাইস আপডেট
        if ($index === 0) {
            firebaseRequest("PATCH", "/products/$parentPid.json", [
                "regular_price" => $varData['regular_price'],
                "sale_price" => $varData['sale_price']
            ]);
        }
        $responseEntries[] = ["id" => $parentPid.$index, "sku" => $v['sku']];
    }

    http_response_code(201);
    echo json_encode(["message" => "Product Variations Created", "data" => $responseEntries]);
    exit();
}

// ৩. ডিলিট পন্য
if (preg_match('/products\/(.+)/', $path, $matches) && $method === 'DELETE') {
    $delId = $matches[1];
    firebaseRequest("DELETE", "/products/$delId.json");
    echo json_encode(["message" => "Product Deleted Successfully"]);
    exit();
}

// Firebase REST Function
function firebaseRequest($method, $endpoint, $data = null) {
    $url = DATABASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    curl_exec($ch);
    curl_close($ch);
}
