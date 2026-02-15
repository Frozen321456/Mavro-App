<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(http_response_code(200)); }

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app');

$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit(http_response_code(401));
}

$path = $_GET['path'] ?? '';
$inputData = json_decode(file_get_contents('php://input'), true);

// পন্য তৈরি
if ($path === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = time();
    $images = array_column($inputData['images'] ?? [], 'src');

    $productData = [
        "id" => $prodId,
        "title" => $inputData['title'],
        "sku" => $inputData['sku'],
        "description" => $inputData['description'] ?? '',
        "images" => $images,
        "created_at" => date('c')
    ];

    firebaseRequest("PUT", "/products/$prodId.json", $productData);
    echo json_encode(["message" => "Product Created", "data" => ["id" => $prodId]]);
    exit(http_response_code(201));
}

// ভ্যারিয়েশন তৈরি (সঠিক নাম সহ)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $pid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $response = [];

    foreach ($vars as $i => $v) {
        // প্রপার্টি থেকে নাম তৈরি (যেমন: Color: Black -> "Black")
        $nameParts = [];
        if(!empty($v['properties'])) {
            foreach($v['properties'] as $prop) { $nameParts[] = $prop['value']; }
        }
        $vName = !empty($nameParts) ? implode(" ", $nameParts) : $v['sku'];

        $varData = [
            "sku" => $v['sku'],
            "regular_price" => (string)$v['regular_price'],
            "sale_price" => (string)($v['sale_price'] ?? ''),
            "image" => $v['image'] ?? '',
            "name" => $vName
        ];

        firebaseRequest("PUT", "/products/$pid/variations/$i.json", $varData);
        
        // মেইন প্রোডাক্টের প্রাইস আপডেট
        if($i === 0) {
            firebaseRequest("PATCH", "/products/$pid.json", [
                "regular_price" => $varData['regular_price'],
                "sale_price" => $varData['sale_price']
            ]);
        }
        $response[] = ["id" => $pid.$i, "sku" => $v['sku']];
    }
    echo json_encode(["message" => "Variations Created", "data" => $response]);
    exit(http_code(201));
}

function firebaseRequest($method, $end, $data) {
    $ch = curl_init(DATABASE_URL . $end);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_exec($ch);
    curl_close($ch);
}
