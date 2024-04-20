<?php
// Include the database configuration file
include 'db_config.php';

// Connect to the default PostgreSQL database (e.g., "postgres")
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=postgres;user=$user;password=$password");

// Check if the database exists
$stmt = $pdo->query("SELECT 1 FROM pg_database WHERE datname='$dbname'");
$databaseExists = $stmt->fetchColumn();

if ($databaseExists) {
    // Delete the existing database
    $pdo->exec("DROP DATABASE $dbname");
    echo "Existing database deleted. ";
}

// Create the database
$pdo->exec("CREATE DATABASE $dbname");
echo "Database created. ";

// Connect to the newly created database
$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Create the table to store room options
$pdo->exec("CREATE TABLE IF NOT EXISTS student (
    sid CHAR(7) PRIMARY KEY,
    fine INTEGER DEFAULT 0 CHECK (fine >= 0 AND fine <= 5)
)");

echo "Database populated with student logs. ";

// Create the table to store timeslot availabilities
$pdo->exec("CREATE TABLE IF NOT EXISTS availabilities (
    room TEXT NOT NULL,
    date DATE NOT NULL,
    timeslot TIME NOT NULL,
    is_available BOOLEAN NOT NULL,
    is_blocked BOOLEAN NOT NULL,
    sid CHAR(7),
    CONSTRAINT fk_sid FOREIGN KEY (sid) REFERENCES student (sid) ON DELETE CASCADE,
    CONSTRAINT pk_availabilities PRIMARY KEY (room, date, timeslot)
)");

// Define the date range (June 1, 2023 to June 1, 2024)
$start = strtotime('2023-06-01');
$end = strtotime('2024-06-01');

// Prepare a SQL query to insert timeslot availabilities
$stmt = $pdo->prepare('INSERT INTO availabilities (room, date, timeslot, is_available, is_blocked, sid) VALUES (:room, :date, :timeslot, :is_available, :is_blocked, :sid)');

$roomNames = [];
for ($i = 130; $i <= 136; $i++) {
    $roomNames[] = "Thorne " . $i;
}

// Loop through each room
foreach ($roomNames as $room) {
    // Loop through each day in the date range
    for ($date = $start; $date < $end; $date += 86400) { // 86400 seconds in a day
        $formatted_date = date('Y-m-d', $date);
        // Loop through each half-hour interval in a day from 7:30am to 11pm
        for ($interval = 15; $interval <= 46; $interval++) {
            $hour = floor($interval / 2); // Get the hour part of the interval
            $minute = ($interval % 2) * 30; // Get the minute part of the interval (0 or 30)
            $formatted_timeslot = sprintf('%02d:%02d', $hour, $minute);
            // Insert timeslot availability into the database (assuming all are available)
            $stmt->execute([
                ':room' => $room,
                ':date' => $formatted_date,
                ':timeslot' => $formatted_timeslot,
                ':is_available' => true ? 'true' : 'false',
                ':is_blocked' => false ? 'true' : 'false',
                ':sid' => NULL,
            ]);
        }
    }
}

echo "Database populated with timeslot availabilities. ";

// Create the table to store options
$pdo->exec("CREATE TABLE IF NOT EXISTS options (
    option_name TEXT PRIMARY KEY,
    stock INT NOT NULL
)");

// Define the options array
$options = ['projector', 'microphone', 'whiteboard'];

// Insert option data
$optionData = [];
foreach ($options as $option) {
    $optionData[] = ['option_name' => $option, 'stock' => 20]; // Replace 20 with the actual stock value in admin code
}

// Prepare the insert statement
$stmt = $pdo->prepare('INSERT INTO options (option_name, stock) VALUES (:option_name, :stock)');

// Insert options
foreach ($optionData as $option) {
    $stmt->execute([
        ':option_name' => $option['option_name'],
        ':stock' => $option['stock']
    ]);
}

echo "Options table created and populated. ";

// Create the table to store room options
$pdo->exec("CREATE TABLE IF NOT EXISTS room_options (
    room TEXT NOT NULL,
    date DATE NOT NULL,
    timeslot TIME NOT NULL,
    option_name TEXT NOT NULL,
    option_unavailable BOOLEAN NOT NULL,
    option_selected BOOLEAN NOT NULL,
    CONSTRAINT fk_room_options FOREIGN KEY (room, date, timeslot) REFERENCES availabilities (room, date, timeslot) ON DELETE CASCADE,
    CONSTRAINT fk_room_options_option FOREIGN KEY (option_name) REFERENCES options (option_name) ON DELETE CASCADE
)");

// Insert room options data
$roomOptions = [];
for ($roomNumber = 130; $roomNumber <= 136; $roomNumber++) {
    $room = "Thorne $roomNumber";
    foreach ($options as $option) {
        $roomOptions[] = ['room' => $room, 'option_name' => $option, 'option_unavailable'=> false ? 'true' : 'false', 'option_selected' => false ? 'true' : 'false'];
    }
}

// Prepare the insert statement
$stmt = $pdo->prepare('INSERT INTO room_options (room, date, timeslot, option_name, option_unavailable, option_selected) VALUES (:room, :date, :timeslot, :option_name, :option_unavailable, :option_selected)');

// Loop through each room
foreach ($roomNames as $room) {
    // Loop through each day in the date range
    for ($date = $start; $date < $end; $date += 86400) { // 86400 seconds in a day
        $formatted_date = date('Y-m-d', $date);
        // Loop through each half-hour interval in a day from 7:30am to 11pm
        for ($interval = 15; $interval <= 46; $interval++) {
            $hour = floor($interval / 2); // Get the hour part of the interval
            $minute = ($interval % 2) * 30; // Get the minute part of the interval (0 or 30)
            $formatted_timeslot = sprintf('%02d:%02d', $hour, $minute);
            // Insert room options for this room, date, and timeslot
            foreach ($roomOptions as $option) {
                if ($option['room'] == $room) {
                    $stmt->execute([
                        ':room' => $option['room'],
                        ':date' => $formatted_date,
                        ':timeslot' => $formatted_timeslot,
                        ':option_name' => $option['option_name'],
                        ':option_unavailable' => $option['option_unavailable'],
                        ':option_selected' => $option['option_selected'],
                    ]);
                }
            }
        }
    }
}

echo "Database populated with room options. ";

// Create the table to manage rooms being used
$pdo->exec("CREATE TABLE IF NOT EXISTS room_keys (
    room TEXT NOT NULL,
    date DATE NOT NULL,
    timeslot TIME NOT NULL,
    key_available BOOLEAN NOT NULL,
    issue_time TIMESTAMP DEFAULT NULL,
    CONSTRAINT fk_room_options FOREIGN KEY (room, date, timeslot) REFERENCES availabilities (room, date, timeslot) ON DELETE CASCADE
)");

// Prepare a SQL query to insert key availabilities
$stmt = $pdo->prepare('INSERT INTO room_keys (room, date, timeslot, key_available) VALUES (:room, :date, :timeslot, :key_available)');

// Loop through each room
foreach ($roomNames as $room) {
    // Loop through each day in the date range
    for ($date = $start; $date < $end; $date += 86400) { // 86400 seconds in a day
        $formatted_date = date('Y-m-d', $date);
        // Loop through each half-hour interval in a day from 7:30am to 11pm
        for ($interval = 15; $interval <= 46; $interval++) {
            $hour = floor($interval / 2); // Get the hour part of the interval
            $minute = ($interval % 2) * 30; // Get the minute part of the interval (0 or 30)
            $formatted_timeslot = sprintf('%02d:%02d', $hour, $minute);
            // Insert room keys for this room, date, and timeslot
            $stmt->execute([
                ':room' => $room,
                ':date' => $formatted_date,
                ':timeslot' => $formatted_timeslot,
                ':key_available' => true ? 'true' : 'false',
            ]);
        }
    }
}

echo "Database populated with room keys. ";

echo "Done. ";

// Close the database connection
$pdo = null;
?>
