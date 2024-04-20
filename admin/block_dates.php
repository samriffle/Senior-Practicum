<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Retrieve parameters from the URL
    $selectedRooms = isset($_GET['rooms']) ? explode(',', $_GET['rooms']) : [];
    $fromDate = isset($_GET['fromDate']) ? $_GET['fromDate'] : '';
    $toDate = isset($_GET['toDate']) ? $_GET['toDate'] : '';
    $selectedTimeslots = isset($_GET['timeslots']) ? explode(',', $_GET['timeslots']) : [];

    // Validate input parameters
    if (empty($selectedRooms) || empty($fromDate) || empty($toDate) || empty($selectedTimeslots) || strtotime($fromDate) > strtotime($toDate)) {
        throw new Exception('Missing or invalid parameters.');
    }

    // Prepare and execute the SQL statements to block out the specified date range for the selected rooms and timeslots
    $sql = 'UPDATE availabilities SET is_blocked = true WHERE room = :room AND date BETWEEN :fromDate AND :toDate AND timeslot = :timeslot';
    $stmt = $pdo->prepare($sql);
    foreach ($selectedRooms as $room) {
        foreach ($selectedTimeslots as $timeslot) {
            $stmt->execute([
                ':room' => $room,
                ':fromDate' => $fromDate,
                ':toDate' => $toDate,
                ':timeslot' => $timeslot,
            ]);
        }
    }

    // Output the contents of the variables as JSON
    $response = [
        'selectedRooms' => $selectedRooms,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'selectedTimeslots' => $selectedTimeslots
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    // Return an error message
    echo json_encode(['error' => $e->getMessage()]);
}
?>
