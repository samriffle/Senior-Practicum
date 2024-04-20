<?php
	date_default_timezone_set('America/New_York');

	// Include the database configuration file
	include 'db_config.php';

	// Connect to the database
	$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

	// Get the studentId from the request (e.g., from a query parameter)
	$studentId = $_GET['studentId']; // Assuming the studentId is passed as a query parameter

	// Prepare SQL statement to fetch reservations for the given studentId
	$stmt = $pdo->prepare("SELECT room, date, timeslot FROM availabilities WHERE sid = ?");
	$stmt->execute([$studentId]);

	// Fetch the reservations
	$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Return the results as JSON
	header('Content-Type: application/json');
	echo json_encode($reservations);
?>
