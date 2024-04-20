<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the room and options parameters are set
    if (isset($_GET['room']) && isset($_GET['options'])) {
        $room = $_GET['room'];
        $options = explode(',', $_GET['options']);

        // Prepare the query to delete room options
        $stmt = $pdo->prepare('DELETE FROM room_options WHERE room = :room AND option_name = :option_name');
        foreach ($options as $option) {
            $stmt->bindParam(':room', $room, PDO::PARAM_STR);
            $stmt->bindParam(':option_name', $option, PDO::PARAM_STR);
            $stmt->execute();
        }

        // Prepare the query to delete options if they are not used in any other room
        $stmt = $pdo->prepare('DELETE FROM options WHERE option_name = :option_name AND NOT EXISTS (SELECT 1 FROM room_options WHERE option_name = :option_name)');
        foreach ($options as $option) {
            $stmt->bindParam(':option_name', $option, PDO::PARAM_STR);
            $stmt->execute();
        }

        echo 'Room options removed successfully.';
        exit;
    } else {
        // Room or options parameters not provided
        http_response_code(400); // Bad Request
        echo 'Missing room or options parameter.';
        exit;
    }
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
