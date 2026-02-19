<?php
/**
 * SIMPLE API - Working Version
 * File: api.php
 */

// Headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-KEY, Authorization");

// Handle OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Simple routing
$request_uri = $_SERVER['REQUEST_URI'];
$path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
$method = $_SERVER['REQUEST_METHOD'];

// Remove file name from path
$path = str_replace(['api.php', 'index.php'], '', $path);
$path = trim($path, '/');

// Get query parameters
$action = $_GET['action'] ?? $path ?: 'health';

// Response array
$response = [
    'status' => 'ok',
    'timestamp' => date('c'),
    'path' => $path,
    'action' => $action,
    'method' => $method
];

// Handle different actions
switch ($action) {
    case 'health':
        $response['message'] = 'API is working';
        $response['php_version'] = PHP_VERSION;
        $response['server'] = $_SERVER['SERVER_SOFTWARE'] ?? 'unknown';
        break;
        
    case 'products':
        $response['message'] = 'Products endpoint';
        $response['data'] = [
            ['id' => 1, 'name' => 'Sample Product 1', 'price' => 1000],
            ['id' => 2, 'name' => 'Sample Product 2', 'price' => 2000]
        ];
        break;
        
    case 'categories':
        $response['message'] = 'Categories endpoint';
        $response['data'] = [
            ['id' => 1, 'name' => 'Electronics'],
            ['id' => 2, 'name' => 'Fashion']
        ];
        break;
        
    default:
        http_response_code(404);
        $response['status'] = 'error';
        $response['message'] = 'Endpoint not found';
        $response['available'] = ['health', 'products', 'categories'];
}

// Return JSON
echo json_encode($response, JSON_PRETTY_PRINT);
