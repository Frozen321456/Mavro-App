<?php
// api/index.php - MoveDrop Connection Fixed
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('API_KEY', 'EsperaApiKey26'); 
define('FIREBASE_URL', 'https://espera-mavro-6ddc5-default-rtdb.firebaseio.com');

// Get Request Path
$requestPath = $_GET['path'] ?? '';

// 1. Health Check - মুভড্রপ কানেক্ট করার সময় এটি চেক করে
if ($requestPath === 'health') {
    echo json_encode([
        'status' => 'online',
        'message' => 'MoveDrop API is running'
    ]);
    exit();
}

// 2. Security Check
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$providedKey = $headers['X-API-KEY'] ?? '';

if ($providedKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid API Key']);
    exit();
}

// 3. Orders Endpoint (MoveDrop Fetching)
if ($requestPath === 'orders') {
    $ch = curl_init(FIREBASE_URL . '/orders.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    $formattedOrders = [];
    
    if ($data) {
        foreach ($data as $id => $order) {
            $order['id'] = $id; // মুভড্রপ আইডি খোঁজে
            $formattedOrders[] = $order;
        }
    }
    
    // মুভড্রপ এই ফরম্যাটটিই চায়
    echo json_encode(['data' => $formattedOrders]);
    exit();
}
