<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Get current date and timeslot
$currentDate = date('Y-m-d');
$currentRoundedTimeslot = date('H:i:00', floor(time() / 1800) * 1800); // Round down to the nearest 30-minute interval
$roundedTimestamp = strtotime($currentDate . ' ' . $currentRoundedTimeslot);
$currentRoundedTimestamp = date('Y-m-d H:i:00', $roundedTimestamp);

echo "\nBooking Validation Start {$currentDate}," . date("H:i:s") . "\n";

// _____________________________________________________________ Pt. 1 Manage Current Keys For Current Bookings (Issued From Current Timeslot) _____________________________________________________________ //

// Check for bookings that should be active in the current timeslot
$stmt = $pdo->prepare('SELECT * FROM availabilities WHERE date = :date AND timeslot = :timeslot AND is_available = false ORDER BY room ASC');
$stmt->execute([':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
$bookedRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// for each of these bookings, get their respective key value (issued or not)
if (count($bookedRooms) !== 0) { 
    foreach ($bookedRooms as $room) {
        
        // Check if a key is issued or not issued for this room in the current timeslot
        echo "  1. Reviewing room key for {$room['room']} \n";
        $stmt = $pdo->prepare('SELECT * FROM room_keys WHERE room = :room AND date = :date AND timeslot = :timeslot AND key_available = false');
        $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
        $keyIssued = $stmt->fetch(PDO::FETCH_ASSOC);
     
        // If key is issued
        if ($keyIssued) {

            // Do nothing booking is going swimmingly or booking will be handled by pt.2
            if ($keyIssued['issue_time'] < $currentRoundedTimestamp) {
                echo "      Key has been issued for {$room['room']}, but it was issued before the current timeslot.\n";
            } else {
                echo "      Key has been issued for {$room['room']}. \n";
            }

        // Else when key not issued
        } else {

            // Check if 10 minutes have passed since currentRoundedTimeslot started
            echo "      Key has not been issued for {$room['room']} yet. \n";
            $currentTimestamp = date('Y-m-d H:i:s');
            $bookingStartTimestamp = $currentDate . ' ' . $room['timeslot'];
            $bookingStartTimestamp = date('Y-m-d H:i:s', strtotime($bookingStartTimestamp));
            $timeDiff = strtotime($currentTimestamp) - strtotime($bookingStartTimestamp);

            // If 10 minutes have passed since currentRoundedTimeslot started
            if ($timeDiff >= 600) { 

                // Remove tardy booking
                echo "      Timeout counter expired. Removing tardy booking.\n";
                $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
                $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                $stmt->execute([':studentId' => $room['sid']]);
                $count = $stmt->fetchColumn();
                if ($count === false) {
                    echo "          Error checking for student ID in availabilities.\n";
                } elseif ($count === 0) {
                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $room['sid']]);
                    $fineAmount = $stmt->fetchColumn();
                    if ($fineAmount === false) {
                        echo "          Error fetching fine amount for student.\n";
                    } elseif ($fineAmount == 0) {
                        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $room['sid']]);
                    } else {
                        echo "          Student not removed. Fine amount is not 0.\n";
                    }
                } else {
                    echo "          Student not removed. Student ID still exists in availabilities.\n";
                }
                echo "          Tardy booking removed successfully for room {$room['room']}.\n";

            // Else 
            } else {

                // We wait and do nothing
                echo "      Timeout counter in effect. Room will be removed 10 minutes after this timeslot's start. \n";

            }
        }
    }
}

// _____________________________________________________________ Pt. 2 Manage Late Keys For Hung Bookings (Issued From Past Timeslot) _____________________________________________________________ //

// Check for any old keys for students in their room past the previous timeslot where key_available = false and issue_time < currentRoundedTimestamp
$stmt = $pdo->prepare('SELECT DISTINCT room FROM room_keys WHERE issue_time < :issue_time AND key_available = false ORDER BY room ASC');
$stmt->execute([':issue_time' => $currentRoundedTimestamp]);
$oldKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

//  for each of these keys with issue_time < currentRoundedTimestamp
foreach ($oldKeys as $oldKey) {

    // Check if theres a booking in availabilities for the key's room, the currentDate, and the currentTimeslotRounded
    echo "  2. Reviewing tardy key for room {$oldKey}. \n";
    $stmt = $pdo->prepare('SELECT * FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot AND sid IS NOT NULL');
    $stmt->execute([':room' => $oldKey, ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    // If there is a booking on it already 
    if ($booking) {

        // Fine sid of keyholder
        $previousTimeslot = date('H:i:00', strtotime($currentRoundedTimeslot) - 1800);
        $stmt = $pdo->prepare('SELECT * FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot AND sid IS NOT NULL');
        $stmt->execute([':room' => $oldKey, ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
        $bookingPrev = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('UPDATE student SET fine = fine + 1 WHERE sid = :sid AND fine < 5');
        $stmt->execute([':sid' => $bookingPrev['sid']]);
        echo "      Fine increased for student {$bookingPrev['sid']}. \n";

        // Returnkey.php clone on previous timeslot (-30 minutes ago)
        echo "      Already a booking on current timeslot. Cannot reschedule. Removing old info and returning key. \n";
        $room = $oldKey;
        $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
        $stmt->execute([':room' => $room]);
        $issueTime = $stmt->fetchColumn();
        if ($issueTime !== false) {
            $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);
            $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
            $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));
            $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
            $studentId = $stmt->fetchColumn();
            if ($studentId !== null) {
                $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
                $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                $selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                $stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
                foreach ($selectedOptions as $option) {
                    $stmt->execute([':option_name' => $option]);
                }
                $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                $stmt->execute([':studentId' => $studentId]);
                $count = $stmt->fetchColumn();
                if ($count === false) {
                    echo "          Error checking for student ID in availabilities.\n";
                } elseif ($count === 0) {
                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $studentId]);
                    $fineAmount = $stmt->fetchColumn();
                    if ($fineAmount === false) {
                        echo "          Error fetching fine amount for student.\n";
                    } elseif ($fineAmount == 0) {
                        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $studentId]);
                    } else {
                        echo "          Student not removed. Fine amount is not 0.\n";
                    }
                } else {
                    echo "          Student not removed. Student ID still exists in availabilities.\n";
                }
                echo "          Booking removed successfully.\n";
                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                $stmt->execute([':room' => $room]);
            } else {
                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                $stmt->execute([':room' => $room]);
                echo "          No booking found for room $room at $issueTimeslotTimeslot. Voided key returned.\n";
            }
        } else {
            echo "          No key issued for room $room.\n";
        }

    // Else 
    } else {

        // Does sid tied to that room key's old booking have a fine >= 5 (availabilities['sid'] -> student['sid', 'fine'])
        $previousTimeslot = date('H:i:00', strtotime($currentRoundedTimeslot) - 1800);
        $stmt = $pdo->prepare('SELECT * FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot AND sid IS NOT NULL');
        $stmt->execute([':room' => $oldKey, ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
        $bookingPrev = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :sid');
        $stmt->execute([':sid' => $bookingPrev['sid']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // If sid's fine >= 5
        if ($student && $student['fine'] >= 5) {

            // Returnkey.php clone on previous timeslot (-30 minutes ago)
            echo "      Student fine limit already reached. Cannot reschedule. Removing old info and returning key. \n";
            $room = $oldKey;
            $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
            $stmt->execute([':room' => $room]);
            $issueTime = $stmt->fetchColumn();
            if ($issueTime !== false) {
                $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);
                $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
                $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));
                $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                $studentId = $stmt->fetchColumn();
                if ($studentId !== null) {
                    $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                    $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
                    $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
                    foreach ($selectedOptions as $option) {
                        $stmt->execute([':option_name' => $option]);
                    }
                    $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                    $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $studentId]);
                    $count = $stmt->fetchColumn();
                    if ($count === false) {
                        echo "          Error checking for student ID in availabilities.\n";
                    } elseif ($count === 0) {
                        $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $studentId]);
                        $fineAmount = $stmt->fetchColumn();
                        if ($fineAmount === false) {
                            echo "          Error fetching fine amount for student.\n";
                        } elseif ($fineAmount == 0) {
                            $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                            $stmt->execute([':studentId' => $studentId]);
                        } else {
                            echo "          Student not removed. Fine amount is not 0.\n";
                        }
                    } else {
                        echo "          Student not removed. Student ID still exists in availabilities.\n";
                    }
                    echo "          Booking removed successfully.\n";
                    $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                    $stmt->execute([':room' => $room]);
                } else {
                    $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                    $stmt->execute([':room' => $room]);
                    echo "          No booking found for room $room at $issueTimeslotTimeslot. Voided key returned.\n";
                }
            } else {
                echo "          No key issued for room $room.\n";
            }

        // Else sid can be fined and extended
        } else {

            // Fine sid of keyholder
            $previousTimeslot = date('H:i:00', strtotime($currentRoundedTimeslot) - 1800);
            $stmt = $pdo->prepare('SELECT * FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot AND sid IS NOT NULL');
            $stmt->execute([':room' => $oldKey, ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
            $bookingPrev = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt = $pdo->prepare('UPDATE student SET fine = fine + 1 WHERE sid = :sid AND fine < 5');
            $stmt->execute([':sid' => $bookingPrev['sid']]);
            echo "      Fine increased for student {$bookingPrev['sid']}. \n";

            // Move room options + sid to new timeslot in booking
            echo "      Moving over options from old booking to current booking.\n";
            $previousTimeslot = date('H:i:00', strtotime($currentRoundedTimeslot) - 1800);
            $stmt = $pdo->prepare('UPDATE availabilities SET is_available = false, sid = :sid WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':sid' => $bookingPrev['sid'], ':room' => $oldKey, ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
            $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
            $stmt->execute([':room' => $bookingPrev['room'], ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
            $carryoverOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $stmt = $pdo->prepare('UPDATE room_options SET option_selected = true WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_name = :option_name');
            foreach ($carryoverOptions as $selectedOption) {
                $stmt->execute([':room' => $bookingPrev['room'], ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot, ':option_name' => $selectedOption]);
            }
            
            // Returnkey.php clone on previous timeslot (-30 minutes ago) but ignore stock options since new booking inherits them
            echo "      Removing old info and returning key. \n";
            $room = $oldKey;
            $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
            $stmt->execute([':room' => $room]);
            $issueTime = $stmt->fetchColumn();
            if ($issueTime !== false) {
                $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);
                $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
                $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));
                $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                $studentId = $stmt->fetchColumn();
                if ($studentId !== null) {
                    $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                    $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                    $stmt->execute([':room' => $room, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $studentId]);
                    $count = $stmt->fetchColumn();
                    if ($count === false) {
                        echo "          Error checking for student ID in availabilities.\n";
                    } elseif ($count === 0) {
                        $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $studentId]);
                        $fineAmount = $stmt->fetchColumn();
                        if ($fineAmount === false) {
                            echo "          Error fetching fine amount for student.\n";
                        } elseif ($fineAmount == 0) {
                            $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                            $stmt->execute([':studentId' => $studentId]);
                        } else {
                            echo "          Student not removed. Fine amount is not 0.\n";
                        }
                    } else {
                        echo "          Student not removed. Student ID still exists in availabilities.\n";
                    }
                    echo "          Booking removed successfully.\n";
                    $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                    $stmt->execute([':room' => $room]);
                } else {
                    $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true, issue_time = null WHERE room = :room');
                    $stmt->execute([':room' => $room]);
                    echo "          No booking found for room $room at $issueTimeslotTimeslot. Voided key returned.\n";
                }
            } else {
                echo "          No key issued for room $room.\n";
            }

            // Reissue room key (again ignore stock options)
            echo "      Reissuing key to extended room booking.\n";
            $issueTime = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
            $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $currentRoundedTimeslot]);
            $sid = $stmt->fetchColumn();
            if ($sid) {
                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = false, issue_time = :issue_time WHERE room = :room');
                $stmt->bindParam(':room', $room, PDO::PARAM_STR);
                $stmt->bindParam(':issue_time', $issueTime, PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() > 0) {
                    echo "          Key issued successfully for room $room at $issueTime.\n";
                } else {
                    echo "          Failed to issue key for room $room.\n";
                }
            } else {    
                echo "          No student booked for room $room at $currentRoundedTimeslot.\n";
            }
        }
    }
}

// Close the database connection
$pdo = null;

// _____________________________________________________________ Notes _____________________________________________________________ //

// Edge cases for issue dates and returns are ignored, especially for those keys that are not return and extended past available hours.
// We will chalk that up to user error if the key is not returned, especially when they are critical to study room issuance protocol.

// The program should not change anything when there are no bookings or keys currently issued. It will remain silent in this time.

// Multiple bookings may be handled at once by section 1 in the current timeslot. 
// Multiple tardy key returns may be handled at once by section 2 relating to the previous timeslot.

?>

