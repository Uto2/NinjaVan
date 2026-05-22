<?php

$conn = new mysqli("localhost", "root", "nigger", "NinjaVanPH");

if($conn->connect_error){
    die("
        <div style='font-family:sans-serif;padding:40px;text-align:center;'>
            <h2 style='color:#e8002d;'>⚠ Database Connection Failed</h2>
            <p>Could not connect to <strong>NinjaVanPH</strong>.</p>
            <p style='color:#888;'>Make sure XAMPP MySQL is running and you imported <code>ninjavan_new.sql</code></p>
            <p style='color:#aaa;font-size:12px;'>" . $conn->connect_error . "</p>
        </div>
    ");
}

$conn->set_charset("utf8mb4");

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