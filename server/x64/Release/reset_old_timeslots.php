<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

// Connect to the database
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

echo "Old Booking Scrubber Start\n";
// Get the current date
$currentDate = date('Y-m-d');

// Update availabilities to set is_available true and remove the student ID for dates before the current date
$stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, is_blocked = false, sid = NULL WHERE date < :date');
$stmt->bindParam(':date', $currentDate);
$stmt->execute();

// Get the list of option_names that were selected for the bookings before the current date
$stmt = $pdo->prepare('SELECT option_name FROM room_options WHERE date < :date AND option_selected = true');
$stmt->bindParam(':date', $currentDate);
$stmt->execute();
$selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Update the stock for each selected option
$stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
foreach ($selectedOptions as $option) {
    $stmt->bindParam(':option_name', $option);
    $stmt->execute();
}

// Reset room_options to set option_selected false for dates before the current date
$stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE date < :date AND option_selected = true');
$stmt->bindParam(':date', $currentDate);
$stmt->execute();

echo "  Old bookings removed\n";
echo "  Checking for bookings in the future where a student already has $5 fine.\n";

// Check for students with a fine of $5 or more
$stmt = $pdo->prepare('SELECT sid FROM student WHERE fine >= 5');
$stmt->execute();
$studentsWithFine = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($studentsWithFine as $sid) {
    
    // Get a list of all tuples in availabilities that given sid is in past current date
    $stmt = $pdo->prepare('SELECT room, date, timeslot FROM availabilities WHERE sid = :sid AND date > :date');
    $stmt->bindParam(':sid', $sid);
    $stmt->bindParam(':date', $currentDate);
    $stmt->execute();
    $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // foreach tuple found
    foreach ($availabilities as $availability) {

        // get the room date and timeslot from the tuple 
        $room = $availability['room'];
        $date = $availability['date'];
        $timeslot = $availability['timeslot'];

        // Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
        $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->bindParam(':room', $room);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':timeslot', $timeslot);
        $stmt->execute();

        // Get the list of option_names that were selected for the booking
        $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
        $stmt->bindParam(':room', $room);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':timeslot', $timeslot);
        $stmt->execute();
        $selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Update the stock for each selected option
        $stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
        foreach ($selectedOptions as $option) {
            $stmt->bindParam(':option_name', $option);
            $stmt->execute();
        }

        // Reset room_options to set option_selected false for the selected room, date, and timeslot
        $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->bindParam(':room', $room);
        $stmt->bindParam(':date', $date);
        $stmt->bindParam(':timeslot', $timeslot);
        $stmt->execute();

        // Check if there are any more instances of the student ID in availabilities
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
        $stmt->bindParam(':studentId', $sid);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count === false) {
            // Handle error
            die('Error checking for student ID in availabilities.');
        } elseif ($count === 0) {
            // If there are no more instances of the student ID, check if the fine amount is 0
            $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
            $stmt->bindParam(':studentId', $sid);
            $stmt->execute();
            $fineAmount = $stmt->fetchColumn();

            if ($fineAmount === false) {
                // Handle error
                die('Error fetching fine amount for student.');
            } elseif ($fineAmount == 0) {
                // If fine amount is 0, remove the student from the student table
                $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                $stmt->bindParam(':studentId', $sid);
                $stmt->execute();

                echo '      Student removed successfully.';
            } else {
                echo '      Student not removed. Fine amount is not 0.';
            }
        } else {
            echo '      Student not removed. Student ID still exists in availabilities.';
        }

        echo '      Booking removed successfully.';
    }
}

echo "  Bookings for students with a fine of $5 or more removed\n";
echo "  Cleaning up keys\n";

// Remove issue_time of all room_keys where key_available = true
$stmt = $pdo->prepare('UPDATE room_keys SET issue_time = NULL WHERE key_available = true');
$stmt->execute();

// Close the database connection
$pdo = null;
?>
