<?php
// api/auth/forgot-password.php
// Forgot Password Endpoint

require_once dirname(__DIR__, 2) . '/includes/config/Database.php';
require_once dirname(__DIR__, 2) . '/includes/utils/Response.php';

use CampusConnect\Utils\Response;

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('Method not allowed', 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';

if (empty($email)) {
    Response::error('Email is required', 400);
}

try {
    $db = \CampusConnect\Config\Database::getInstance()->getConnection();
    
    // Check if user exists
    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE campus_email = ? AND is_active = 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if (!$user) {
        // Don't reveal if user exists or not (security)
        Response::success([], 'If your email is registered, you will receive a password reset link');
    }
    
    // Generate reset token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Delete old reset tokens for this user
    $stmt = $db->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // Insert new reset token
    $stmt = $db->prepare("
        INSERT INTO password_resets (user_id, token, expires_at) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$user['id'], $token, $expiresAt]);
    
    // Generate reset link
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/campusconnect/public/auth/reset-password?token=" . $token;
    
    // In a real application, send email here
    // For development, return the link
    $response = [
        'message' => 'Password reset email sent',
        'reset_link' => $resetLink, // Remove in production
        'note' => 'In production, this link would be sent via email'
    ];
    
    Response::success($response, 'Password reset instructions sent to your email');
    
} catch (Exception $e) {
    error_log("Forgot Password Error: " . $e->getMessage());
    Response::error('Failed to process password reset request');
}
?>