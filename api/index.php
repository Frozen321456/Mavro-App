<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation
 * Supports: Title, SKU, Description, Images, Categories, Tags, Properties
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';

// Only handle products creation for now (you can add more paths later)
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');

    // ১. Category Mapping with Default Fallback
    $catValues = [];
    $providedCategories = $inputData['category_ids'] ?? [];

    foreach ($providedCategories as $id) {
        $cleanId = trim((string)$id);
        if (!empty($cleanId)) {
            $catValues[] = ["stringValue" => $cleanId];
        }
    }

    // যদি কোনো ক্যাটাগরি না থাকে তাহলে default ক্যাটাগরি ব্যবহার করা হবে
    if (empty($catValues)) {
        $defaultCategoryId = "1"; // ← এখানে আপনার Firestore-এর বৈধ category ID দিন !!!
        // উদাহরণ: "uncategorized", "default", "1" ইত্যাদি
        $catValues = [["stringValue" => $defaultCategoryId]];
    }

    // ২. Tags Mapping
    $tagValues = [];
    foreach (($inputData['tags'] ?? []) as $tag) {
        $cleanTag = trim((string)$tag);
        if (!empty($cleanTag)) {
            $tagValues[] = ["stringValue" => $cleanTag];
        }
    }

    // ৩. Properties Mapping (Color, Size ইত্যাদি)
    $propertyValues = [];
    foreach (($inputData['properties'] ?? []) as $prop) {
        if (empty($prop['name']) || empty($prop['values'])) continue;

        $pVals = [];
        foreach ($prop['values'] as $v) {
            $cleanVal = trim((string)$v);
            if (!empty($cleanVal)) {
                $pVals[] = ["stringValue" => $cleanVal];
            }
        }

        if (!empty($pVals)) {
            $propertyValues[] = ["mapValue" => [
                "fields" => [
                    "name"  => ["stringValue" => (string)$prop['name']],
                    "values" => ["arrayValue" => ["values" => $pVals]]
                ]
            ]];
        }
    }

    // ৪. Firestore Data Structure
    $firestoreData = [
        "fields" => [
            "id"              => ["stringValue" => $prodId],
            "title"           => ["stringValue" => (string)($inputData['title'] ?? 'Untitled Product')],
            "sku"             => ["stringValue" => (string)($inputData['sku'] ?? '')],
            "description"     => ["stringValue" => (string)($inputData['description'] ?? '')],
            "price"           => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
            "image"           => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
            "category_ids"    => ["arrayValue" => ["values" => $catValues]],
            "tags"            => ["arrayValue" => ["values" => $tagValues]],
            "properties"      => ["arrayValue" => ["values" => $propertyValues]],
            "created_at"      => ["stringValue" => $now],
            "updated_at"      => ["stringValue" => $now],
            
            // MoveDrop association - এখন সবসময় কমপক্ষে ১টা category থাকবে
            "channel_association" => ["mapValue" => ["fields" => [
                "custom" => ["arrayValue" => ["values" => [
                    ["mapValue" => ["fields" => [
                        "category_ids" => ["arrayValue" => ["values" => $catValues]]
                    ]]]
                ]]]]
            ]]]
        ]
    ];

    // Firestore API Call
    $url = FIRESTORE_URL . "/products?documentId=" . urlencode($prodId);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($firestoreData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    // Response Handling
    if ($httpCode >= 200 && $httpCode < 300) {
        http_response_code(201);
        echo json_encode([
            "message" => "Product Created",
            "data" => [
                "id"         => $prodId,
                "title"      => $inputData['title'] ?? 'Untitled',
                "sku"        => $inputData['sku'] ?? '',
                "tags"       => $inputData['tags'] ?? [],
                "created_at" => $now,
                "updated_at" => $now
            ]
        ]);
    } else {
        http_response_code($httpCode ?: 500);
        $errorData = json_decode($res, true);
        echo json_encode([
            "status"  => "error",
            "message" => $errorData['error']['message'] ?? "Firestore error",
            "details" => $errorData ?? $res,
            "curl_error" => $curlError ?: null
        ]);
    }

    exit();
}

// যদি কোনো ম্যাচ না করে তাহলে 404
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
