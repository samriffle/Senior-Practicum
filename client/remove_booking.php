<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

// Connect to the database
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
$stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
$stmt->bindParam(':room', $_POST['room']);
$stmt->bindParam(':date', $_POST['date']);
$stmt->bindParam(':timeslot', $_POST['timeslot']);
$stmt->execute();

// Reset room_options to set option_selected false for the selected room, date, and timeslot
$stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
$stmt->bindParam(':room', $_POST['room']);
$stmt->bindParam(':date', $_POST['date']);
$stmt->bindParam(':timeslot', $_POST['timeslot']);
$stmt->execute();

// Check if there are any more instances of the student ID in availabilities
$stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
$stmt->bindParam(':studentId', $_POST['studentId']);
$stmt->execute();
$count = $stmt->fetchColumn();

if ($count === false) {
    // Handle error
    die('Error checking for student ID in availabilities.');
} elseif ($count === 0) {
    // If there are no more instances of the student ID, check if the fine amount is 0
    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
    $stmt->bindParam(':studentId', $_POST['studentId']);
    $stmt->execute();
    $fineAmount = $stmt->fetchColumn();

    if ($fineAmount === false) {
        // Handle error
        die('Error fetching fine amount for student.');
    } elseif ($fineAmount == 0) {
        // If fine amount is 0, remove the student from the student table
        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
        $stmt->bindParam(':studentId', $_POST['studentId']);
        $stmt->execute();

        echo 'Student removed successfully.';
    } else {
        echo 'Student not removed. Fine amount is not 0.';
    }
} else {
    echo 'Student not removed. Student ID still exists in availabilities.';
}

echo 'Booking removed successfully.';
?>
