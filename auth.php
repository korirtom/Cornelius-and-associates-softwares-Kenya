<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        exit;
    }
    
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Check credentials
        $query = "SELECT * FROM admins WHERE username = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // For initial login: username=admin, password=admin (hardcoded)
            // After password change, use password_verify
            if ($username === 'admin' && $password === 'admin') {
                // First time login with default credentials
                session_start();
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_id'] = $admin['id'];
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Login successful',
                    'username' => $username,
                    'first_login' => true
                ]);
            } else {
                // Check with password_verify for changed passwords
                if (password_verify($password, $admin['password_hash'])) {
                    session_start();
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_id'] = $admin['id'];
                    
                    // Update last login
                    $updateQuery = "UPDATE admins SET last_login = NOW() WHERE id = :id";
                    $updateStmt = $db->prepare($updateQuery);
                    $updateStmt->bindParam(':id', $admin['id']);
                    $updateStmt->execute();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Login successful',
                        'username' => $username,
                        'first_login' => false
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
                }
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>