<?php
// api/webhook.php
// This file handles Udjokta Pay webhook callbacks

// Your Firebase database URL and secret
$firebaseDatabaseUrl = "https://espera-mavro-6ddc5-default-rtdb.asia-southeast1.firebasedatabase.app";
$firebaseSecret = "YOUR_FIREBASE_SECRET"; // Get from Firebase Console > Project Settings > Service Accounts > Database Secrets

// Udjokta Pay API Key (for verification)
$apiKey = 'oEFrtrdAuuVkBqWooix8CdhBdhCFql0kf5Xp7InJ';

// Get the API key from headers
$headers = getallheaders();
$headerApi = isset($headers['RT-UDDOKTAPAY-API-KEY']) ? $headers['RT-UDDOKTAPAY-API-KEY'] : null;

// Verify API key
if ($headerApi !== $apiKey) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

// Get webhook data
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

if (!$data) {
    http_response_code(400);
    die(json_encode(['error' => 'Invalid JSON']));
}

// Log webhook data
file_put_contents('webhook_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($data) . PHP_EOL, FILE_APPEND);

// Extract order information from metadata
$metadata = $data['metadata'] ?? [];
$orderId = $metadata['order_id'] ?? null;
$invoiceId = $data['invoice_id'] ?? null;
$status = $data['status'] ?? 'PENDING';

if (!$orderId || !$invoiceId) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing order_id or invoice_id']));
}

// Update order status in Firebase
if ($status === 'COMPLETED') {
    // Payment successful
    $updateData = [
        'payment.status' => 'completed',
        'payment.invoice_id' => $invoiceId,
        'payment.transaction_id' => $data['transaction_id'] ?? null,
        'payment.payment_method' => $data['payment_method'] ?? null,
        'payment.sender_number' => $data['sender_number'] ?? null,
        'status' => 'processing',
        'updated_at' => date('c'),
        'status_history' => [
            [
                'status' => 'processing',
                'timestamp' => date('c'),
                'note' => 'Payment completed via Udjokta Pay'
            ]
        ]
    ];
    
    // Send to Firebase
    $firebaseUrl = "{$firebaseDatabaseUrl}/orders/{$orderId}.json?auth={$firebaseSecret}";
    
    $ch = curl_init($firebaseUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updateData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        // Send WhatsApp notification to admin
        sendAdminNotification($data, $orderId);
        
        echo json_encode(['success' => true, 'message' => 'Order updated successfully']);
    } else {
        echo json_encode(['error' => 'Failed to update Firebase', 'result' => $result]);
    }
} else {
    // Payment failed or pending
    echo json_encode(['success' => true, 'message' => 'Webhook received but status not completed']);
}

// Function to send WhatsApp notification
function sendAdminNotification($paymentData, $orderId) {
    $ultramsgConfig = [
        'instanceId' => 'instance162260',
        'token' => 'f6yiq8o26ak03z5s',
        'adminNumber' => '8801721383418'
    ];
    
    $message = "💰 *PAYMENT RECEIVED*\n\n";
    $message .= "Order ID: {$orderId}\n";
    $message .= "Invoice ID: {$paymentData['invoice_id']}\n";
    $message .= "Amount: ৳{$paymentData['charged_amount']}\n";
    $message .= "Method: {$paymentData['payment_method']}\n";
    $message .= "Transaction: {$paymentData['transaction_id']}\n";
    $message .= "Sender: {$paymentData['sender_number']}\n";
    
    $ch = curl_init("https://api.ultramsg.com/{$ultramsgConfig['instanceId']}/messages/chat");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'token' => $ultramsgConfig['token'],
        'to' => $ultramsgConfig['adminNumber'],
        'body' => $message,
        'priority' => 1
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
