<?php
/**
* Mavro Essence - MoveDrop Final Official Implementation
* Fixed: Health check, product creation with proper Firebase structure
*/ 

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept"); 

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
} 

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('DATABASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app'); 

// Security check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
} 

$path = $_GET['path'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Health check endpoint
if ($path === 'health' && $method === 'GET') {
    http_response_code(200);
    echo json_encode(["status" => "ok", "message" => "API is running"]);
    exit();
}

// Get all products (for sync)
if ($path === 'products' && $method === 'GET') {
    $response = firebaseRequest("GET", "/products.json");
    $products = [];
    
    if ($response['status'] == 200 && $response['data']) {
        foreach ($response['data'] as $key => $product) {
            // Convert your product structure to MoveDrop format
            $productData = [
                "id" => $key,
                "title" => $product['title'] ?? $product['name'] ?? 'No Title',
                "sku" => $product['sku'] ?? '',
                "description" => $product['description'] ?? '',
                "regular_price" => (string)($product['regular_price'] ?? $product['price'] ?? '0'),
                "sale_price" => (string)($product['sale_price'] ?? ''),
                "images" => [],
                "tags" => [],
                "created_at" => $product['created_at'] ?? date('c'),
                "updated_at" => $product['created_at'] ?? date('c')
            ];
            
            // Handle images
            if (!empty($product['images']) && is_array($product['images'])) {
                foreach ($product['images'] as $img) {
                    $productData['images'][] = ["src" => $img, "position" => 0];
                }
            } elseif (!empty($product['image'])) {
                $productData['images'][] = ["src" => $product['image'], "position" => 0];
            }
            
            // Handle variations
            if (!empty($product['variations'])) {
                $productData['variations'] = [];
                foreach ($product['variations'] as $varKey => $var) {
                    $productData['variations'][] = [
                        "id" => $key . $varKey,
                        "sku" => $var['sku'] ?? '',
                        "regular_price" => (string)($var['regular_price'] ?? '0'),
                        "sale_price" => (string)($var['sale_price'] ?? ''),
                        "image" => $var['image'] ?? '',
                        "properties" => [["name" => "Option", "value" => $var['name'] ?? "Option"]]
                    ];
                }
            }
            
            $products[] = $productData;
        }
    }
    
    echo json_encode($products);
    exit();
}

// ১. পন্য তৈরি (Create Product)
if ($path === 'products' && $method === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    
    // Generate a new product ID
    $prodId = uniqid();
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
        "name" => (string)$inputData['title'], // For compatibility with your admin panel
        "sku" => (string)$inputData['sku'],
        "description" => (string)($inputData['description'] ?? ''),
        "regular_price" => (string)($inputData['regular_price'] ?? '0'),
        "sale_price" => (string)($inputData['sale_price'] ?? ''),
        "price" => (string)($inputData['regular_price'] ?? '0'), // For compatibility
        "images" => $images,
        "image" => !empty($images) ? $images[0] : '',
        "tags" => $inputData['tags'] ?? [],
        "created_at" => $now,
        "updated_at" => $now
    ]; 

    $res = firebaseRequest("PUT", "/products/$prodId.json", $productData); 

    if ($res['status'] == 200) {
        http_response_code(201);
        echo json_encode([
            "message" => "Product Created",
            "data" => [
                "id" => $prodId,
                "title" => $inputData['title'],
                "sku" => $inputData['sku'],
                "regular_price" => (string)($inputData['regular_price'] ?? '0'),
                "sale_price" => (string)($inputData['sale_price'] ?? ''),
                "tags" => $inputData['tags'] ?? [],
                "created_at" => $now,
                "updated_at" => $now
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to create product"]);
    }
    exit();
} 

// ২. ভ্যারিয়েশন যোগ করা (Create Product Variations)
if (preg_match('/products\/(.+)\/variations/', $path, $matches) && $method === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
    $parentPid = $matches[1];
    $vars = $inputData['variations'] ?? [];
    $responseEntries = []; 

    if (!empty($vars)) {
        // First, get the parent product
        $parentRes = firebaseRequest("GET", "/products/$parentPid.json");
        
        if ($parentRes['status'] == 200 && $parentRes['data']) {
            $parentProduct = $parentRes['data'];
            
            // Update parent product with first variation's price
            $firstRegPrice = $vars[0]['regular_price'] ?? "0";
            $firstSalePrice = $vars[0]['sale_price'] ?? "";
            
            firebaseRequest("PATCH", "/products/$parentPid.json", [
                "regular_price" => (string)$firstRegPrice,
                "sale_price" => (string)$firstSalePrice,
                "price" => (string)$firstRegPrice
            ]); 

            // Save variations
            $variations = [];
            foreach ($vars as $index => $v) {
                $propName = "Option " . ($index + 1);
                if (!empty($v['properties'])) {
                    $propName = $v['properties'][0]['value'] ?? $propName;
                } 

                $varData = [
                    "sku" => $v['sku'],
                    "regular_price" => (string)$v['regular_price'],
                    "sale_price" => (string)($v['sale_price'] ?? ''),
                    "image" => $v['image'] ?? '',
                    "name" => $propName
                ];
                
                $variations[$index] = $varData;
                $responseEntries[] = ["id" => (int)($parentPid . $index), "sku" => $v['sku']];
            }
            
            // Save all variations at once
            firebaseRequest("PUT", "/products/$parentPid/variations.json", $variations);
        }
    } 

    http_response_code(201);
    echo json_encode([
        "message" => "Product Variations Created",
        "data" => $responseEntries
    ]);
    exit();
} 

// ৩. পন্য ডিলিট করা (Delete Product)
if (preg_match('/products\/(.+)/', $path, $matches) && $method === 'DELETE') {
    $delId = $matches[1];
    firebaseRequest("DELETE", "/products/$delId.json");
    
    http_response_code(200);
    echo json_encode(["message" => "Product Deleted Successfully"]);
    exit();
} 

// If no route matches
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
exit();

// সাহায্যকারী ফাংশন (Firebase REST API)
function firebaseRequest($method, $endpoint, $data = null) {
    $url = DATABASE_URL . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Add this for local testing
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_error($ch)) {
        error_log("CURL Error: " . curl_error($ch));
    }
    
    curl_close($ch);
    return ['status' => $status, 'data' => json_decode($response, true)];
}
