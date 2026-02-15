<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Supports: Regular/Sale Price, Variations with Name, Firestore & RTDB Sync
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

// ১. পন্য তৈরি (Create Product)
if ($path === 'products' && $method === 'POST') {
    $prodId = time(); 
    $now = date('c');

    $images = [];
    if (!empty($inputData['images'])) {
        foreach ($inputData['images'] as $img) {
            $images[] = $img['src'];
        }
    }

    $productData = [
        "id" => $prodId,
        "title" => (string)$inputData['title'],
        "sku" => (string)$inputData['sku'],
        "description" => (string)($inputData['description'] ?? ''),
        "images" => $images,
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now
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

// ২. ভ্যারিয়েশন তৈরি (Create Product Variations) - নাম সহ
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $parentPid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = [];

    if (!empty($vars)) {
        foreach ($vars as $index => $v) {
            // ১. ভ্যারিয়েশনের নাম বের করার লজিক
            $varName = "";
            if (!empty($v['properties'])) {
                // সব প্রপার্টি ভ্যালু যোগ করে একটি নাম তৈরি করা (যেমন: Black, XL)
                $names = array_column($v['properties'], 'value');
                $varName = implode(", ", $names);
            }
            
            // যদি নাম না পাওয়া যায় তবে ডিফল্ট হিসেবে SKU ব্যবহার হবে
            if (empty($varName)) { $varName = $v['sku']; }

            // ২. ডাটাবেসের জন্য ডাটা তৈরি
            $varData = [
                "sku" => $v['sku'],
                "regular_price" => (string)$v['regular_price'],
                "sale_price" => (string)($v['sale_price'] ?? ''),
                "image" => $v['image'],
                "name" => $varName, // এটিই আপনার বাটন টেক্সট হিসেবে কাজ করবে
                "stock" => $v['stock_quantity'] ?? 0
            ];

            // ৩. রিয়েলটাইম ডাটাবেসে সেভ
            firebaseRequest("PUT", "/products/$parentPid/variations/$index.json", $varData);
            
            // ৪. মেইন প্রোডাক্টের প্রাইস আপডেট (প্রথম ভ্যারিয়েশন অনুযায়ী)
            if ($index === 0) {
                firebaseRequest("PATCH", "/products/$parentPid.json", [
                    "regular_price" => (string)$v['regular_price'],
                    "sale_price" => (string)($v['sale_price'] ?? '')
                ]);
            }

            $responseEntries[] = ["id" => time() + $index, "sku" => $v['sku']];
        }
    }

    http_response_code(201);
    echo json_encode([
        "message" => "Product Variations Created",
        "data" => $responseEntries
    ]);
    exit();
}

// সাহায্যকারী ফাংশন
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
