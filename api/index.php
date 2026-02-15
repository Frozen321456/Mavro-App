<?php
/**
 * Mavro Essence - MoveDrop Fixed Implementation for Vercel
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// ১. Security Check (X-API-KEY)
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// ২. Path Handling (Vercel Fix)
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);
$path = trim($path, '/');
// যদি path এ index.php থাকে তবে তা বাদ দেওয়া
$path = str_replace('index.php', '', $path);
$path = trim($path, '/');

$inputData = json_decode(file_get_contents('php://input'), true);

// ৩. Routes Handling
if ($path === 'categories' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get Categories logic
    $ch = curl_init(FIRESTORE_URL . "/categories");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $res = curl_exec($ch);
    echo $res; // সরাসরি ফায়ারস্টোর ডাটা মুভড্রপ ফরমেটে পাঠাবে
    exit();
}

if ($path === 'products' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // Product creation logic (আপনার আগের লজিক এখানে থাকবে)
    $prodId = time(); // Unique ID
    $now = date('c');
    
    // Firestore Data Structure
    $firestoreData = [
        "fields" => [
            "title" => ["stringValue" => $inputData['title'] ?? ''],
            "sku" => ["stringValue" => $inputData['sku'] ?? ''],
            "description" => ["stringValue" => $inputData['description'] ?? ''],
            "created_at" => ["stringValue" => $now]
        ]
    ];

    $ch = curl_init(FIRESTORE_URL . "/products?documentId=" . $prodId);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    
    http_response_code(201);
    echo json_encode([
        "message" => "Product Created",
        "data" => ["id" => $prodId, "title" => $inputData['title'], "sku" => $inputData['sku']]
    ]);
    exit();
}

// Default Response
http_response_code(404);
echo json_encode(["message" => "Endpoint not found", "path" => $path]);
