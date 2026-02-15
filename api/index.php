<?php
/**
 * Mavro Essence - MoveDrop Final Official Implementation (Fixed Version)
 * Supports: Title, SKU, Description, Images, Categories, Tags, Properties
 * Fixed: channel_association required error, default category fallback
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
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

$input = json_decode(file_get_contents('php://input'), true);
$path = trim($_GET['path'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];

// Helper: Firestore REST API call
function firestoreRequest($url, $method = 'POST', $data = null) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'body' => json_decode($response, true) ?: $response];
}

// -------------------------------
// POST /products - Create Product
// -------------------------------
if ($path === 'products' && $method === 'POST') {

    $prodId = 'prod_' . time();
    $now = date('c');

    // Category handling + default fallback
    $catValues = [];
    $inputCats = $input['category_ids'] ?? [];

    foreach ((array)$inputCats as $cid) {
        $clean = trim((string)$cid);
        if ($clean !== '') $catValues[] = ["stringValue" => $clean];
    }

    // Default category if empty
    if (empty($catValues)) {
        $defaultCatId = "uncategorized";  // ← এখানে আপনার Firestore-এর বৈধ category doc ID দিন
        $catValues = [["stringValue" => $defaultCatId]];
    }

    // Tags
    $tagValues = [];
    foreach ((array)($input['tags'] ?? []) as $t) {
        $clean = trim((string)$t);
        if ($clean !== '') $tagValues[] = ["stringValue" => $clean];
    }

    // Properties
    $propertyValues = [];
    foreach ((array)($input['properties'] ?? []) as $prop) {
        if (empty($prop['name']) || empty($prop['values'])) continue;

        $vals = [];
        foreach ((array)$prop['values'] as $v) {
            $clean = trim((string)$v);
            if ($clean !== '') $vals[] = ["stringValue" => $clean];
        }
        if (!empty($vals)) {
            $propertyValues[] = ["mapValue" => [
                "fields" => [
                    "name"   => ["stringValue" => (string)$prop['name']],
                    "values" => ["arrayValue" => ["values" => $vals]]
                ]
            ]];
        }
    }

    // Main document fields
    $fields = [
        "id"              => ["stringValue" => $prodId],
        "title"           => ["stringValue" => (string)($input['title'] ?? 'Untitled')],
        "sku"             => ["stringValue" => (string)($input['sku'] ?? 'SKU-'.$prodId)],
        "description"     => ["stringValue" => (string)($input['description'] ?? '')],
        "price"           => ["stringValue" => (string)($input['regular_price'] ?? '0')],
        "image"           => ["stringValue" => $input['images'][0]['src'] ?? ''],
        "category_ids"    => ["arrayValue" => ["values" => $catValues]],
        "tags"            => ["arrayValue" => ["values" => $tagValues]],
        "properties"      => ["arrayValue" => ["values" => $propertyValues]],
        "created_at"      => ["stringValue" => $now],
        "updated_at"      => ["stringValue" => $now],
    ];

    // channel_association শুধু যদি ক্যাটাগরি থাকে তাহলে যোগ করা
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

    // Save to Firestore
    $url = FIRESTORE_URL . "/products?documentId=" . urlencode($prodId);
    $result = firestoreRequest($url, 'POST', $firestoreData);

    if ($result['code'] >= 200 && $result['code'] < 300) {
        http_response_code(201);
        echo json_encode([
            "message" => "Product Created",
            "data" => [
                "id"         => $prodId,
                "title"      => $input['title'] ?? 'Untitled',
                "sku"        => $input['sku'] ?? '',
                "tags"       => $input['tags'] ?? [],
                "created_at" => $now,
                "updated_at" => $now
            ]
        ]);
    } else {
        http_response_code($result['code'] ?: 500);
        echo json_encode([
            "status"  => "error",
            "message" => $result['body']['error']['message'] ?? "Firestore error",
            "details" => $result['body'] ?? $result['body']
        ]);
    }

    exit();
}

// -------------------------------
// GET /products - List Products
// -------------------------------
if ($path === 'products' && $method === 'GET') {

    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = min(50, max(1, (int)($_GET['per_page'] ?? 20)));

    $queryParams = [
        'orderBy' => 'created_at desc',
        'pageSize' => $perPage,
        'pageToken' => $_GET['pageToken'] ?? ''
    ];

    $url = FIRESTORE_URL . "/products?" . http_build_query($queryParams);

    $result = firestoreRequest($url, 'GET');

    if ($result['code'] === 200) {
        $docs = $result['body']['documents'] ?? [];
        $products = [];

        foreach ($docs as $doc) {
            $fields = $doc['fields'] ?? [];
            $products[] = [
                'id'    => $doc['name'] ? basename($doc['name']) : '',
                'title' => $fields['title']['stringValue'] ?? '',
                'sku'   => $fields['sku']['stringValue'] ?? '',
                'price' => $fields['price']['stringValue'] ?? '0',
                'image' => $fields['image']['stringValue'] ?? '',
                'tags'  => array_map(fn($t) => $t['stringValue'] ?? '', $fields['tags']['arrayValue']['values'] ?? [])
            ];
        }

        echo json_encode([
            "data" => $products,
            "meta" => [
                "current_page" => $page,
                "per_page"     => $perPage,
                "next_page_token" => $result['body']['nextPageToken'] ?? null
            ]
        ]);
    } else {
        http_response_code($result['code']);
        echo json_encode(["status" => "error", "message" => "Failed to fetch products"]);
    }

    exit();
}

// 404
http_response_code(404);
echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
