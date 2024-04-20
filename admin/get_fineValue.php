<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

if (!isset($_GET['sid'])) {
    die("Error: No student ID provided.");
}

$sid = $_GET['sid'];

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $stmt = $pdo->prepare("SELECT fine FROM student WHERE sid = :sid");
    $stmt->execute([':sid' => $sid]);
    $fineValue = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode($fineValue);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
