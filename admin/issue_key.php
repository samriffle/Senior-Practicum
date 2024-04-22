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

        // Set stock -1 for being in use for selected options for the room date and timeslot
        $stmt = $pdo->prepare('SELECT option_name, option_selected FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot]);
        $selectedOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Update the stock for selected options marked as in use
        foreach ($selectedOptions as $option) {
            if ($option['option_selected']) {
                $stmt = $pdo->prepare('UPDATE options SET stock = stock - 1 WHERE option_name = :option_name AND stock > 0');
                $stmt->execute([':option_name' => $option['option_name']]);
            }
        }

    } else {
        echo "No student booked for room $room at $currentTimeslot.\nChecking time to issue a booking for this walk in.";
        
        // Check to make sure current time isnt 10+ minutes past start of current timeslot interval
        $currentTimeslotCheck = strtotime(date('H:i:00', floor(time() / 1800) * 1800)); // Round down to the nearest 30-minute interval
        $currentTimeCheck = strtotime(date('H:i:s'));

        if ($currentTimeCheck <= $currentTimeslotCheck + 600) {
            // Check if the sid '0000000' already exists in the student table
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM student WHERE sid = :sid');
            $stmt->bindParam(':sid', $wildcardSid);
            $wildcardSid = '0000000';
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                // Insert the new sid '0000000' into the student table
                $stmt = $pdo->prepare('INSERT INTO student (sid) VALUES (:sid)');
                $stmt->bindParam(':sid', $wildcardSid);
                $wildcardSid = '0000000';
                $stmt->execute();
            }
            else {
                // Reset the fine for sid '0000000' since it shouldnt be tied to a real student and fined
                $stmt = $pdo->prepare('UPDATE student SET fine = 0 WHERE sid = :sid');
                $stmt->bindParam(':sid', $wildcardSid);
                $stmt->execute();
            }

            // Book the wildcard student for the room
            $stmt = $pdo->prepare('UPDATE availabilities SET is_available = false, sid = :sid WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':sid' => $wildcardSid, ':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot]);

            // Prepare the update query to set key_available to false and issue_time to the current timestamp for the specified room
            $stmt = $pdo->prepare('UPDATE room_keys SET key_available = false, issue_time = :issue_time WHERE room = :room');
            $stmt->bindParam(':room', $room, PDO::PARAM_STR);
            $stmt->bindParam(':issue_time', $issueTime, PDO::PARAM_STR);

            // Execute the query
            $stmt->execute();

            // Check if any rows were affected
            if ($stmt->rowCount() > 0) {
                echo "Walk-in booking successful for room $room at $issueTime.";
            } else {
                echo "Failed to issue key for walk-in at room $room.";
            }
        } else {
            echo "Too late to issue a walk-in booking for this timeslot. Please wait.";
        }
    }
} else {
    // 'room' parameter not provided
    http_response_code(400); // Bad Request
    echo 'Missing room parameter.';
}

// Close the database connection
$pdo = null;
?>
