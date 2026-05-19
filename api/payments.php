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
            createPayment($db);
            break;
        case 'get_patient_payments':
            getPatientPayments($db);
            break;
        case 'simulate_payment':
            simulatePayment($db);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}

function createPayment($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    // Validate required fields
    if (empty($data['appointment_id']) || empty($data['patient_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }

    // Get appointment details
    $apptQuery = "SELECT id, amount, status FROM appointments WHERE id = :appointment_id";
    $apptStmt = $db->prepare($apptQuery);
    $apptStmt->bindParam(':appointment_id', $data['appointment_id']);
    $apptStmt->execute();
    
    if ($apptStmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }
    
    $appointment = $apptStmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if already paid
    if ($appointment['status'] === 'paid') {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Appointment already paid']);
        return;
    }
    
    // Check if confirmed
    if ($appointment['status'] !== 'confirmed') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please wait for doctor to confirm the appointment before payment']);
        return;
    }

    $amount = $appointment['amount'];
    $payment_method = isset($data['payment_method']) ? $data['payment_method'] : 'card';

    // Insert payment record
    $query = "INSERT INTO payments 
              (appointment_id, patient_id, amount, payment_method, status) 
              VALUES 
              (:appointment_id, :patient_id, :amount, :payment_method, 'pending')";

    $stmt = $db->prepare($query);

    $stmt->bindParam(':appointment_id', $data['appointment_id']);
    $stmt->bindParam(':patient_id', $data['patient_id']);
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':payment_method', $payment_method);

    if ($stmt->execute()) {
        $payment_id = $db->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Payment initiated',
            'payment_id' => $payment_id,
            'amount' => $amount,
            'payment_url' => 'process_payment.html?payment_id=' . $payment_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create payment record']);
    }
}

function getPatientPayments($db) {
    if (!isset($_GET['patient_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Patient ID required']);
        return;
    }

    $patient_id = $_GET['patient_id'];

    $query = "SELECT p.*, a.doctor_name, a.appointment_date, a.appointment_time, a.specialty
              FROM payments p
              JOIN appointments a ON p.appointment_id = a.id
              WHERE p.patient_id = :patient_id
              ORDER BY p.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id);
    $stmt->execute();
    
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'data' => $payments]);
}

function simulatePayment($db) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        return;
    }

    $data = json_decode(file_get_contents("php://input"), true);

    if (empty($data['payment_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment ID required']);
        return;
    }

    // Generate demo transaction ID
    $transaction_id = 'TXN' . strtoupper(substr(uniqid(), -8)) . rand(1000, 9999);

    // Update payment status
    $paymentQuery = "UPDATE payments 
                     SET status = 'completed', 
                         transaction_id = :transaction_id,
                         payment_date = CURRENT_TIMESTAMP
                     WHERE id = :payment_id AND status = 'pending'";

    $paymentStmt = $db->prepare($paymentQuery);
    $paymentStmt->bindParam(':transaction_id', $transaction_id);
    $paymentStmt->bindParam(':payment_id', $data['payment_id']);

    if ($paymentStmt->execute() && $paymentStmt->rowCount() > 0) {
        // Get payment details
        $paymentId = $data['payment_id'];
        $detailsQuery = "SELECT appointment_id, amount FROM payments WHERE id = :payment_id";
        $detailsStmt = $db->prepare($detailsQuery);
        $detailsStmt->bindParam(':payment_id', $paymentId);
        $detailsStmt->execute();
        $payment = $detailsStmt->fetch(PDO::FETCH_ASSOC);

        // Update appointment status to paid
        $apptQuery = "UPDATE appointments 
                      SET status = 'paid', 
                          payment_status = 'paid',
                          updated_at = CURRENT_TIMESTAMP
                      WHERE id = :appointment_id";
        
        $apptStmt = $db->prepare($apptQuery);
        $apptStmt->bindParam(':appointment_id', $payment['appointment_id']);
        $apptStmt->execute();

        echo json_encode([
            'success' => true, 
            'message' => 'Payment completed successfully',
            'transaction_id' => $transaction_id,
            'amount' => $payment['amount'],
            'receipt_url' => 'receipt.html?payment_id=' . $data['payment_id']
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Payment not found or already processed']);
    }
}
?>
