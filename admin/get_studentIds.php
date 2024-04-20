<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $stmt = $pdo->query("SELECT sid FROM student");
    $studentIds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($studentIds);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
