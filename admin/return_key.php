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

    // Get the issue_time for the room from room_keys
    $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
    $stmt->execute([':room' => $room]);
    $issueTime = $stmt->fetchColumn();

    if ($issueTime !== false) {
        // Get the current timestamp rounded down to the nearest 30-minute increment
        $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);

        // Split the issueTimeSlot into date and timeslot
        $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
        $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));

        // Check if there was a booking at the specified key issue time for the room
        $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
        $studentId = $stmt->fetchColumn();

        if ($studentId !== null) {
            // Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
            $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

            // Get the list of option_names that were selected for the booking
            $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
            $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
            $selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Update the stock for each selected option
            $stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
            foreach ($selectedOptions as $option) {
                $stmt->execute([':option_name' => $option]);
            }

            // Reset room_options to set option_selected false for the selected room, date, and timeslot
            $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

            // Check if there are any more instances of the student ID in availabilities
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
            $stmt->execute([':studentId' => $studentId]);
            $count = $stmt->fetchColumn();

            if ($count === false) {
                // Handle error
                die('Error checking for student ID in availabilities.');
            } elseif ($count === 0) {
                // If there are no more instances of the student ID, check if the fine amount is 0
                $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                $stmt->execute([':studentId' => $studentId]);
                $fineAmount = $stmt->fetchColumn();

                if ($fineAmount === false) {
                    // Handle error
                    die('Error fetching fine amount for student.');
                } elseif ($fineAmount == 0) {
                    // If fine amount is 0, remove the student from the student table
                    $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $studentId]);
                } else {
                    echo 'Student not removed. Fine amount is not 0.';
                }
            } else {
                echo 'Student not removed. Student ID still exists in availabilities.';
            }

            echo 'Booking removed successfully.';

            // Return the room key since the room has been cleared
            $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
            $stmt->execute([':room' => $room]);
        } else {
            // Room was not booked at the specified time, set key_available back to true for the room
            $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
            $stmt->execute([':room' => $room]);
            
            // Return an error message
            echo "No booking found for room $room at $issueTimeslotTimeslot. Voided key returned.";

            // Close the database connection
            $pdo = null;
            exit(); // Exit the script
        }
    } else {
        // No key issued for the room, return an error message
        echo "No key issued for room $room.";
    }
} else {
    // 'room' parameter not provided
    http_response_code(400); // Bad Request
    echo 'Missing room parameter.';
}

// Close the database connection
$pdo = null;
?>
