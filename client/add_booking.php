<?php
    date_default_timezone_set('America/New_York');

    // Include the database configuration file
    include 'db_config.php';

    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the booking parameters are not older than the current date and time
    if ($_POST['date'] < date('Y-m-d') || ($_POST['date'] == date('Y-m-d') && $_POST['timeslot'] < date('H:i:s'))) {
        http_response_code(400); // Bad Request
        echo 'Booking date and time cannot be older than the current date and time. Booking failed.';
        exit(); // or handle the error as needed
    }

    // Check if the student exists
    $stmt = $pdo->prepare('SELECT sid, fine FROM student WHERE sid = :studentId');
    $stmt->bindParam(':studentId', $_POST['studentId']);
    $stmt->execute();
    $existingStudent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$existingStudent) {
        // Insert the student as a new tuple
        $stmt = $pdo->prepare('INSERT INTO student (sid) VALUES (:studentId)');
        $stmt->bindParam(':studentId', $_POST['studentId']);
        $stmt->execute();
    } else {
        // Check if the student has a fine amount of 5
        if ($existingStudent['fine'] == 5) {
            http_response_code(400); // Bad Request
            echo 'Student has a fine amount of 5. Booking failed.';
            exit(); // or handle the error as needed
        }
    }

    // Check if all selected options have stock greater than 0
    $stmt = $pdo->prepare('SELECT option_name FROM options WHERE option_name = :option_name AND stock > 0');
    foreach ($_POST['selectedOptions'] as $selectedOption) {
        $stmt->bindParam(':option_name', $selectedOption);
        $stmt->execute();
        $existingOption = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingOption) {
            // Option is out of stock
            http_response_code(400); // Bad Request
            echo 'One or more selected options are out of stock. Booking failed.';
            exit(); // or handle the error as needed
        }
    }

    // Update availabilities with option_selected true and is_available false for selected room, date, timeslot
    $stmt = $pdo->prepare('UPDATE availabilities SET is_available = false, sid = :studentId WHERE room = :room AND date = :date AND timeslot = :timeslot');
    $stmt->bindParam(':studentId', $_POST['studentId']);
    $stmt->bindParam(':room', $_POST['room']);
    $stmt->bindParam(':date', $_POST['date']);
    $stmt->bindParam(':timeslot', $_POST['timeslot']);
    $stmt->execute();

    // Update room options with option_selected true for selected options
    $stmt = $pdo->prepare('UPDATE room_options SET option_selected = true WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_name = :option_name');
    $stmt->bindParam(':room', $_POST['room']);
    $stmt->bindParam(':date', $_POST['date']);
    $stmt->bindParam(':timeslot', $_POST['timeslot']);
    foreach ($_POST['selectedOptions'] as $selectedOption) {
        $stmt->bindParam(':option_name', $selectedOption);
        $stmt->execute();
    }

    echo 'Database updated successfully.';
?>
