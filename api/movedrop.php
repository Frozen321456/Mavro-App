<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Accept");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

define('MOVEDROP_API_URL', 'https://mavro.xo.je/api/index.php');
define('API_KEY', 'MAVRO-ESSENCE-SECURE-KEY-2026');

$path = $_GET['path'] ?? '';
$headers = getallheaders();
$apiKey = $headers['X-API-KEY'] ?? $_SERVER['HTTP_X_API_KEY'] ?? API_KEY;
$input = file_get_contents('php://input');

$ch = curl_init(MOVEDROP_API_URL . '?path=' . urlencode($path));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-API-KEY: ' . $apiKey,
    'Accept: application/json'
]);

if (in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
}

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($http_code);
echo $response;
?>
