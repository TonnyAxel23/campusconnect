<?php
// public/index.php
// Front controller for CampusConnect

session_start();

// Load configuration
require_once dirname(__DIR__) . '/config/app.php';

// Define base path
define('BASE_PATH', dirname(__DIR__));
define('PUBLIC_PATH', __DIR__);

// Simple routing
$request = $_SERVER['REQUEST_URI'];
$request = str_replace('/campusconnect/public', '', $request); // Adjust based on your setup
$request = trim($request, '/');

// Default route
if (empty($request)) {
    $request = 'auth/login';
}

// Split the request
$parts = explode('/', $request);
$page = $parts[0];
$action = $parts[1] ?? '';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

// Define accessible pages without login
$publicPages = ['auth', 'api'];

// Redirect to login if not authenticated
if (!$isLoggedIn && !in_array($page, $publicPages)) {
    header('Location: /campusconnect/public/auth/login');
    exit();
}

// Load the requested page
$pageFile = PUBLIC_PATH . "/views/{$page}/" . ($action ?: 'index') . '.html';

if (file_exists($pageFile)) {
    // Load header
    include PUBLIC_PATH . '/views/partials/header.html';
    
    // Load page content
    include $pageFile;
    
    // Load footer
    include PUBLIC_PATH . '/views/partials/footer.html';
} else {
    // 404 Not Found
    http_response_code(404);
    include PUBLIC_PATH . '/views/errors/404.html';
}
?>