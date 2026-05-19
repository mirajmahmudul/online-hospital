<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

include_once '../config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

try {
    switch ($action) {
        case 'create':
            createAppointment($db);
            break;
        case 'get_patient_appointments':
            getPatientAppointments($db);
            break;
        case 'get_doctor_appointments':
            getDoctorAppointments($db);
            break;
        case 'confirm':
            confirmAppointment($db);
            break;
        case 'cancel':
            cancelAppointment($db);
            break;
        case 'get_available_slots':
            getAvailableSlots($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function createAppointment($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (empty($data['patient_id']) || empty($data['doctor_id']) || 
        empty($data['appointment_date']) || empty($data['appointment_time'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    // Check if slot is available
    $checkQuery = "SELECT id FROM appointments 
                   WHERE doctor_id = :doctor_id 
                   AND appointment_date = :appointment_date 
                   AND appointment_time = :appointment_time 
                   AND status IN ('pending', 'confirmed')";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':doctor_id', $data['doctor_id']);
    $checkStmt->bindParam(':appointment_date', $data['appointment_date']);
    $checkStmt->bindParam(':appointment_time', $data['appointment_time']);
    $checkStmt->execute();
    
    if ($checkStmt->rowCount() > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'This time slot is already booked']);
        return;
    }

    // Get doctor details for amount calculation
    $doctorQuery = "SELECT consultation_fee FROM doctors WHERE id = :doctor_id";
    $doctorStmt = $db->prepare($doctorQuery);
    $doctorStmt->bindParam(':doctor_id', $data['doctor_id']);
    $doctorStmt->execute();
    $doctor = $doctorStmt->fetch(PDO::FETCH_ASSOC);
    
    $amount = isset($data['amount']) ? $data['amount'] : ($doctor['consultation_fee'] ?? 150.00);

    // Insert appointment
    $query = "INSERT INTO appointments 
              (patient_id, doctor_id, patient_name, doctor_name, specialty, 
               appointment_date, appointment_time, duration_minutes, status, 
               payment_status, amount, notes) 
              VALUES 
              (:patient_id, :doctor_id, :patient_name, :doctor_name, :specialty,
               :appointment_date, :appointment_time, :duration_minutes, 'pending', 
               'unpaid', :amount, :notes)";

    $stmt = $db->prepare($query);

    $patient_name = htmlspecialchars(strip_tags($data['patient_name']));
    $doctor_name = htmlspecialchars(strip_tags($data['doctor_name']));
    $specialty = htmlspecialchars(strip_tags($data['specialty']));
    $duration = isset($data['duration_minutes']) ? $data['duration_minutes'] : 30;
    $notes = isset($data['notes']) ? htmlspecialchars(strip_tags($data['notes'])) : '';

    $stmt->bindParam(':patient_id', $data['patient_id']);
    $stmt->bindParam(':doctor_id', $data['doctor_id']);
    $stmt->bindParam(':patient_name', $patient_name);
    $stmt->bindParam(':doctor_name', $doctor_name);
    $stmt->bindParam(':specialty', $specialty);
    $stmt->bindParam(':appointment_date', $data['appointment_date']);
    $stmt->bindParam(':appointment_time', $data['appointment_time']);
    $stmt->bindParam(':duration_minutes', $duration);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':notes', $notes);

    if ($stmt->execute()) {
        $appointment_id = $db->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment request sent successfully',
            'appointment_id' => $appointment_id,
            'data' => [
                'id' => $appointment_id,
                'patient_name' => $patient_name,
                'doctor_name' => $doctor_name,
                'specialty' => $specialty,
                'date' => $data['appointment_date'],
                'time' => $data['appointment_time'],
                'status' => 'pending',
                'amount' => $amount
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create appointment']);
    }
}

function getPatientAppointments($db) {
    if (!isset($_GET['patient_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Patient ID required']);
        return;
    }

    $patient_id = $_GET['patient_id'];
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    $query = "SELECT a.*, p.transaction_id, p.payment_method
              FROM appointments a
              LEFT JOIN payments p ON a.id = p.appointment_id
              WHERE a.patient_id = :patient_id";
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = :status";
    }
    
    $query .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter);
    }
    
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $appointments]);
}

function getDoctorAppointments($db) {
    if (!isset($_GET['doctor_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Doctor ID required']);
        return;
    }

    $doctor_id = $_GET['doctor_id'];
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    $query = "SELECT a.*, p.transaction_id, p.payment_method, p.status as payment_status
              FROM appointments a
              LEFT JOIN payments p ON a.id = p.appointment_id
              WHERE a.doctor_id = :doctor_id";
    
    if (!empty($status_filter)) {
        $query .= " AND a.status = :status";
    }
    
    $query .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id);
    if (!empty($status_filter)) {
        $stmt->bindParam(':status', $status_filter);
    }
    
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $appointments]);
}

function confirmAppointment($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['appointment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        return;
    }

    $query = "UPDATE appointments 
              SET status = 'confirmed', updated_at = CURRENT_TIMESTAMP 
              WHERE id = :appointment_id AND status = 'pending'";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $data['appointment_id']);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Appointment confirmed successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found or already processed']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment']);
    }
}

function cancelAppointment($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['appointment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        return;
    }

    $query = "UPDATE appointments 
              SET status = 'cancelled', updated_at = CURRENT_TIMESTAMP 
              WHERE id = :appointment_id AND status IN ('pending', 'confirmed')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':appointment_id', $data['appointment_id']);

    if ($stmt->execute()) {
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Appointment cancelled successfully']);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Appointment not found or already processed']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel appointment']);
    }
}

function getAvailableSlots($db) {
    if (!isset($_GET['doctor_id']) || !isset($_GET['date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Doctor ID and date required']);
        return;
    }

    $doctor_id = $_GET['doctor_id'];
    $date = $_GET['date'];

    // Define working hours (9 AM to 6 PM, every 30 minutes)
    $working_hours = [];
    for ($hour = 9; $hour < 18; $hour++) {
        $working_hours[] = sprintf('%02d:00', $hour);
        $working_hours[] = sprintf('%02d:30', $hour);
    }

    // Get booked slots for the date
    $query = "SELECT appointment_time FROM appointments 
              WHERE doctor_id = :doctor_id 
              AND appointment_date = :date 
              AND status IN ('pending', 'confirmed')";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id);
    $stmt->bindParam(':date', $date);
    $stmt->execute();
    
    $booked_slots = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Filter out booked slots
    $available_slots = array_diff($working_hours, $booked_slots);

    echo json_encode([
        'success' => true, 
        'date' => $date,
        'available_slots' => array_values($available_slots)
    ]);
}
?>
