<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

$studentId = $_GET['sid'];

// Check if the student exists in the student table
$stmt = $pdo->prepare("SELECT * FROM student WHERE sid = :sid");
$stmt->execute([':sid' => $studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    echo "Student ID not found.";
    exit;
}

// Reset the fine to 0
$pdo->prepare("UPDATE student SET fine = 0 WHERE sid = :sid")->execute([':sid' => $studentId]);

// Check if the student has any availabilities
$stmt = $pdo->prepare("SELECT * FROM availabilities WHERE sid = :sid");
$stmt->execute([':sid' => $studentId]);
$availabilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($availabilities) === 0 && $student['fine'] === 0) {
    // Remove the student tuple if the fine is 0 and there are no availabilities
    $pdo->prepare("DELETE FROM student WHERE sid = :sid")->execute([':sid' => $studentId]);
    echo "Fine reset to 0. Student ID removed from student table.";
} else {
    echo "Fine reset to 0.";
}

$pdo = null;
?>
