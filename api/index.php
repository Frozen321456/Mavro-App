<?php
/**
 * MoveDrop Custom Channel - Optimized for Firestore/Firebase structure
 * Website: https://mavro-app.vercel.app/
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 1. Config
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// 2. Auth
function verifyKey() {
    $headers = array_change_key_case(getallheaders(), CASE_UPPER);
    $providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
    if ($providedKey !== API_KEY) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized"]);
        exit();
    }
}

// 3. Firebase Helper
function firebase($path, $method = 'GET', $data = null) {
    $url = FIREBASE_URL . $path . '.json';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// 4. Pagination Helper
function paginate($data, $page, $perPage) {
    if (!$data) return ["data" => [], "meta" => ["total" => 0]];
    $items = array_values($data);
    $total = count($items);
    $offset = ($page - 1) * $perPage;
    $pagedData = array_slice($items, $offset, $perPage);
    return [
        "data" => $pagedData,
        "meta" => [
            "current_page" => (int)$page,
            "last_page" => ceil($total / $perPage),
            "per_page" => (int)$perPage,
            "total" => $total
        ]
    ];
}

verifyKey();

$method = $_SERVER['REQUEST_METHOD'];
$inputData = json_decode(file_get_contents('php://input'), true);
$pathInfo = $_GET['path'] ?? '';
$parts = explode('/', trim($pathInfo, '/'));
$resource = $parts[0] ?? '';
$id = $parts[1] ?? null;

switch ($resource) {
    case 'categories':
        if ($method === 'GET') {
            $data = firebase('/categories');
            echo json_encode(paginate($data, $_GET['page'] ?? 1, $_GET['per_page'] ?? 20));
        } elseif ($method === 'POST') {
            $newCat = [
                'id' => uniqid(),
                'name' => $inputData['name'] ?? 'Unnamed',
                'created_at' => date('c')
            ];
            firebase('/categories/'.$newCat['id'], 'PUT', $newCat);
            echo json_encode(["data" => $newCat]);
        }
        break;

    case 'products':
        if ($method === 'POST') {
            if ($id && ($parts[2] ?? '') === 'variations') {
                firebase("/products/$id/variations", 'PATCH', ['variations' => $inputData['variations']]);
                echo json_encode(["message" => "Variations Added"]);
            } else {
                // Check if MoveDrop sent category_ids
                // Jodi empty thake, amra default ekta category pathabo jate error na hoy
                $categoryIds = $inputData['category_ids'] ?? [];
                $mainCategory = !empty($categoryIds) ? $categoryIds[0] : "uncategorized";

                $productData = [
                    'id' => uniqid(),
                    'title' => $inputData['title'] ?? '',
                    'sku' => $inputData['sku'] ?? '',
                    'description' => $inputData['description'] ?? '',
                    'price' => $inputData['regular_price'] ?? 0,
                    'image' => $inputData['images'][0]['src'] ?? '', // First image as main
                    'images' => $inputData['images'] ?? [],
                    'category' => $mainCategory, // Apnar Firestore compatible field
                    'category_ids' => $categoryIds, // MoveDrop compatible field
                    'tags' => $inputData['tags'] ?? [],
                    'properties' => $inputData['properties'] ?? [],
                    'created_at' => date('c')
                ];

                firebase('/products/'.$productData['id'], 'PUT', $productData);
                http_response_code(201);
                echo json_encode(["message" => "Product Created", "data" => $productData]);
            }
        }
        break;

    case 'orders':
        // ... (Order handling code same as previous)
        $data = firebase('/orders');
        echo json_encode(paginate($data, $_GET['page'] ?? 1, $_GET['per_page'] ?? 20));
        break;

    case 'health':
        echo json_encode(["status" => "online"]);
        break;
}
