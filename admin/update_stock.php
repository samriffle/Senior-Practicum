<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the options and stock parameters are set
    if (isset($_GET['options']) && isset($_GET['stock'])) {
        $options = explode(',', $_GET['options']); // Split options into an array
        $newStock = $_GET['stock'];

        // Prepare the update query
        $stmt = $pdo->prepare('UPDATE options SET stock = :stock WHERE option_name = :option_name');
        
        foreach ($options as $option) {
            // Bind parameters and execute the query for each option
            $stmt->bindParam(':stock', $newStock, PDO::PARAM_INT);
            $stmt->bindParam(':option_name', $option, PDO::PARAM_STR);
            $stmt->execute();
        }

        echo 'Stock updated successfully.';
        exit;
    } else {
        // Parameters not provided
        http_response_code(400); // Bad Request
        echo 'Missing parameters.';
        exit;
    }
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
