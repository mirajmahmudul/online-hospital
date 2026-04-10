<?php
/**
 * ═══════════════════════════════════════════════════════
 * LOGIN API — Online Hospital
 * ═══════════════════════════════════════════════════════
 *
 * POST /api/login.php
 *
 * Expects JSON body:
 *   {
 *     "email":    "user@example.com",
 *     "password": "plaintext_password"
 *   }
 *
 * Returns:
 *   200  { "message": "Login successful.", "role": "doctor"|"patient" }
 *   400  { "message": "Email and password are required." }
 *   401  { "message": "Invalid credentials." }
 *   405  { "message": "Method not allowed." }
 *   500  { "message": "Internal server error." }
 *
 * Security Notes:
 *   - Uses password_verify() against bcrypt-hashed passwords
 *   - Initiates a secure PHP session with httponly, samesite flags
 *   - Stores only uuid and role in $_SESSION (never internal IDs)
 *   - Timing-safe: same error for wrong email vs wrong password
 */

// ── Headers ─────────────────────────────────────────────
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Only accept POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit;
}

// ── Dependencies ────────────────────────────────────────
include_once '../config/Database.php';

// ── Read JSON Input ─────────────────────────────────────
$input = json_decode(file_get_contents("php://input"), true);

$email = isset($input['email']) ? trim($input['email']) : '';
$password = isset($input['password']) ? $input['password'] : '';

// ── Validate Required Fields ────────────────────────────
if (empty($email) || empty($password)) {
    http_response_code(400);
    echo json_encode(["message" => "Email and password are required."]);
    exit;
}

// ── Database Connection ─────────────────────────────────
try {
    $database = new Database();
    $db = $database->getConnection();

    if ($db === null) {
        throw new Exception("Database connection failed.");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Internal server error."]);
    exit;
}

// ── Query User by Email ─────────────────────────────────
try {
    $query = "SELECT uuid, email, password, role 
              FROM users 
              WHERE email = :email 
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // ── Verify Credentials ──────────────────────────────
    // Timing-safe: we always call password_verify even if user
    // doesn't exist (by checking against a dummy hash) to prevent
    // timing-based email enumeration attacks.
    if (!$user) {
        // Dummy verify to keep timing consistent
        password_verify($password, '$2y$10$dummyhashtopreventtimingattackpadding1234567890');
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials."]);
        exit;
    }

    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(["message" => "Invalid credentials."]);
        exit;
    }

    // ── Start Secure Session ────────────────────────────
    // Configure session cookie parameters BEFORE session_start()
    session_set_cookie_params([
        'lifetime' => 0,           // Session cookie (expires on browser close)
        'path' => '/',
        'secure' => false,       // Set to true in production with HTTPS
        'httponly' => true,        // Prevent JavaScript access to session cookie
        'samesite' => 'Strict',    // CSRF protection
    ]);

    session_start();

    // Regenerate session ID to prevent session fixation attacks
    session_regenerate_id(true);

    // Store only non-sensitive identifiers in the session
    $_SESSION['uuid'] = $user['uuid'];
    $_SESSION['role'] = $user['role'];

    // ── Success Response ────────────────────────────────
    http_response_code(200);
    echo json_encode([
        "message" => "Login successful.",
        "role" => $user['role'],
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Internal server error."]);
    exit;
}
?>