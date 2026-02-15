<?php
/**
 * Mavro Essence - MoveDrop to Firestore Ultimate Fix
 * Full support for Categories, Tags, and Products
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
define('FIRESTORE_BASE', "https://firestore.googleapis.com/v1/projects/" . PROJECT_ID . "/databases/(default)/documents");

// Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit();
}

// Firestore REST API Format Helper (Crucial for Firestore)
function mapToFirestore($data) {
    $fields = [];
    foreach ($data as $key => $value) {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $v) {
                if (is_array($v)) { // For nested arrays like images
                    $values[] = ["stringValue" => json_encode($v)];
                } else {
                    $values[] = ["stringValue" => (string)$v];
                }
            }
            $fields[$key] = ["arrayValue" => ["values" => $values]];
        } else {
            $fields[$key] = ["stringValue" => (string)$value];
        }
    }
    return ["fields" => $fields];
}

function firestorePost($collection, $docId, $data) {
    $url = FIRESTORE_BASE . "/$collection?documentId=$docId";
    $payload = mapToFirestore($data);
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

$inputData = json_decode(file_get_contents('php://input'), true);
$path = $_GET['path'] ?? '';
$parts = explode('/', trim($path, '/'));
$resource = $parts[0] ?? '';

switch ($resource) {
    case 'categories':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $catId = 'cat_' . time();
            $data = [
                'id' => $catId,
                'name' => $inputData['name'] ?? 'New Category',
                'slug' => strtolower(str_replace(' ', '-', $inputData['name'] ?? 'cat'))
            ];
            firestorePost('categories', $catId, $data);
            echo json_encode(["data" => $data]);
        }
        break;

    case 'products':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $prodId = $parts[1] ?? 'prod_' . time();
            
            // MoveDrop specific fields
            $category_ids = $inputData['category_ids'] ?? ["uncategorized"];
            $tags = $inputData['tags'] ?? [];
            
            // Image handling
            $mainImage = "";
            if (!empty($inputData['images']) && isset($inputData['images'][0]['src'])) {
                $mainImage = $inputData['images'][0]['src'];
            }

            $productData = [
                'id' => $prodId,
                'title' => $inputData['title'] ?? '',
                'name' => $inputData['title'] ?? '', // For shop.html
                'sku' => $inputData['sku'] ?? '',
                'price' => (string)($inputData['regular_price'] ?? '0'),
                'image' => $mainImage,
                'description' => strip_tags($inputData['description'] ?? ''),
                'category' => (string)$category_ids[0],
                'category_ids' => $category_ids, // MoveDrop requires this
                'tags' => $tags,                 // Tags added here
                'created_at' => date('c')
            ];

            // channel_association logic for MoveDrop validation
            $productData['channel_association_custom_category_ids'] = $category_ids;

            firestorePost('products', $prodId, $productData);
            
            http_response_code(201);
            echo json_encode(["message" => "Success", "id" => $prodId]);
        }
        break;

    case 'health':
        echo json_encode(["status" => "online", "database" => "Firestore-REST-v5"]);
        break;

    default:
        http_response_code(404);
        echo json_encode(["message" => "Not Found"]);
        break;
}
