<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Fixed: Category, Variations Name, and Realtime Database Sync
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

// Security Check
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

// ১. প্রোডাক্ট তৈরি (Create Product)
if ($path === 'products' && $method === 'POST') {
    $prodId = time(); 
    $now = date('c');

    // মুভড্রপ থেকে আসা ইমেজ প্রসেসিং
    $images = [];
    if (!empty($inputData['images'])) {
        foreach ($inputData['images'] as $img) {
            $images[] = $img['src'];
        }
    }

    // ক্যাটাগরি ফিক্স: সরাসরি আইডি অ্যারে সেভ করা
    $categories = $inputData['category_ids'] ?? [];

    $productData = [
        "id" => $prodId,
        "title" => (string)$inputData['title'],
        "sku" => (string)$inputData['sku'],
        "description" => (string)($inputData['description'] ?? ''),
        "images" => $images,
        "category_ids" => $categories, // মুভড্রপ ক্যাটাগরি সাপোর্ট
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now,
        "regular_price" => "0",
        "sale_price" => ""
    ];

    // Realtime Database এ সেভ করা (Firestore লজিক বাদ দেওয়া হয়েছে)
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

// ২. ভ্যারিয়েশন তৈরি (সঠিক নাম সহ)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $pid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = [];

    if (!empty($vars)) {
        foreach ($vars as $index => $v) {
            // প্রপার্টি থেকে নাম বের করা (যেমন: Color: Black -> "Black")
            $nameParts = [];
            if (!empty($v['properties'])) {
                foreach ($v['properties'] as $prop) {
                    $nameParts[] = $prop['value'];
                }
            }
            // যদি নাম না থাকে তবে SKU ই নাম
            $vName = !empty($nameParts) ? implode(" ", $nameParts) : $v['sku'];

            $varData = [
                "sku" => $v['sku'],
                "regular_price" => (string)$v['regular_price'],
                "sale_price" => (string)($v['sale_price'] ?? ''),
                "image" => $v['image'],
                "name" => $vName, // এটিই বাটনে দেখাবে
                "stock" => $v['stock_quantity'] ?? 0
            ];

            firebaseRequest("PUT", "/products/$pid/variations/$index.json", $varData);
            
            // প্রথম ভ্যারিয়েশন দিয়ে মেইন প্রাইস আপডেট
            if ($index === 0) {
                firebaseRequest("PATCH", "/products/$pid.json", [
                    "regular_price" => $varData['regular_price'],
                    "sale_price" => $varData['sale_price']
                ]);
            }
            $responseEntries[] = ["id" => (int)($pid . $index), "sku" => $v['sku']];
        }
    }

    http_response_code(201);
    echo json_encode(["message" => "Product Variations Created", "data" => $responseEntries]);
    exit();
}

// ৩. প্রোডাক্ট ডিলিট (Delete)
if (preg_match('/products\/(.+)/', $path, $matches) && $method === 'DELETE') {
    $delId = $matches[1];
    firebaseRequest("DELETE", "/products/$delId.json");
    echo json_encode(["message" => "Product Deleted Successfully"]);
    exit();
}

// Firebase Helper
function firebaseRequest($method, $endpoint, $data = null) {
    $url = DATABASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}
