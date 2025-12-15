<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get platform settings
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT * FROM platform_settings LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $settings = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $settings]);
        } else {
            // Return default settings
            echo json_encode([
                'success' => true,
                'data' => [
                    'platform_name' => 'PromptTemplates',
                    'contact_phone' => '+254 700 000 000',
                    'contact_email' => 'support@prompttemplates.com'
                ]
            ]);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to get settings: ' . $e->getMessage()]);
    }
}

// Update platform settings (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update') {
    checkAuth();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Check if settings exist
        $checkQuery = "SELECT id FROM platform_settings LIMIT 1";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() > 0) {
            // Update existing
            $updateQuery = "UPDATE platform_settings SET 
                           platform_name = :platform_name,
                           logo_url = :logo_url,
                           contact_phone = :contact_phone,
                           contact_email = :contact_email,
                           tiktok_url = :tiktok_url,
                           facebook_url = :facebook_url,
                           updated_at = NOW()
                           WHERE id = 1";
        } else {
            // Insert new
            $updateQuery = "INSERT INTO platform_settings 
                           (platform_name, logo_url, contact_phone, contact_email, tiktok_url, facebook_url) 
                           VALUES (:platform_name, :logo_url, :contact_phone, :contact_email, :tiktok_url, :facebook_url)";
        }
        
        $stmt = $db->prepare($updateQuery);
        $stmt->bindParam(':platform_name', $data['platform_name']);
        $stmt->bindParam(':logo_url', $data['logo_url']);
        $stmt->bindParam(':contact_phone', $data['contact_phone']);
        $stmt->bindParam(':contact_email', $data['contact_email']);
        $stmt->bindParam(':tiktok_url', $data['tiktok_url']);
        $stmt->bindParam(':facebook_url', $data['facebook_url']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Change admin password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'change_password') {
    checkAuth();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['current_password']) || empty($data['new_password']) || empty($data['confirm_password'])) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            exit;
        }
        
        if ($data['new_password'] !== $data['confirm_password']) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
            exit;
        }
        
        if (strlen($data['new_password']) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
            exit;
        }
        
        session_start();
        $admin_id = $_SESSION['admin_id'];
        
        // Get current admin
        $query = "SELECT * FROM admins WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $admin_id);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Verify current password
        // Special case for default admin/admin
        if ($admin['username'] === 'admin' && $data['current_password'] === 'admin') {
            // First time password change from default
            $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
            
            $updateQuery = "UPDATE admins SET password_hash = :password_hash WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':password_hash', $hashed_password);
            $updateStmt->bindParam(':id', $admin_id);
            
            if ($updateStmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to change password']);
            }
        } else {
            // Verify using password_hash
            if (password_verify($data['current_password'], $admin['password_hash'])) {
                $hashed_password = password_hash($data['new_password'], PASSWORD_DEFAULT);
                
                $updateQuery = "UPDATE admins SET password_hash = :password_hash WHERE id = :id";
                $updateStmt = $db->prepare($updateQuery);
                $updateStmt->bindParam(':password_hash', $hashed_password);
                $updateStmt->bindParam(':id', $admin_id);
                
                if ($updateStmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Password changed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to change password']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            }
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Upload file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'upload') {
    checkAuth();
    
    if (!isset($_FILES['file'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        exit;
    }
    
    $file = $_FILES['file'];
    $upload_dir = '../uploads/';
    
    // Create uploads directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    // Generate unique filename
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $file_name = uniqid() . '_' . time() . '.' . $file_ext;
    $file_path = $upload_dir . $file_name;
    
    // Check file type
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'zip'];
    if (!in_array($file_ext, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        exit;
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10MB)']);
        exit;
    }
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        $file_url = 'uploads/' . $file_name;
        echo json_encode(['success' => true, 'file_url' => $file_url]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
}
?>