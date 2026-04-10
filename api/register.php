<?php
/**
 * REGISTER API — Online Hospital
 * Aligned to schema: users + doctor_profiles (no specialties table)
 * Session keys: $_SESSION['uuid'], $_SESSION['role']
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit;
}

include_once '../config/Database.php';

$input = json_decode(file_get_contents("php://input"), true);

$name     = isset($input['name'])     ? trim($input['name'])     : '';
$email    = isset($input['email'])    ? trim($input['email'])    : '';
$password = isset($input['password']) ? $input['password']       : '';
$role     = isset($input['role'])     ? trim($input['role'])     : '';

// Doctor-specific: frontend sends "license_number", we map it to DB column "medical_license"
$medical_license = isset($input['license_number']) ? trim($input['license_number']) : '';
$specialty       = isset($input['specialty'])      ? trim($input['specialty'])      : '';

// Validation
if (empty($name) || empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($password) || strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["message" => "Valid name, email, and password (8+ chars) are required."]);
    exit;
}

if (!in_array($role, ['patient', 'doctor'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid role. Must be 'patient' or 'doctor'."]);
    exit;
}

if ($role === 'doctor' && (empty($medical_license) || empty($specialty))) {
    http_response_code(400);
    echo json_encode(["message" => "Medical license and specialty are required for doctors."]);
    exit;
}

// Database
try {
    $database = new Database();
    $db = $database->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database connection failed."]);
    exit;
}

// Duplicate check
try {
    $check = $db->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $check->bindParam(':email', $email, PDO::PARAM_STR);
    $check->execute();

    if ($check->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(["message" => "An account with this email already exists."]);
        exit;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Internal server error."]);
    exit;
}

// Hash & UUID
$hashed_password = password_hash($password, PASSWORD_BCRYPT);
$uuid = bin2hex(random_bytes(16));
$uuid = sprintf('%s-%s-4%s-%s%s-%s',
    substr($uuid, 0, 8), substr($uuid, 8, 4), substr($uuid, 13, 3),
    dechex(8 | (hexdec(substr($uuid, 16, 1)) & 3)), substr($uuid, 17, 3),
    substr($uuid, 20, 12)
);

// Insert
try {
    $db->beginTransaction();

    // 1. users table — matches schema: [id, uuid, name, email, password, role, created_at]
    $userStmt = $db->prepare(
        "INSERT INTO users (uuid, name, email, password, role)
         VALUES (:uuid, :name, :email, :password, :role)"
    );
    $userStmt->bindParam(':uuid',     $uuid,            PDO::PARAM_STR);
    $userStmt->bindParam(':name',     $name,            PDO::PARAM_STR);
    $userStmt->bindParam(':email',    $email,           PDO::PARAM_STR);
    $userStmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
    $userStmt->bindParam(':role',     $role,            PDO::PARAM_STR);
    $userStmt->execute();

    $userId = $db->lastInsertId();

    // 2. doctor_profiles — matches schema: [id, user_id, medical_license, specialty, verification_status, consultation_fee, rating, review_count, bio]
    if ($role === 'doctor') {
        $profileStmt = $db->prepare(
            "INSERT INTO doctor_profiles (user_id, medical_license, specialty, verification_status, consultation_fee, rating, review_count, bio)
             VALUES (:user_id, :medical_license, :specialty, 'pending', 0.00, 5.00, 0, '')"
        );
        $profileStmt->bindParam(':user_id',         $userId,          PDO::PARAM_INT);
        $profileStmt->bindParam(':medical_license',  $medical_license, PDO::PARAM_STR);
        $profileStmt->bindParam(':specialty',        $specialty,       PDO::PARAM_STR);
        $profileStmt->execute();
    }

    $db->commit();

    // Session — consistent keys: 'uuid' and 'role'
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
    session_start();
    session_regenerate_id(true);

    $_SESSION['uuid'] = $uuid;
    $_SESSION['role'] = $role;

    http_response_code(201);
    echo json_encode(["message" => "Registration successful.", "role" => $role]);

} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(["message" => "Registration failed: " . $e->getMessage()]);
    exit;
}
?>