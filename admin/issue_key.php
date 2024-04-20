<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

// Connect to the database using the parameters from db_config.php
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Check if the 'room' parameter is set
if (isset($_GET['room'])) {
    // Get the value of the 'room' parameter
    $room = $_GET['room'];

    // Get the current timestamp
    $issueTime = date('Y-m-d H:i:s');

    // Check if there is an sid attached to the current timeslot in the availabilities table
    $currentDate = date('Y-m-d');
    $currentTimeslot = date('H:i:00', floor(time() / 1800) * 1800); // Round down to the nearest 30-minute interval
    $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
    $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot]);
    $sid = $stmt->fetchColumn();

    if ($sid) { ////////////////////////////////////////////// set back to $sid when not testing
        // Prepare the update query to set key_available to false and issue_time to the current timestamp for the specified room
        $stmt = $pdo->prepare('UPDATE room_keys SET key_available = false, issue_time = :issue_time WHERE room = :room');
        $stmt->bindParam(':room', $room, PDO::PARAM_STR);
        $stmt->bindParam(':issue_time', $issueTime, PDO::PARAM_STR);

        // Execute the query
        $stmt->execute();

        // Check if any rows were affected
        if ($stmt->rowCount() > 0) {
            echo "Key issued successfully for room $room at $issueTime.";
        } else {
            echo "Failed to issue key for room $room.";
        }
    } else {
        echo "No student booked for room $room at $currentTimeslot.";
    }
} else {
    // 'room' parameter not provided
    http_response_code(400); // Bad Request
    echo 'Missing room parameter.';
}

// Close the database connection
$pdo = null;
?>
