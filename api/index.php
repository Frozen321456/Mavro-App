<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Fixed: Full Variation Name Support (Concatenating Color, Size, etc.)
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
        "regular_price" => (string)($inputData['regular_price'] ?? '0'),
        "sale_price" => (string)($inputData['sale_price'] ?? ''),
        "images" => $images,
        "category_ids" => $inputData['category_ids'] ?? [],
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now
    ];

    $res = firebaseRequest("PUT", "/products/$prodId.json", $productData);

    if ($res['status'] == 200 || $res['status'] == 201) {
        http_response_code(201);
        echo json_encode([
            "message" => "Product Created",
            "data" => [
                "id" => $prodId,
                "title" => $inputData['title'],
                "sku" => $inputData['sku'],
                "tags" => $inputData['tags'] ?? [],
                "created_at" => $now,
                "updated_at" => $now
            ]
        ]);
    }
    exit();
}

// ২. ভ্যারিয়েশন যোগ করা (FIXED: Full Variation Name)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $parentPid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = [];

    if (!empty($vars)) {
        // প্রথম ভ্যারিয়েশনের দামকে মেইন প্রোডাক্টের দাম হিসেবে আপডেট করা
        $firstRegPrice = $vars[0]['regular_price'] ?? "0";
        $firstSalePrice = $vars[0]['sale_price'] ?? "";
        
        firebaseRequest("PATCH", "/products/$parentPid.json", [
            "regular_price" => (string)$firstRegPrice,
            "sale_price" => (string)$firstSalePrice
        ]);

        foreach ($vars as $index => $v) {
            $varId = $parentPid . "00" . $index;
            
            // --- প্রপার্টিজ থেকে পূর্ণাঙ্গ নাম বের করার লজিক ---
            $nameParts = [];
            if (!empty($v['properties'])) {
                foreach ($v['properties'] as $prop) {
                    if (!empty($prop['value'])) {
                        $nameParts[] = $prop['value'];
                    }
                }
            }
            
            // যদি "Black" এবং "S" থাকে তবে নাম হবে "Black S"
            // যদি কিছু না থাকে তবে SKU দেখাবে
            $propName = !empty($nameParts) ? implode(" ", $nameParts) : ($v['sku'] ?? "Option " . ($index + 1));

            $varData = [
                "sku" => $v['sku'],
                "regular_price" => (string)$v['regular_price'],
                "sale_price" => (string)($v['sale_price'] ?? ''),
                "image" => $v['image'],
                "name" => $propName // এখন সঠিক নাম সেভ হবে
            ];
            
            firebaseRequest("PUT", "/products/$parentPid/variations/$index.json", $varData);
            $responseEntries[] = ["id" => (int)$varId, "sku" => $v['sku']];
        }
    }

    http_response_code(201);
    echo json_encode([
        "message" => "Product Variations Created",
        "data" => $responseEntries
    ]);
    exit();
}

// ৩. পন্য ডিলিট করা
if (preg_match('/products\/(.+)/', $path, $matches) && $method === 'DELETE') {
    $delId = $matches[1];
    firebaseRequest("DELETE", "/products/$delId.json");
    
    http_response_code(200);
    echo json_encode(["message" => "Product Deleted Successfully"]);
    exit();
}

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
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($response, true)];
}
