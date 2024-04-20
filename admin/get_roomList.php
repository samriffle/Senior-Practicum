<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Check if the keySearch parameter is set
    if (isset($_GET['keySearch'])) {
        // Get the value of keySearch parameter
        $keySearch = $_GET['keySearch'];

        // Validate the parameter value
        if ($keySearch === 'true' || $keySearch === 'false' || $keySearch === 'all') {
            // Prepare the query based on the parameter value
            if ($keySearch === 'all') {
                $stmt = $pdo->query('SELECT DISTINCT room FROM room_keys');
            } else {
                $stmt = $pdo->prepare('SELECT DISTINCT room FROM room_keys WHERE key_available = :key_available');
                $stmt->bindValue(':key_available', $keySearch === 'true' ? 't' : 'f', PDO::PARAM_STR);
            }
            $stmt->execute();

            // Fetch the results
            $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Return the results as JSON
            header('Content-Type: application/json');
            echo json_encode($rooms);
            exit;
        } else {
            // Invalid parameter value
            http_response_code(400); // Bad Request
            echo 'Invalid value for keySearch parameter. Use "true", "false", or "all".';
            exit;
        }
    } else {
        // keySearch parameter not provided
        http_response_code(400); // Bad Request
        echo 'Missing keySearch parameter. Use "true", "false", or "all".';
        exit;
    }
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo 'Database connection error: ' . $e->getMessage();
    exit;
}
?>
