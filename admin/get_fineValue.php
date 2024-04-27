<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

if (!isset($_GET['sid'])) {
    die("Error: No student ID provided.");
}

$sid = $_GET['sid'];

try {
    // Get fine from database
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");
    $stmt = $pdo->prepare("SELECT fine FROM student WHERE sid = :sid");
    $stmt->execute([':sid' => $sid]);
    $fineValue = $stmt->fetch(PDO::FETCH_ASSOC);

    // Get password from userids.csv
    $passwordFile = '../auth/userids.csv';
    if (($handle = fopen($passwordFile, "r")) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if ($data[0] == $sid) {
                $password = $data[1];
                break;
            }
        }
        fclose($handle);
    }

    // Return both fine and password
    echo json_encode(['fine' => $fineValue, 'password' => $password]);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
