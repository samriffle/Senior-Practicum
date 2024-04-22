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

    // Fetch rooms that were blocked but have a booking
    $sql = 'SELECT * FROM availabilities WHERE is_blocked = true AND sid IS NOT NULL AND room = :room AND date BETWEEN :fromDate AND :toDate AND timeslot = :timeslot';
    $stmt = $pdo->prepare($sql);
    $blockedRooms = []; // Initialize an empty array to store the blocked rooms
    foreach ($selectedRooms as $room) {
        foreach ($selectedTimeslots as $timeslot) {
            $stmt->execute([
                ':room' => $room,
                ':fromDate' => $fromDate,
                ':toDate' => $toDate,
                ':timeslot' => $timeslot,
            ]);
            $blockedRooms = array_merge($blockedRooms, $stmt->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    // Process the blocked rooms
    foreach ($blockedRooms as $blockedRoom) {
        // Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
        $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->bindParam(':room', $blockedRoom['room']);
        $stmt->bindParam(':date', $blockedRoom['date']);
        $stmt->bindParam(':timeslot', $blockedRoom['timeslot']);
        $stmt->execute();

        // Reset room_options to set option_selected false for the selected room, date, and timeslot
        $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
        $stmt->bindParam(':room', $blockedRoom['room']);
        $stmt->bindParam(':date', $blockedRoom['date']);
        $stmt->bindParam(':timeslot', $blockedRoom['timeslot']);
        $stmt->execute();

        // Check if there are any more instances of the student ID in availabilities
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
        $stmt->bindParam(':studentId', $blockedRoom['sid']);
        $stmt->execute();
        $count = $stmt->fetchColumn();

        if ($count === false) {
            // Handle error
            die('Error checking for student ID in availabilities.');
        } elseif ($count === 0) {
            // If there are no more instances of the student ID, check if the fine amount is 0
            $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
            $stmt->bindParam(':studentId', $blockedRoom['sid']);
            $stmt->execute();
            $fineAmount = $stmt->fetchColumn();

            if ($fineAmount === false) {
                // Handle error
                die('Error fetching fine amount for student.');
            } elseif ($fineAmount == 0) {
                // If fine amount is 0, remove the student from the student table
                $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                $stmt->bindParam(':studentId', $blockedRoom['sid']);
                $stmt->execute();

                echo 'Student removed successfully.';
            } else {
                echo 'Student not removed. Fine amount is not 0.';
            }
        } else {
            echo 'Student not removed. Student ID still exists in availabilities.';
        }

        echo 'Booking removed successfully.';
    }

    // Output the contents of the variables as JSON
    $response = [
        'selectedRooms' => $selectedRooms,
        'fromDate' => $fromDate,
        'toDate' => $toDate,
        'selectedTimeslots' => $selectedTimeslots,
        'blockedRooms' => $blockedRooms
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
} catch (Exception $e) {
    // Return an error message
    echo json_encode(['error' => $e->getMessage()]);
}
?>