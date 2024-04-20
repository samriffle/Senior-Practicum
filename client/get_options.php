<?php
	date_default_timezone_set('America/New_York');

	// Include the database configuration file
	include 'db_config.php';

	// Connect to the database
	$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

	// Get the date, room, and timeslot from the request (e.g., from query parameters)
	$date = $_GET['date']; 
	$room = $_GET['room']; 
	$timeslot = $_GET['timeslot']; 

	// Prepare and execute a query to fetch room options for the specified date, room, and timeslot
	$stmt = $pdo->prepare("SELECT ro.option_name, ro.option_unavailable FROM room_options ro JOIN options o ON ro.option_name = o.option_name WHERE o.stock > 0 AND ro.date = ? AND ro.room = ? AND ro.timeslot = ?");
	$stmt->execute([$date, $room, $timeslot]);

	// Fetch the results as an associative array
	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Return the results as JSON
	header('Content-Type: application/json');
	echo json_encode($results);
?>
