<?php
    date_default_timezone_set('America/New_York');

    // Include the database configuration file
    include 'db_config.php';

    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Query to find the minimum and maximum dates available
    $stmt = $pdo->query('SELECT MIN(date) AS minDate, MAX(date) AS maxDate FROM availabilities');

    // Fetch the result as an associative array
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Convert the result to JSON and output it
    header('Content-Type: application/json');
    echo json_encode($result);
?>