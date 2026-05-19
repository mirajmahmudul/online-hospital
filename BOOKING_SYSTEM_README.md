# Online Hospital - Booking & Payment System

## Overview
This is a complete doctor appointment booking system with integrated payment processing for the Online Hospital platform. The system allows patients to book appointments with doctors, wait for doctor confirmation, and then proceed to payment.

## Features

### Patient Side
1. **Browse Doctors** - Search and filter doctors by specialty
2. **Book Appointment** - Select date and time slot for consultation
3. **View Appointment Status** - Track pending, confirmed, and paid appointments
4. **Secure Payment** - Pay after doctor confirms the appointment
5. **Payment History** - View all past payments and transactions

### Doctor Side
1. **View Appointment Requests** - See pending appointment requests from patients
2. **Confirm/Cancel Appointments** - Accept or decline booking requests
3. **Manage Schedule** - View all upcoming confirmed appointments
4. **Track Earnings** - Monitor completed and paid consultations

## Workflow

### 1. Patient Books Appointment
```
Patient Dashboard → Find Specialist → Select Doctor → Choose Date/Time → Submit Request
```
- Appointment status: `pending`
- Payment status: `unpaid`

### 2. Doctor Reviews & Confirms
```
Doctor Dashboard → Appointments → Review Request → Confirm
```
- Appointment status changes to: `confirmed`
- Patient receives notification to proceed with payment

### 3. Patient Makes Payment
```
Patient Dashboard → My Appointments → Pay Now → Complete Payment
```
- Appointment status changes to: `paid`
- Payment status changes to: `paid`
- Transaction ID generated

## File Structure

### Frontend Files
- `patient-dashboard.html` - Patient dashboard with booking modal
- `doctor-dashboard.html` - Doctor dashboard for managing appointments
- `process_payment.html` - Secure payment page
- `appointments.html` - Appointments management page

### Backend API Files
- `api/appointments.php` - Appointment CRUD operations
- `api/payments.php` - Payment processing
- `config/Database.php` - Database connection

### Database Schema
- `database_schema.sql` - SQL schema for appointments and payments tables

## API Endpoints

### Appointments API (`api/appointments.php`)

#### Create Appointment
```
POST api/appointments.php?action=create
Body: {
    "patient_id": 1,
    "doctor_id": 1,
    "patient_name": "John Doe",
    "doctor_name": "Dr. Sarah Chen",
    "specialty": "Cardiology",
    "appointment_date": "2024-10-24",
    "appointment_time": "10:00",
    "amount": 150.00,
    "notes": "Follow-up consultation"
}
```

#### Get Patient Appointments
```
GET api/appointments.php?action=get_patient_appointments&patient_id=1
GET api/appointments.php?action=get_patient_appointments&patient_id=1&status=pending
```

#### Get Doctor Appointments
```
GET api/appointments.php?action=get_doctor_appointments&doctor_id=1
GET api/appointments.php?action=get_doctor_appointments&doctor_id=1&status=confirmed
```

#### Confirm Appointment
```
POST api/appointments.php?action=confirm
Body: { "appointment_id": 1 }
```

#### Cancel Appointment
```
POST api/appointments.php?action=cancel
Body: { "appointment_id": 1 }
```

#### Get Available Slots
```
GET api/appointments.php?action=get_available_slots&doctor_id=1&date=2024-10-24
```

### Payments API (`api/payments.php`)

#### Create Payment
```
POST api/payments.php?action=create
Body: {
    "appointment_id": 1,
    "patient_id": 1,
    "payment_method": "card"
}
```

#### Get Patient Payments
```
GET api/payments.php?action=get_patient_payments&patient_id=1
```

#### Simulate Payment (Demo)
```
POST api/payments.php?action=simulate_payment
Body: { "payment_id": 1 }
```

## Database Tables

### appointments
- `id` - Primary key
- `patient_id` - Foreign key to users
- `doctor_id` - Foreign key to doctors
- `patient_name` - Patient's full name
- `doctor_name` - Doctor's full name
- `specialty` - Doctor's specialty
- `appointment_date` - Date of appointment
- `appointment_time` - Time of appointment
- `duration_minutes` - Duration (default 30)
- `status` - pending/confirmed/completed/cancelled/paid
- `payment_status` - unpaid/paid/refunded
- `amount` - Consultation fee
- `notes` - Patient notes
- `created_at` - Timestamp
- `updated_at` - Timestamp

### payments
- `id` - Primary key
- `appointment_id` - Foreign key to appointments
- `patient_id` - Foreign key to users
- `amount` - Payment amount
- `payment_method` - card/bank_transfer/wallet
- `transaction_id` - Unique transaction reference
- `status` - pending/completed/failed/refunded
- `payment_date` - Payment timestamp
- `created_at` - Timestamp

## Demo Mode

The system includes demo functionality that works without a backend:

1. **Booking Demo**: When you book an appointment in `patient-dashboard.html`, it simulates the API call and shows a success message.

2. **Payment Demo**: The `process_payment.html` page simulates payment processing with a 2-second delay and generates a random transaction ID.

## Testing the System

### Step 1: Setup Database
```sql
-- Run the database_schema.sql file in your MySQL database
mysql -u root -p online_hospital < database_schema.sql
```

### Step 2: Test Patient Booking
1. Open `patient-dashboard.html` in your browser
2. Click "Find a Specialist" in the sidebar
3. Click "Book Consult →" on any doctor
4. Select a date and time slot
5. Enter patient name and submit
6. You'll see a confirmation message

### Step 3: Test Payment Flow
1. After booking, navigate to `process_payment.html`
2. Fill in card details (demo mode accepts any valid format)
3. Click "Pay" button
4. Wait for processing animation
5. See success message with transaction ID

## Integration Notes

### Production Deployment
1. Replace demo functions with actual API calls
2. Implement proper authentication using JWT tokens
3. Add email notifications for appointment confirmations
4. Integrate real payment gateway (Stripe, PayPal, etc.)
5. Add HIPAA compliance measures for healthcare data

### Security Considerations
- Use HTTPS for all API calls
- Implement CSRF protection
- Sanitize all user inputs
- Use prepared statements (already implemented)
- Store sensitive data encrypted
- Implement rate limiting on API endpoints

## Customization

### Changing Consultation Fees
Edit the `getDoctorFeeBySpecialty()` function in `patient-dashboard.html`:
```javascript
function getDoctorFeeBySpecialty(specialty) {
    const feeMap = {
        'Cardiology': 150,
        'Neurology': 200,
        'Pediatrics': 120,
        'Orthopedics': 180
    };
    return feeMap[specialty] || 150;
}
```

### Adding More Time Slots
Edit the `loadAvailableSlots()` function:
```javascript
const demoSlots = ['09:00', '09:30', '10:00', ...];
```

### Styling
All styles are embedded in the HTML files. Key classes:
- `.booking-modal-content` - Booking modal container
- `.time-slot-btn` - Time selection buttons
- `.payment-container` - Payment page wrapper
- `.btn-book` - Book appointment button

## Support

For issues or questions:
1. Check console logs for API errors
2. Verify database connection in `config/Database.php`
3. Ensure all required fields are provided in API calls
4. Check CORS settings if calling API from different domain

---

**Version**: 1.0.0  
**Last Updated**: 2024  
**License**: Proprietary
