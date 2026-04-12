<?php
/**
 * MEDIA UPLOAD API — Online Hospital
 * Generates unique names and securely accepts Avatar / Certificate uploads.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Session check
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly'  => true,
    'samesite' => 'Strict',
]);
session_start();

if (!isset($_SESSION['uuid']) || empty($_SESSION['uuid']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit;
}

$doctor_uuid = $_SESSION['uuid'];

// DB Connection
include_once '../config/Database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    if ($db === null) throw new Exception("Database connection failed.");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["message" => "Internal server error."]);
    exit;
}

// Auto-Migrations (Ensure columns exist safely)
try { $db->exec("ALTER TABLE doctor_profiles ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}
try { $db->exec("ALTER TABLE doctor_profiles ADD COLUMN certificate_url VARCHAR(255) DEFAULT NULL"); } catch (PDOException $e) {}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed."]);
    exit;
}

$upload_type = $_POST['upload_type'] ?? '';
if (!in_array($upload_type, ['avatar', 'certificate'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid upload type."]);
    exit;
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["message" => "No valid file uploaded."]);
    exit;
}

$file = $_FILES['file'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(["message" => "File size exceeds 5MB limit."]);
    exit;
}

// Validate mime type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
];

if ($upload_type === 'certificate') {
    $allowed_mimes['application/pdf'] = 'pdf';
}

if (!array_key_exists($mime_type, $allowed_mimes)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid file format. Please upload a JPG, PNG, WEBP, or PDF."]);
    exit;
}

$ext = $allowed_mimes[$mime_type];
$unique_filename = $upload_type . '_' . $doctor_uuid . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target_dir = '../assets/uploads/';

if (!is_dir($target_dir)) {
    mkdir($target_dir, 0755, true);
}

$target_path = $target_dir . $unique_filename;

if (!move_uploaded_file($file['tmp_name'], $target_path)) {
    http_response_code(500);
    echo json_encode(["message" => "Failed to save uploaded file."]);
    exit;
}

// Update Database
$public_url = 'assets/uploads/' . $unique_filename;
$column = ($upload_type === 'avatar') ? 'avatar_url' : 'certificate_url';

try {
    $stmt = $db->prepare("
        UPDATE doctor_profiles 
        SET {$column} = :url 
        WHERE user_id = (SELECT id FROM users WHERE uuid = :uuid LIMIT 1)
    ");
    $stmt->bindParam(':url', $public_url, PDO::PARAM_STR);
    $stmt->bindParam(':uuid', $doctor_uuid, PDO::PARAM_STR);
    $stmt->execute();
    
    http_response_code(200);
    echo json_encode([
        "message" => ucfirst($upload_type) . " uploaded successfully.",
        "url"     => $public_url
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["message" => "Database update failed."]);
}
?>
