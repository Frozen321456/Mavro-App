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

// Only handle products creation for now
if (strpos($path, 'products') !== false && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $prodId = 'prod_' . time();
    $now = date('c');

    // Category Mapping
    $catValues = [];
    $providedCategories = $inputData['category_ids'] ?? [];

    foreach ($providedCategories as $id) {
        $cleanId = trim((string)$id);
        if ($cleanId !== '') {
            $catValues[] = ["stringValue" => $cleanId];
        }
    }

    // যদি কোনো ক্যাটাগরি না থাকে তাহলে ডিফল্ট দিতে চাইলে এখানে আনকমেন্ট করুন
    // $DEFAULT_CATEGORY_ID = "uncategorized";   // ← আপনার Firestore-এর বৈধ category doc ID দিন
    // if (empty($catValues)) {
    //     $catValues = [["stringValue" => $DEFAULT_CATEGORY_ID]];
    // }

    // Tags Mapping
    $tagValues = [];
    foreach (($inputData['tags'] ?? []) as $tag) {
        $cleanTag = trim((string)$tag);
        if ($cleanTag !== '') {
            $tagValues[] = ["stringValue" => $cleanTag];
        }
    }

    // Properties Mapping
    $propertyValues = [];
    foreach (($inputData['properties'] ?? []) as $prop) {
        if (empty($prop['name']) || empty($prop['values'])) continue;

        $pVals = [];
        foreach ($prop['values'] as $v) {
            $cleanVal = trim((string)$v);
            if ($cleanVal !== '') {
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

    // Firestore Data Structure
    $fields = [
        "id"              => ["stringValue" => $prodId],
        "title"           => ["stringValue" => (string)($inputData['title'] ?? 'Untitled Product')],
        "sku"             => ["stringValue" => (string)($inputData['sku'] ?? 'SKU-' . $prodId)],
        "description"     => ["stringValue" => (string)($inputData['description'] ?? '')],
        "price"           => ["stringValue" => (string)($inputData['regular_price'] ?? '0')],
        "image"           => ["stringValue" => $inputData['images'][0]['src'] ?? ''],
        "category_ids"    => ["arrayValue" => ["values" => $catValues]],
        "tags"            => ["arrayValue" => ["values" => $tagValues]],
        "properties"      => ["arrayValue" => ["values" => $propertyValues]],
        "created_at"      => ["stringValue" => $now],
        "updated_at"      => ["stringValue" => $now],
    ];

    // channel_association শুধু যদি ক্যাটাগরি থাকে তাহলে যোগ করা হবে
    if (!empty($catValues)) {
        $fields["channel_association"] = [
            "mapValue" => [
                "fields" => [
                    "custom" => [
                        "arrayValue" => [
                            "values" => [
                                ["mapValue" => [
                                    "fields" => [
                                        "category_ids" => ["arrayValue" => ["values" => $catValues]]
                                    ]
                                ]]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    $firestoreData = ["fields" => $fields];

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

    // Response
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
        $errorData = json_decode($res, true) ?? [];
        echo json_encode([
            "status"  => "error",
            "message" => $errorData['error']['message'] ?? "Firestore write failed",
            "http_code" => $httpCode,
            "details" => $errorData['error'] ?? $res,
            "curl_error" => $curlError ?: null
        ]);
    }

    exit();
}

// Default 404
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
