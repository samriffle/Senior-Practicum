<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    // Connect to the database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

    // Fetch all instances where is_available is false, sorted by timeslot and date
    $sql = 'SELECT * FROM availabilities WHERE is_available = false ORDER BY date DESC, timeslot DESC';
    $stmt = $pdo->query($sql);
    $availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // For each availability, fetch the corresponding room_options
    foreach ($availabilities as &$availability) {
        $room = $availability['room'];
        $date = $availability['date'];
        $timeslot = $availability['timeslot'];
        
        $stmt = $pdo->prepare('SELECT * FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
        $stmt->execute([':room' => $room, ':date' => $date, ':timeslot' => $timeslot]);
        $room_options = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $availability['room_options'] = $room_options;
    }

    // Return the results as JSON
    header('Content-Type: application/json');
    echo json_encode($availabilities);
} catch (PDOException $e) {
    // Handle database connection error
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database connection error: ' . $e->getMessage()]);
}
?>
