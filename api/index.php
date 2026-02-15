<?php
/**
 * Mavro Essence - MoveDrop EXCLUSIVE API (Firestore Version)
 * শুধুমাত্র MoveDrop এর রিকোয়ারমেন্ট অনুযায়ী তৈরি।
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
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_URL', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// সিকিউরিটি চেক
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Firestore ডেটা ফরম্যাট হেল্পার
function formatToFirestore($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $arrayValues = [];
            foreach ($value as $v) { $arrayValues[] = ["stringValue" => (string)$v]; }
            $fields[$key] = ["arrayValue" => ["values" => $arrayValues]];
        } else {
            $fields[$key] = ["stringValue" => (string)$value];
        }
    }
    return ["fields" => $fields];
}

// CURL ফাংশন
function firestoreRequest($path, $method = 'GET', $body = null) {
    $url = FIRESTORE_URL . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

// ইনপুট হ্যান্ডলিং
$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';
$parts = explode('/', trim($path, '/'));
$resource = $parts[0] ?? '';

switch ($resource) {
    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $res = firestoreRequest('/categories');
            $list = [];
            if (isset($res['documents'])) {
                foreach ($res['documents'] as $doc) {
                    $list[] = [
                        "id" => basename($doc['name']),
                        "name" => $doc['fields']['name']['stringValue'] ?? 'Unnamed'
                    ];
                }
            }
            echo json_encode(["data" => $list]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ১. MoveDrop এর পাঠানো ক্যাটাগরি আইডি ধরা
            $category_ids = $inputData['category_ids'] ?? [];
            
            // যদি একেবারেই খালি থাকে, তবে ফোর্সড একটি আইডি দেওয়া যাতে এরর না আসে
            if (empty($category_ids)) {
                $category_ids = ["mavro_general"];
            }

            // ২. প্রোডাক্ট আইডি
            $docId = $parts[1] ?? 'prod_' . time();

            // ৩. MoveDrop যেভাবে ডাটা চায় ঠিক সেভাবেই স্ট্রাকচার তৈরি
            $fields = [
                'id' => $docId,
                'title' => $inputData['title'] ?? '',
                'sku' => $inputData['sku'] ?? '',
                'regular_price' => (string)($inputData['regular_price'] ?? '0'),
                'description' => $inputData['description'] ?? '',
                'category_ids' => $category_ids, // এই সেই ফিল্ড যা মুভড্রপ খুঁজছে
                'images' => array_map(function($img){ return $img['src']; }, $inputData['images'] ?? []),
                'created_at' => date('c')
            ];

            // ৪. 'channel_association' ফিল্ডটি যোগ করা (Extra Security for MoveDrop)
            $fields['channel_association'] = ["custom" => [["category_ids" => $category_ids]]];

            // Firestore এ সেভ
            $payload = formatToFirestore($fields);
            firestoreRequest("/products?documentId=$docId", 'POST', $payload);

            http_response_code(201);
            echo json_encode([
                "message" => "Product Sync Success",
                "data" => ["id" => $docId]
            ]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "engine" => "MoveDrop-Only-v4"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
