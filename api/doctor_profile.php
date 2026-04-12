<?php
/**
 * DOCTOR PROFILE API — Online Hospital
 * Aligned to schema: users + doctor_profiles (no specialties table)
 * Session keys: $_SESSION['uuid'], $_SESSION['role']
 *
 * GET /api/doctor_profile.php
 *
 * Returns:
 *   200  { name, email, medical_license, specialty, verification_status,
 *          consultation_fee, rating, review_count, bio }
 *   401  { message: "Unauthorized." }
 *   404  { message: "Doctor profile not found." }
 *   500  { message: "Internal server error." }
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly'  => true,
    'samesite' => 'Strict',
]);
session_start();

// Auth check — uses $_SESSION['uuid'] and $_SESSION['role']
if (!isset($_SESSION['uuid']) || empty($_SESSION['uuid']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit;
}

$uuid = $_SESSION['uuid'];

include_once '../config/Database.php';

// Database connection
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

// Gracefully ensure new media columns exist to prevent SELECT query fatal crash
try { $db->exec("ALTER TABLE doctor_profiles ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try { $db->exec("ALTER TABLE doctor_profiles ADD COLUMN certificate_url VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}

// Query — direct JOIN, no specialties tables
try {
    $query = "SELECT 
                u.name,
                u.email,
                dp.medical_license,
                dp.specialty,
                dp.verification_status,
                dp.consultation_fee,
                dp.rating,
                dp.review_count,
                dp.bio,
                dp.avatar_url
              FROM users u
              JOIN doctor_profiles dp ON u.id = dp.user_id
              WHERE u.uuid = :uuid AND u.role = 'doctor'
              LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':uuid', $uuid, PDO::PARAM_STR);
    $stmt->execute();

    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        http_response_code(404);
        echo json_encode(["message" => "Doctor profile not found."]);
        exit;
    }

    // ── Profile Completion Calculation ─────────────
    $completion = 0;
    if (!empty($doctor['name']))                          $completion += 20;
    if (!empty($doctor['specialty']))                     $completion += 20;
    if (!empty($doctor['medical_license']))               $completion += 20;
    if (floatval($doctor['consultation_fee']) > 0)        $completion += 20;
    if ($doctor['verification_status'] === 'verified')    $completion += 20;

    http_response_code(200);
    echo json_encode([
        "name"                => $doctor['name'],
        "email"               => $doctor['email'],
        "medical_license"     => $doctor['medical_license'],
        "specialty"           => $doctor['specialty'] ?? 'Not specified',
        "verification_status" => $doctor['verification_status'],
        "consultation_fee"    => floatval($doctor['consultation_fee']),
        "rating"              => floatval($doctor['rating']),
        "review_count"        => intval($doctor['review_count']),
        "bio"                 => $doctor['bio'] ?? '',
        "avatar_url"          => $doctor['avatar_url'] ?? null,
        "profile_completion"  => $completion,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Internal server error."]);
    exit;
}
?>
