<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

// Connect to the database using the parameters from db_config.php
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Query to select rooms that are not available
$stmt = $pdo->query("SELECT DISTINCT room, issue_time AT TIME ZONE 'UTC' AT TIME ZONE 'America/New_York' AS issue_time FROM room_keys WHERE key_available = false");

// Fetch the results into an associative array
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Close the database connection
$pdo = null;

// Return the data as JSON
header('Content-Type: application/json');
echo json_encode($rooms);
?>
