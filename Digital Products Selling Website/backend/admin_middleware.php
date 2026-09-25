<?php
/**
 * Admin Authentication Middleware
 * Verifies user is logged in AND has admin role
 * Include this at the top of all admin endpoints
 */

function require_admin_auth()
{
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    
    require_once __DIR__ . '/../db.php';
    
    $stmt = $pdo->prepare('SELECT id, name, email, role FROM users WHERE id = ? AND role = "admin" LIMIT 1');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        jsonResponse(['error' => 'Unauthorized - admin access required'], 403);
    }
    
    return $user;
}

// Note: require_auth() checks for any authenticated user
// require_admin_auth() checks for admin role specifically
