<?php
// Required headers to allow your frontend to talk to this backend
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

// Include the database connection and the doctor model you just built
include_once '../config/Database.php';
include_once '../models/Doctor.php';

// Instantiate the database connection
$database = new Database();
$db = $database->getConnection();

// Instantiate the Doctor object
$doctor = new Doctor($db);

// Query the database using the model
$stmt = $doctor->getAvailableDoctors();
$num = $stmt->rowCount();

// If we find verified doctors, package them up
if ($num > 0) {
    $doctors_arr = array();
    $doctors_arr["records"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);

        $doctor_item = array(
            "uuid" => $uuid,
            "email" => $email,
            "consultation_fee" => $consultation_fee,
            "bio" => html_entity_decode($bio),
            "specialties" => $specialties,
            "last_active" => $last_active
        );

        array_push($doctors_arr["records"], $doctor_item);
    }

    // Set response code 200 (OK) and output the JSON data
    http_response_code(200);
    echo json_encode($doctors_arr);
} else {
    // Set response code 404 (Not found) if no doctors are verified yet
    http_response_code(404);
    echo json_encode(
        array("message" => "No verified doctors found.")
    );
}
?>