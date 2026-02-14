<?php
// webhook-handler.php - MoveDrop থেকে webhook receive করার জন্য

header("Content-Type: application/json");

// Get webhook data
$input = json_decode(file_get_contents('php://input'), true);
$event = $_GET['event'] ?? '';

// Log webhook (optional)
$logFile = 'webhook_log.txt';
$logData = date('Y-m-d H:i:s') . " - Event: " . $event . " - Data: " . json_encode($input) . "\n";
file_put_contents($logFile, $logData, FILE_APPEND);

// Handle different webhook events
switch ($event) {
    case 'product.deleted':
        // Handle product deleted from MoveDrop
        $productId = $input['id'] ?? null;
        if ($productId) {
            // Delete from your database
            // firebaseRequest('/products/' . $productId, 'DELETE');
        }
        break;
        
    case 'order.created':
        // Handle new order from MoveDrop
        $orderData = $input;
        // Save to your database
        break;
        
    case 'order.updated':
        // Handle order update from MoveDrop
        $orderId = $input['id'] ?? null;
        $status = $input['status'] ?? null;
        if ($orderId && $status) {
            // Update order status in your database
        }
        break;
}

// Always return success
http_response_code(200);
echo json_encode(['status' => 'received']);
?>