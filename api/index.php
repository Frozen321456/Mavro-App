<?php
/**
 * Mavro Essence - MoveDrop Implementation (Firebase Realtime Database)
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// কনফিগারেশন
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('RTDB_URL', "https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app");

// ১. অথেন্টিকেশন চেক
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// ২. পাথ হ্যান্ডেলিং (Vercel Fix)
$requestUri = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($requestUri, PHP_URL_PATH), '/');
$path = str_replace(['index.php', 'api/'], '', $path);
$path = trim($path, '/');

$inputData = json_decode(file_get_contents('php://input'), true);

// ৩. ক্যাটাগরি অ্যাড করা (POST /categories)
if ($path === 'categories' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $catId = time();
    $catData = [
        "id" => $catId,
        "name" => $inputData['name'] ?? 'Uncategorized',
        "slug" => strtolower(str_replace(' ', '-', $inputData['name'] ?? ''))
    ];
    
    // RTDB-তে ডাটা পুশ (PUT ব্যবহার করা হয়েছে নির্দিষ্ট ID-র জন্য)
    $ch = curl_init(RTDB_URL . "/categories/$catId.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($catData));
    curl_exec($ch);
    
    http_response_code(201);
    echo json_encode(["data" => $catData]);
    exit();
}

// ৪. প্রডাক্ট অ্যাড করা (POST /products)
if ($path === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $prodId = time();
    
    // ইমেজ প্রসেসিং
    $imgUrl = "https://via.placeholder.com/400x500?text=No+Image";
    if (!empty($inputData['images'])) {
        foreach ($inputData['images'] as $img) {
            if (isset($img['is_default']) && $img['is_default']) {
                $imgUrl = $img['src'];
                break;
            }
        }
        if ($imgUrl === "https://via.placeholder.com/400x500?text=No+Image") {
            $imgUrl = $inputData['images'][0]['src'];
        }
    }

    // প্রডাক্ট ডাটা স্ট্রাকচার (Realtime DB-র জন্য সহজ ফরম্যাট)
    $productData = [
        "id" => $prodId,
        "name" => $inputData['title'] ?? '',
        "sku" => $inputData['sku'] ?? '',
        "description" => $inputData['description'] ?? '',
        "image" => $imgUrl,
        "price" => 0.0, // ডিফল্ট জিরো, আপনি চাইলে মুভড্রপের অন্য ফিল্ড থেকে নিতে পারেন
        "tags" => $inputData['tags'] ?? [],
        "categories" => $inputData['category_ids'] ?? [],
        "created_at" => date('c')
    ];

    // RTDB-তে প্রডাক্ট সেভ করা
    $ch = curl_init(RTDB_URL . "/products/$prodId.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($productData));
    curl_exec($ch);

    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => ["id" => $prodId, "title" => $inputData['title'], "sku" => $inputData['sku']]
    ]);
    exit();
}

// ৫. ক্যাটাগরি লিস্ট দেখা (GET /categories)
if ($path === 'categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $ch = curl_init(RTDB_URL . "/categories.json");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = json_decode(curl_exec($ch), true);
    
    $output = [];
    if ($res) {
        foreach ($res as $key => $val) {
            $output[] = [
                "id" => $key,
                "name" => $val['name'] ?? ''
            ];
        }
    }
    echo json_encode(["data" => $output]);
    exit();
}

http_response_code(404);
echo json_encode(["message" => "Endpoint not found", "path" => $path]);
