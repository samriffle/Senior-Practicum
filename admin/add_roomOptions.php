<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the room and options parameters are set
    if (isset($_GET['room']) && isset($_GET['option'])) {
        $room = $_GET['room'];
        $options = $_GET['option'];

        // Check if the option already exists in the options table
        $stmt = $pdo->prepare('SELECT option_name FROM options WHERE option_name = :option_name');
        $stmt->bindParam(':option_name', $options, PDO::PARAM_STR);
        $stmt->execute();
        $existingOption = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existingOption) {
            // Option already exists, return an error
            http_response_code(400); // Bad Request
            echo 'Option already exists.';
            exit;
        }

        // Insert the new option into the options table
        $stmt = $pdo->prepare('INSERT INTO options (option_name, stock) VALUES (:option_name, 20)');
        $stmt->bindParam(':option_name', $options, PDO::PARAM_STR);
        $stmt->execute();

         // Get all unique combinations of room, date, and timeslot for the specified room
        $stmt = $pdo->prepare('SELECT DISTINCT room, date, timeslot FROM room_options WHERE room = :room');
        $stmt->bindParam(':room', $room, PDO::PARAM_STR);
        $stmt->execute();
        $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Insert a new tuple for each combination with the new option
        $stmt = $pdo->prepare('INSERT INTO room_options (room, date, timeslot, option_name, option_unavailable, option_selected) VALUES (:room, :date, :timeslot, :option_name, false, false)');
        foreach ($combinations as $combination) {
            $stmt->bindParam(':room', $combination['room'], PDO::PARAM_STR);
            $stmt->bindParam(':date', $combination['date'], PDO::PARAM_STR);
            $stmt->bindParam(':timeslot', $combination['timeslot'], PDO::PARAM_STR);
            $stmt->bindParam(':option_name', $options, PDO::PARAM_STR);
            $stmt->execute();
        }
        exit;
    } else {
        // Room or options parameters not provided
        http_response_code(400); // Bad Request
        echo 'Missing room or option parameter.';
        exit;
    }
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
