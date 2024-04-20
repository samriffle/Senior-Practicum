<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the room parameter is set
    if (isset($_GET['room'])) {
        $room = $_GET['room'];

        // Prepare the query to get option_name and stock for the specified room
        $stmt = $pdo->prepare('SELECT DISTINCT ro.option_name, o.stock FROM room_options ro JOIN options o ON ro.option_name = o.option_name WHERE ro.room = :room');
        $stmt->bindParam(':room', $room, PDO::PARAM_STR);
        $stmt->execute();

        // Fetch the results
        $roomOptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Return the results as JSON
        header('Content-Type: application/json');
        echo json_encode($roomOptions);
        exit;
    } else {
        // Room parameter not provided
        http_response_code(400); // Bad Request
        echo 'Missing room parameter.';
        exit;
    }
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
