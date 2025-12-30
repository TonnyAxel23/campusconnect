<?php
// api/auth/reset-password.php
// Reset Password Endpoint

require_once dirname(__DIR__, 2) . '/includes/config/Database.php';
require_once dirname(__DIR__, 2) . '/includes/utils/Response.php';

use CampusConnect\Utils\Response;

// Allow POST for reset and GET for token validation
if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
    Response::error('Method not allowed', 405);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Validate reset token
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        Response::error('Reset token is required', 400);
    }
    
    try {
        $db = \CampusConnect\Config\Database::getInstance()->getConnection();
        
        $stmt = $db->prepare("
            SELECT pr.*, u.campus_email 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            Response::error('Invalid or expired reset token', 400);
        }
        
        Response::success(['token' => $token], 'Token is valid');
        
    } catch (Exception $e) {
        error_log("Token Validation Error: " . $e->getMessage());
        Response::error('Token validation failed');
    }
    
} else {
    // POST request to reset password
    $input = json_decode(file_get_contents('php://input'), true);
    
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    if (empty($token) || empty($password) || empty($confirmPassword)) {
        Response::error('Token, password and confirmation are required', 400);
    }
    
    if ($password !== $confirmPassword) {
        Response::error('Passwords do not match', 400);
    }
    
    if (strlen($password) < 8) {
        Response::error('Password must be at least 8 characters', 400);
    }
    
    try {
        $db = \CampusConnect\Config\Database::getInstance()->getConnection();
        
        // Validate token
        $stmt = $db->prepare("
            SELECT pr.*, u.id as user_id 
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        
        $stmt->execute([$token]);
        $reset = $stmt->fetch();
        
        if (!$reset) {
            Response::error('Invalid or expired reset token', 400);
        }
        
        // Hash new password
        $passwordHash = password_hash($password, PASSWORD_BCRYPT);
        
        // Update user password
        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->execute([$passwordHash, $reset['user_id']]);
        
        // Delete used reset token
        $stmt = $db->prepare("DELETE FROM password_resets WHERE id = ?");
        $stmt->execute([$reset['id']]);
        
        Response::success([], 'Password reset successful! You can now login with your new password.');
        
    } catch (Exception $e) {
        error_log("Reset Password Error: " . $e->getMessage());
        Response::error('Failed to reset password');
    }
}
?>