<?php
/**
* Mavro Essence - MoveDrop Final Official Implementation
* Fixed: Variations Name (Color/Size concat) & Category ID Fix
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

// ১. পন্য তৈরি (Category Fix সহ)
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
        "category_ids" => $inputData['category_ids'] ?? [], // সরাসরি অ্যারে সেভ হবে
        "images" => $images,
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now
    ]; 

    firebaseRequest("PUT", "/products/$prodId.json", $productData); 
    http_response_code(201);
    echo json_encode(["message" => "Product Created", "data" => ["id" => $prodId]]);
    exit();
} 

// ২. ভ্যারিয়েশন যোগ করা (Name Concatenation Fix)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $parentPid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = []; 

    if (!empty($vars)) {
        foreach ($vars as $index => $v) {
            // নাম তৈরির লজিক: সব প্রপার্টি (Color, Size) একসাথে করা
            $combinedName = [];
            if (!empty($v['properties'])) {
                foreach ($v['properties'] as $prop) {
                    if (!empty($prop['value'])) { $combinedName[] = $prop['value']; }
                }
            }
            
            // যদি প্রপার্টি না থাকে তবে SKU অথবা "Option"
            $finalName = !empty($combinedName) ? implode(" ", $combinedName) : ($v['sku'] ?? "Option " . ($index + 1));

            $varData = [
                "sku" => $v['sku'],
                "regular_price" => (string)$v['regular_price'],
                "sale_price" => (string)($v['sale_price'] ?? ''),
                "image" => $v['image'],
                "name" => $finalName // এখন আর 982#0 দেখাবে না
            ];
            
            firebaseRequest("PUT", "/products/$parentPid/variations/$index.json", $varData);
            
            // প্রথম ভ্যারিয়েশন দিয়ে মেইন প্রাইস আপডেট
            if($index === 0) {
                firebaseRequest("PATCH", "/products/$parentPid.json", [
                    "regular_price" => (string)$v['regular_price'],
                    "sale_price" => (string)($v['sale_price'] ?? '')
                ]);
            }
            $responseEntries[] = ["id" => (int)($parentPid.$index), "sku" => $v['sku']];
        }
    } 

    http_response_code(201);
    echo json_encode(["message" => "Product Variations Created", "data" => $responseEntries]);
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
    curl_exec($ch);
    curl_close($ch);
}
