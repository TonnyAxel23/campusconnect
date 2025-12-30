<?php
// api/auth/index.php
// Authentication Router

require_once dirname(__DIR__, 2) . '/includes/config/Database.php';
require_once dirname(__DIR__, 2) . '/includes/utils/Response.php';

use CampusConnect\Utils\Response;

// Get action from URL
$path = $_SERVER['PATH_INFO'] ?? '';
$action = basename($path);

// Route to appropriate endpoint
switch ($action) {
    case 'register':
        require __DIR__ . '/register.php';
        break;
        
    case 'login':
        require __DIR__ . '/login.php';
        break;
        
    case 'verify':
        require __DIR__ . '/verify.php';
        break;
        
    case 'logout':
        require __DIR__ . '/logout.php';
        break;
        
    case 'forgot-password':
        require __DIR__ . '/forgot-password.php';
        break;
        
    case 'reset-password':
        require __DIR__ . '/reset-password.php';
        break;
        
    default:
        Response::error('Authentication endpoint not found', 404);
}
?>