<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Retrieve parameters from the URL
    $room = isset($_GET['room']) ? $_GET['room'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $timeslot = isset($_GET['timeslot']) ? $_GET['timeslot'] : '';

    // Validate input parameters
    if (empty($room) || empty($date) || empty($timeslot)) {
        throw new Exception('Missing parameters.');
    }

    // Prepare and execute the SQL statement to check room availability
    $stmt = $pdo->prepare('SELECT is_blocked FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
    $stmt->execute([':room' => $room, ':date' => $date, ':timeslot' => $timeslot]);
    $is_blocked = $stmt->fetchColumn();

    // Output the result as JSON
    header('Content-Type: application/json');
    echo json_encode(['is_blocked' => (bool)$is_blocked]);
    exit;
} catch (Exception $e) {
    // Return an error message
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}
?>
