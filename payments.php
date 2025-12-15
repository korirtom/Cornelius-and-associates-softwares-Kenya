<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Process M-Pesa payment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $required = ['phone', 'amount', 'template_ids', 'customer_name', 'customer_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            exit;
        }
    }
    
    try {
        // Format phone number
        $phone = $data['phone'];
        if (strlen($phone) === 9) {
            $phone = '254' . $phone;
        } else if (strlen($phone) === 10 && $phone[0] === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // Generate unique transaction ID
        $transaction_id = 'TXN_' . time() . '_' . rand(1000, 9999);
        
        // Check if user exists
        $userQuery = "SELECT id FROM users WHERE email = :email";
        $userStmt = $db->prepare($userQuery);
        $userStmt->bindParam(':email', $data['customer_email']);
        $userStmt->execute();
        
        if ($userStmt->rowCount() > 0) {
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $user['id'];
        } else {
            // Create new user
            $insertUserQuery = "INSERT INTO users (email, phone, full_name) VALUES (:email, :phone, :full_name)";
            $insertUserStmt = $db->prepare($insertUserQuery);
            $insertUserStmt->bindParam(':email', $data['customer_email']);
            $insertUserStmt->bindParam(':phone', $phone);
            $insertUserStmt->bindParam(':full_name', $data['customer_name']);
            $insertUserStmt->execute();
            $user_id = $db->lastInsertId();
        }
        
        // Record purchase
        $purchaseQuery = "INSERT INTO purchases (transaction_id, user_id, amount, phone_number, status) 
                          VALUES (:transaction_id, :user_id, :amount, :phone_number, 'pending')";
        $purchaseStmt = $db->prepare($purchaseQuery);
        $purchaseStmt->bindParam(':transaction_id', $transaction_id);
        $purchaseStmt->bindParam(':user_id', $user_id);
        $purchaseStmt->bindParam(':amount', $data['amount']);
        $purchaseStmt->bindParam(':phone_number', $phone);
        $purchaseStmt->execute();
        
        $purchase_id = $db->lastInsertId();
        
        // Record template purchases
        foreach ($data['template_ids'] as $template_id) {
            $templateQuery = "INSERT INTO purchase_templates (purchase_id, template_id) VALUES (:purchase_id, :template_id)";
            $templateStmt = $db->prepare($templateQuery);
            $templateStmt->bindParam(':purchase_id', $purchase_id);
            $templateStmt->bindParam(':template_id', $template_id);
            $templateStmt->execute();
        }
        
        // Simulate M-Pesa payment (in production, integrate with actual M-Pesa API)
        // For demo, simulate 90% success rate
        $is_success = rand(1, 100) <= 90;
        
        if ($is_success) {
            // Successful payment
            $receipt = 'MPE' . time() . rand(100, 999);
            $download_url = 'download.php?token=' . bin2hex(random_bytes(16));
            
            $updateQuery = "UPDATE purchases SET 
                           status = 'completed', 
                           mpesa_receipt = :receipt,
                           download_url = :download_url,
                           completed_at = NOW()
                           WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':receipt', $receipt);
            $updateStmt->bindParam(':download_url', $download_url);
            $updateStmt->bindParam(':id', $purchase_id);
            $updateStmt->execute();
            
            // Update download counts
            foreach ($data['template_ids'] as $template_id) {
                $countQuery = "UPDATE templates SET downloads_count = downloads_count + 1 WHERE id = :id";
                $countStmt = $db->prepare($countQuery);
                $countStmt->bindParam(':id', $template_id);
                $countStmt->execute();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Payment successful',
                'transaction_id' => $transaction_id,
                'receipt' => $receipt,
                'download_url' => $download_url
            ]);
        } else {
            // Failed payment
            $updateQuery = "UPDATE purchases SET status = 'failed' WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $purchase_id);
            $updateStmt->execute();
            
            // Record failed payment
            $failedQuery = "INSERT INTO failed_payments (transaction_id, phone_number, amount, error_message) 
                           VALUES (:transaction_id, :phone_number, :amount, :error_message)";
            $failedStmt = $db->prepare($failedQuery);
            $failedStmt->bindParam(':transaction_id', $transaction_id);
            $failedStmt->bindParam(':phone_number', $phone);
            $failedStmt->bindParam(':amount', $data['amount']);
            $error_message = 'Payment cancelled by user';
            $failedStmt->bindParam(':error_message', $error_message);
            $failedStmt->execute();
            
            echo json_encode([
                'success' => false,
                'message' => 'Payment failed. Please try again.',
                'transaction_id' => $transaction_id
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Payment processing error: ' . $e->getMessage()]);
    }
}

// Get payment statistics (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['stats'])) {
    checkAuth();
    
    try {
        // Total sales
        $salesQuery = "SELECT COALESCE(SUM(amount), 0) as total_sales FROM purchases WHERE status = 'completed'";
        $salesStmt = $db->prepare($salesQuery);
        $salesStmt->execute();
        $sales = $salesStmt->fetch(PDO::FETCH_ASSOC);
        
        // Successful payments count
        $successQuery = "SELECT COUNT(*) as count FROM purchases WHERE status = 'completed'";
        $successStmt = $db->prepare($successQuery);
        $successStmt->execute();
        $success = $successStmt->fetch(PDO::FETCH_ASSOC);
        
        // Failed payments count
        $failedQuery = "SELECT COUNT(*) as count FROM failed_payments";
        $failedStmt = $db->prepare($failedQuery);
        $failedStmt->execute();
        $failed = $failedStmt->fetch(PDO::FETCH_ASSOC);
        
        // Recent transactions
        $recentQuery = "SELECT p.*, u.full_name, u.email 
                       FROM purchases p 
                       LEFT JOIN users u ON p.user_id = u.id 
                       ORDER BY p.payment_date DESC 
                       LIMIT 10";
        $recentStmt = $db->prepare($recentQuery);
        $recentStmt->execute();
        
        $recent = [];
        while ($row = $recentStmt->fetch(PDO::FETCH_ASSOC)) {
            $recent[] = $row;
        }
        
        // All payments
        $paymentsQuery = "SELECT p.*, u.full_name, u.email 
                         FROM purchases p 
                         LEFT JOIN users u ON p.user_id = u.id 
                         ORDER BY p.payment_date DESC";
        $paymentsStmt = $db->prepare($paymentsQuery);
        $paymentsStmt->execute();
        
        $payments = [];
        while ($row = $paymentsStmt->fetch(PDO::FETCH_ASSOC)) {
            $payments[] = $row;
        }
        
        // Failed payments list
        $failedListQuery = "SELECT * FROM failed_payments ORDER BY attempted_at DESC";
        $failedListStmt = $db->prepare($failedListQuery);
        $failedListStmt->execute();
        
        $failedList = [];
        while ($row = $failedListStmt->fetch(PDO::FETCH_ASSOC)) {
            $failedList[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sales' => $sales['total_sales'],
                'successful_payments' => $success['count'],
                'failed_payments' => $failed['count']
            ],
            'recent_transactions' => $recent,
            'all_payments' => $payments,
            'failed_payments' => $failedList
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get statistics: ' . $e->getMessage()]);
    }
}
?>