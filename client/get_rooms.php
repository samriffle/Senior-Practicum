<?php
    date_default_timezone_set('America/New_York');

    // Include the database configuration file
    include 'db_config.php';

    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Get the date from the request (e.g., from a query parameter)
	$date = $_GET['date']; // Assuming the date is passed as a query parameter

    // Prepare SQL statement to fetch available rooms for the given date
    $stmt = $pdo->prepare("SELECT DISTINCT room FROM availabilities WHERE date = ?");
    $stmt->execute([$date]);

    // Fetch the rooms
    $rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Return the results as JSON
	header('Content-Type: application/json');
	echo json_encode($rooms);
?>