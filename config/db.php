<?php

require_once __DIR__ . '/../vendor/autoload.php';
use Kreait\Firebase\Factory;

try {
    $factory = (new Factory)
        ->withServiceAccount(__DIR__ . '/firebase_credentials.json')
        ->withDatabaseUri('https://ninjavanph-3f985-default-rtdb.asia-southeast1.firebasedatabase.app/'); // You may need to update this URI from the Firebase Console
    
    $auth = $factory->createAuth();
    
    // Switch to Realtime Database because Firestore requires gRPC which is not supported on standard Windows XAMPP
    $db = $factory->createDatabase();
} catch (\Exception $e) {
    die("
        <div style='font-family:sans-serif;padding:40px;text-align:center;'>
            <h2 style='color:#e8002d;'>⚠ Database Connection Failed</h2>
            <p>Could not connect to <strong>Firebase</strong>.</p>
            <p style='color:#888;'>Ensure Composer is installed and firebase_credentials.json is present.</p>
            <p style='color:#aaa;font-size:12px;'>" . $e->getMessage() . "</p>
        </div>
    ");
}

// ── CSRF helpers ───────────────────────────────────────────────────────────────

/**
 * Returns (and lazily creates) a per-session CSRF token.
 */
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Renders a hidden <input> carrying the current CSRF token.
 * Drop <?= csrf_field() ?> inside every POST form.
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Verifies the POSTed csrf_token against the session token.
 * Call at the top of every POST handler. Aborts with 403 on mismatch.
 */
function csrf_verify(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $posted = $_POST['csrf_token'] ?? '';
    $stored = $_SESSION['csrf_token'] ?? '';
    if (!$stored || !hash_equals($stored, $posted)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;padding:40px;text-align:center;">'
            . '<h2 style="color:#e8002d;">⚠ Invalid Request</h2>'
            . '<p>Security token mismatch. Please go back and try again.</p>'
            . '</div>');
    }
}
?>