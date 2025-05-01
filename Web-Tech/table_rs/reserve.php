<?php
header('Content-Type: application/json');

// Configuration
$config = [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_password' => '',
    'db_name' => 'table_reservation',
    'table_name' => 'reservations',
    'max_reservations_per_time' => 5,
    'opening_hours' => [
        0 => ['11:00-14:30', '18:00-22:00'], // Sunday
        1 => [],                              // Monday (closed)
        2 => ['11:00-14:30', '18:00-22:00'], // Tuesday
        3 => ['11:00-14:30', '18:00-22:00'], // Wednesday
        4 => ['11:00-14:30', '18:00-22:00'], // Thursday
        5 => ['11:00-14:30', '18:00-22:00'], // Friday
        6 => ['11:00-14:30', '18:00-22:00'], // Saturday
    ]
];

// Send response and exit
function sendResponse($success, $message, $data = []) {
    echo json_encode(array_merge([
        'success' => $success,
        'message' => $message
    ], $data));
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Sanitize input
$required_fields = ['name', 'email', 'phone', 'date', 'time', 'guests'];
$input = [];

foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        sendResponse(false, "Missing required field: $field");
    }
    $input[$field] = trim($_POST[$field]);
}

$input['seating'] = trim($_POST['seating'] ?? 'no_preference');
$input['special_requests'] = trim($_POST['special_requests'] ?? '');

// Validate name
if (!preg_match('/^[A-Za-z\s]{2,50}$/', $input['name'])) {
    sendResponse(false, 'Please enter a valid name (2-50 characters, letters only)');
}

// Validate email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address');
}

// Validate phone
if (!preg_match('/^[\d\s\-()]{10,15}$/', $input['phone'])) {
    sendResponse(false, 'Please enter a valid phone number');
}

// Validate date
$current_date = date('Y-m-d');
$max_date = date('Y-m-d', strtotime('+30 days'));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $input['date'])) {
    sendResponse(false, 'Invalid date format');
}

if ($input['date'] < $current_date) {
    sendResponse(false, 'Cannot make reservations for past dates');
}

if ($input['date'] > $max_date) {
    sendResponse(false, 'Reservations can only be made up to 30 days in advance');
}

// Validate open day
$day_of_week = date('w', strtotime($input['date']));
if (empty($config['opening_hours'][$day_of_week])) {
    sendResponse(false, 'We are closed on the selected day');
}

// Validate time
if (!preg_match('/^\d{2}:\d{2}$/', $input['time'])) {
    sendResponse(false, 'Invalid time format');
}

$valid_time = false;
foreach ($config['opening_hours'][$day_of_week] as $range) {
    [$open, $close] = explode('-', $range);
    if ($input['time'] >= $open && $input['time'] <= $close) {
        $valid_time = true;
        break;
    }
}

if (!$valid_time) {
    sendResponse(false, 'The selected time is outside our opening hours');
}

// Validate guests
$input['guests'] = (int)$input['guests'];
if ($input['guests'] < 1 || $input['guests'] > 8) {
    sendResponse(false, 'Please select a valid number of guests (1–8)');
}

// Validate seating
$valid_seating = ['no_preference', 'indoor', 'outdoor', 'bar', 'private'];
if (!in_array($input['seating'], $valid_seating)) {
    sendResponse(false, 'Invalid seating preference');
}

if ($input['seating'] === 'private' && $input['guests'] < 6) {
    sendResponse(false, 'Private rooms are only available for 6 or more guests');
}

// DB connection
try {
    $conn = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_password'],
        $config['db_name']
    );

    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS " . $config['table_name'] . " (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100),
        email VARCHAR(100),
        phone VARCHAR(20),
        date DATE,
        time TIME,
        guests INT,
        seating VARCHAR(50),
        special_requests TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";

    if (!$conn->query($sql)) {
        throw new Exception("Table creation error: " . $conn->error);
    }

    // Check availability
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM " . $config['table_name'] . " WHERE date = ? AND time = ?");
    $stmt->bind_param("ss", $input['date'], $input['time']);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if ($row['count'] >= $config['max_reservations_per_time']) {
        sendResponse(false, 'We are fully booked at this time. Please choose another slot.');
    }

    // Insert reservation
    $stmt = $conn->prepare("INSERT INTO " . $config['table_name'] . " 
        (name, email, phone, date, time, guests, seating, special_requests) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

    $stmt->bind_param("sssssiis",
        $input['name'],
        $input['email'],
        $input['phone'],
        $input['date'],
        $input['time'],
        $input['guests'],
        $input['seating'],
        $input['special_requests']
    );

    if (!$stmt->execute()) {
        throw new Exception("Error saving reservation: " . $stmt->error);
    }

    $stmt->close();
    $conn->close();

    sendResponse(true, 'Your reservation has been confirmed!', [
        'name' => $input['name'],
        'date' => $input['date'],
        'time' => $input['time'],
        'guests' => $input['guests'],
        'seating' => $input['seating']
    ]);

} catch (Exception $e) {
    sendResponse(false, 'An error occurred: ' . $e->getMessage());
}
