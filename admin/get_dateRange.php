<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Fetch the earliest and latest dates from the database
    $stmt = $pdo->query('SELECT MIN(date), MAX(date) FROM availabilities');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    // Return the dates as JSON
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
