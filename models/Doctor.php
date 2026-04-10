<?php
class Doctor
{
    private $conn;

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAvailableDoctors()
    {
        // We only select the secure UUID for the frontend, NEVER the internal database ID
        $query = "SELECT 
                    u.uuid, 
                    u.email, 
                    u.last_active,
                    dp.consultation_fee, 
                    dp.bio,
                    GROUP_CONCAT(s.name SEPARATOR ', ') as specialties
                  FROM users u
                  JOIN doctor_profiles dp ON u.id = dp.user_id
                  LEFT JOIN doctor_specialties ds ON dp.id = ds.doctor_id
                  LEFT JOIN specialties s ON ds.specialty_id = s.id
                  WHERE u.role = 'doctor' AND dp.verification_status = 'verified'
                  GROUP BY u.id";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }
}
?>