<?php
/**
 * Mavro Essence - Official Firestore API
 * Project ID: espera-mavro-6ddc5
 * Fixing: Category Add Issue & Product Association Error
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// আপনার Firestore প্রজেক্ট কনফিগারেশন
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');
define('PROJECT_ID', 'espera-mavro-6ddc5');
define('FIRESTORE_BASE', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Firestore Helper: Data Mapping
function toFirestore($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $v) { $values[] = ["stringValue" => (string)$v]; }
            $fields[$key] = ["arrayValue" => ["values" => $values]];
        } else {
            $fields[$key] = ["stringValue" => (string)$value];
        }
    }
    return ["fields" => $fields];
}

function fromFirestore($doc) {
    $data = ["id" => basename($doc['name'])];
    if (isset($doc['fields'])) {
        foreach ($doc['fields'] as $key => $value) {
            if (isset($value['stringValue'])) $data[$key] = $value['stringValue'];
            elseif (isset($value['arrayValue']['values'])) {
                $data[$key] = array_map(function($v) { return $v['stringValue'] ?? ''; }, $value['arrayValue']['values']);
            }
        }
    }
    return $data;
}

// Firebase CURL Request
function firestoreRequest($path, $method = 'GET', $body = null) {
    $url = FIRESTORE_BASE . $path;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$inputData = json_decode(file_get_contents('php://input'), true);
$pathInfo = $_GET['path'] ?? '';
$parts = explode('/', trim($pathInfo, '/'));
$resource = $parts[0] ?? '';
$docId = $parts[1] ?? null;

switch ($resource) {
    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $res = firestoreRequest('/categories');
            $list = [];
            if (isset($res['documents'])) {
                foreach ($res['documents'] as $d) $list[] = fromFirestore($d);
            }
            echo json_encode(["data" => $list]);
        } 
        elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = 'cat_' . time();
            $payload = toFirestore(['id' => $id, 'name' => $inputData['name'] ?? 'New Category']);
            firestoreRequest("/categories?documentId=$id", 'POST', $payload);
            echo json_encode(["data" => ["id" => $id, "name" => $inputData['name']]]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $catIds = $inputData['category_ids'] ?? ["default"];
            $id = $docId ?? 'prod_' . time();
            
            // শপ এবং মুভড্রপ উভয়ের জন্য ডাটা ফিল্ড
            $fields = [
                'id' => $id,
                'name' => $inputData['title'] ?? '',
                'title' => $inputData['title'] ?? '',
                'sku' => $inputData['sku'] ?? '',
                'price' => (string)($inputData['regular_price'] ?? '0'),
                'image' => $inputData['images'][0]['src'] ?? '',
                'category' => (string)$catIds[0],
                'category_ids' => $catIds,
                'description' => strip_tags($inputData['description'] ?? '')
            ];

            $res = firestoreRequest("/products?documentId=$id", 'POST', toFirestore($fields));
            
            http_response_code(201);
            echo json_encode(["message" => "Success", "id" => $id]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "mode" => "Firestore-REST"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
