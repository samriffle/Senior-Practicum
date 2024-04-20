<?php
    date_default_timezone_set('America/New_York');

    // Include the database configuration file
    include 'db_config.php';

    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Get the date and room from the request (e.g., from query parameters)
    $date = $_GET['date']; // Assuming the date is passed as a query parameter
    $room = $_GET['room']; // Assuming the room is passed as a query parameter

    // Prepare and execute a query to fetch availability data for the specified date and room
    $stmt = $pdo->prepare("SELECT timeslot, is_available FROM availabilities WHERE date = ? AND room = ?");
    $stmt->execute([$date, $room]);

    // Fetch the results as an associative array
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Return the results as JSON
    header('Content-Type: application/json');
    echo json_encode($results);
?>
