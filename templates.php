<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get all active templates
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $query = "SELECT * FROM templates WHERE is_active = 1 ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $templates = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $templates[] = $row;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $templates,
            'count' => count($templates)
        ]);
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to fetch templates: ' . $e->getMessage()]);
    }
}

// Add new template (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkAuth();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $required = ['name', 'description', 'price'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
                exit;
            }
        }
        
        $query = "INSERT INTO templates (name, description, price, background_url, zip_file_url, preview_html) 
                  VALUES (:name, :description, :price, :background_url, :zip_file_url, :preview_html)";
        
        $stmt = $db->prepare($query);
        
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':description', $data['description']);
        $stmt->bindParam(':price', $data['price']);
        $stmt->bindParam(':background_url', $data['background_url']);
        $stmt->bindParam(':zip_file_url', $data['zip_file_url']);
        $stmt->bindParam(':preview_html', $data['preview_html']);
        
        if ($stmt->execute()) {
            echo json_encode([
                'success' => true,
                'message' => 'Template added successfully',
                'template_id' => $db->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to add template']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

// Delete template (Admin only)
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    checkAuth();
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['id'])) {
            echo json_encode(['success' => false, 'message' => 'Template ID required']);
            exit;
        }
        
        $query = "UPDATE templates SET is_active = 0 WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $data['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Template deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete template']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>