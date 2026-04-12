<?php
/**
 * DOCTOR SERVICES (Gigs) API — Online Hospital
 * Session key: $_SESSION['uuid']
 *
 * GET  /api/services.php           → List doctor's services
 * POST /api/services.php           → Create a new service
 *
 * Auto-creates the `doctor_services` table if it doesn't exist.
 */

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ── Session ────────────────────────────────────────
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => false,
    'httponly'  => true,
    'samesite' => 'Strict',
]);
session_start();

// ── Auth Guard ─────────────────────────────────────
if (!isset($_SESSION['uuid']) || empty($_SESSION['uuid']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(401);
    echo json_encode(["message" => "Unauthorized."]);
    exit;
}

$doctor_uuid = $_SESSION['uuid'];

// ── Database Connection ────────────────────────────
include_once '../config/Database.php';

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


// ── Auto-Create Table ──────────────────────────────
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS doctor_services (
            id              INT AUTO_INCREMENT PRIMARY KEY,
            doctor_uuid     VARCHAR(36)    NOT NULL,
            title           VARCHAR(255)   NOT NULL,
            description     TEXT           DEFAULT NULL,
            duration        INT            NOT NULL DEFAULT 30,
            price           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
            created_at      TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_doctor (doctor_uuid)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (PDOException $e) {
    // Table might already exist — that's fine
}


// ═══════════════════════════════════════════════════
// ROUTE: GET — Fetch all services for this doctor
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $db->prepare("
            SELECT id, title, description, duration, price, created_at
            FROM doctor_services
            WHERE doctor_uuid = :uuid
            ORDER BY created_at DESC
        ");
        $stmt->bindParam(':uuid', $doctor_uuid, PDO::PARAM_STR);
        $stmt->execute();

        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Normalize numeric types
        foreach ($services as &$svc) {
            $svc['id']       = intval($svc['id']);
            $svc['duration'] = intval($svc['duration']);
            $svc['price']    = floatval($svc['price']);
        }
        unset($svc);

        http_response_code(200);
        echo json_encode(["services" => $services]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to fetch services."]);
    }
    exit;
}


// ═══════════════════════════════════════════════════
// ROUTE: POST — Create a new service (Gig)
// ═══════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Parse JSON body
    $input = json_decode(file_get_contents("php://input"), true);

    if (!$input) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid JSON payload."]);
        exit;
    }

    // ── Validate required fields ──
    $title       = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $duration    = intval($input['duration'] ?? 0);
    $price       = floatval($input['price'] ?? 0);

    $errors = [];
    if (empty($title))           $errors[] = "Title is required.";
    if (strlen($title) > 255)    $errors[] = "Title must be under 255 characters.";
    if ($duration < 5)           $errors[] = "Duration must be at least 5 minutes.";
    if ($duration > 480)         $errors[] = "Duration cannot exceed 480 minutes.";
    if ($price < 0)              $errors[] = "Price cannot be negative.";
    if ($price > 99999)          $errors[] = "Price seems unreasonably high.";

    if (!empty($errors)) {
        http_response_code(422);
        echo json_encode(["message" => implode(' ', $errors), "errors" => $errors]);
        exit;
    }

    // ── Insert ──
    try {
        $stmt = $db->prepare("
            INSERT INTO doctor_services (doctor_uuid, title, description, duration, price)
            VALUES (:uuid, :title, :description, :duration, :price)
        ");
        $stmt->bindParam(':uuid',        $doctor_uuid, PDO::PARAM_STR);
        $stmt->bindParam(':title',       $title,       PDO::PARAM_STR);
        $stmt->bindParam(':description', $description, PDO::PARAM_STR);
        $stmt->bindParam(':duration',    $duration,    PDO::PARAM_INT);
        $stmt->bindParam(':price',       $price,       PDO::PARAM_STR);
        $stmt->execute();

        $newId = $db->lastInsertId();

        http_response_code(201);
        echo json_encode([
            "message" => "Service created successfully.",
            "service" => [
                "id"          => intval($newId),
                "title"       => $title,
                "description" => $description,
                "duration"    => $duration,
                "price"       => $price,
            ]
        ]);

    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["message" => "Failed to create service."]);
    }
    exit;
}


// ── Unsupported Method ─────────────────────────────
http_response_code(405);
echo json_encode(["message" => "Method not allowed."]);
exit;
?>
