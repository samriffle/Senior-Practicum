<?php
date_default_timezone_set('America/New_York');

// Include the database configuration file
include 'db_config.php';

$pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password");

// Get current date and timeslot
$currentDate = date('Y-m-d');
$currentTimeslot = date('H:i:00', floor(time() / 1800) * 1800); // Round down to the nearest 30-minute interval

echo "Booking Validation Start {$currentDate}," . date("H:i:s") . "\n";

// Get room tuples for current date and timeslot where is_available = false
$stmt = $pdo->prepare('SELECT * FROM availabilities WHERE date = :date AND timeslot = :timeslot AND is_available = false');
$stmt->execute([':date' => $currentDate, ':timeslot' => $currentTimeslot]);
$bookedRooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($bookedRooms) !== 0) {
    foreach ($bookedRooms as $room) {
        // Check if a key is issued or not issued for this room in the current timeslot
        $stmt = $pdo->prepare('SELECT * FROM room_keys WHERE room = :room AND date = :date AND timeslot = :timeslot AND key_available = false');
        $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentTimeslot]);
        $keyIssued = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($keyIssued) {
            echo "  Key issued\n";
            // Check if the current time is still within the timeslot the key was issued for
            $keyIssuedTimeslot = date('H:i:00', strtotime($keyIssued['issue_time']));
            $keyIssuedTimeslotRounded = date('H:i:00', floor(strtotime($keyIssued['issue_time']) / 1800) * 1800); // Round down to the nearest 30-minute interval
            $keyIssuedDatePart = date('Y-m-d', strtotime($keyIssued['issue_time']));

            if (strtotime($currentDate . ' ' . $currentTimeslot) <= strtotime($keyIssuedDatePart . ' ' . $keyIssuedTimeslotRounded)) {
                // Student is in room, return before timeslot over
                echo "  Student in room {$room['room']}. Return key before timeslot ({$currentDate}, {$currentTimeslot}) is over.\n";
            } else {
                echo "  Timeslot over and next booking needs pushed, but student hasn't left.\nFining and removing old booking, then returning key for current timeslot's booking.\n";
 
                // Fine the sid for being late returning the key when someone else needs it
                $stmt = $pdo->prepare('UPDATE student SET fine = fine + 1 WHERE sid = :sid AND fine < 5');
                $stmt->execute([':sid' => $room['sid']]);

                // Return_key.php clone removes old booking since theyre late and they cant get an immediate extension since a new person wants in. 
                $rroom = $room['room'];

                // Get the issue_time for the room from room_keys
                $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
                $stmt->execute([':room' => $rroom]);
                $issueTime = $stmt->fetchColumn();

                if ($issueTime !== false) {
                    // Get the current timestamp rounded down to the nearest 30-minute increment
                    $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);

                    // Split the issueTimeSlot into date and timeslot
                    $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
                    $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));

                    // Check if there was a booking at the specified key issue time for the room
                    $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                    $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                    $studentId = $stmt->fetchColumn();

                    if ($studentId !== null) {
                        // Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
                        $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                        $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

                        // Get the list of option_names that were selected for the booking
                        $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
                        $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                        $selectedOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        // Update the stock for each selected option
                        $stmt = $pdo->prepare('UPDATE options SET stock = stock + 1 WHERE option_name = :option_name');
                        foreach ($selectedOptions as $option) {
                            $stmt->execute([':option_name' => $option]);
                        }

                        // Reset room_options to set option_selected false for the selected room, date, and timeslot
                        $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                        $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

                        // Check if there are any more instances of the student ID in availabilities
                        $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $studentId]);
                        $count = $stmt->fetchColumn();

                        if ($count === false) {
                            // Handle error
                            die('Error checking for student ID in availabilities.');
                        } elseif ($count === 0) {
                            // If there are no more instances of the student ID, check if the fine amount is 0
                            $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                            $stmt->execute([':studentId' => $studentId]);
                            $fineAmount = $stmt->fetchColumn();

                            if ($fineAmount === false) {
                                // Handle error
                                die('Error fetching fine amount for student.');
                            } elseif ($fineAmount == 0) {
                                // If fine amount is 0, remove the student from the student table
                                $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                                $stmt->execute([':studentId' => $studentId]);
                            } else {
                                echo '      Student not removed. Fine amount is not 0.';
                            }
                        } else {
                            echo '      Student not removed. Student ID still exists in availabilities.';
                        }

                        echo '      Booking removed successfully.';

                        // Return the room key since the room has been cleared
                        $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                        $stmt->execute([':room' => $rroom]);
                    } else {
                        // Room was not booked at the specified time, set key_available back to true for the room
                        $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                        $stmt->execute([':room' => $rroom]);
            
                        // Return an error message
                        echo "  No booking found for room $rroom at $issueTimeslotTimeslot. Voided key returned.";

                        // Close the database connection
                        $pdo = null;
                        exit(); // Exit the script
                    }
                } else {
                    // No key issued for the room, return an error message
                    echo "  No key issued for room $rroom.";
                }

            }
        } else {
            echo "  Key not issued\n";
            // Keys not issued yet (late arriving, but still in timeslot)
            $currentTimestamp = date('Y-m-d H:i:s');
            $bookingStartTimestamp = $currentDate . ' ' . $room['timeslot'];
            $bookingStartTimestamp = date('Y-m-d H:i:s', strtotime($bookingStartTimestamp));
            $timeDiff = strtotime($currentTimestamp) - strtotime($bookingStartTimestamp);
            // Remove booking so key cant be issued later for this timeslot when the student is too tardy
            if ($timeDiff >= 600) { // 10 minutes in seconds
                echo "  Timeout counter expired. Removing tardy booking.\n";
                // Remove the booking and update availabilities
                $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentTimeslot]);

                // Reset room options
                $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                $stmt->execute([':room' => $room['room'], ':date' => $currentDate, ':timeslot' => $currentTimeslot]);

                // Check if there are any more instances of the student ID in availabilities
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                $stmt->execute([':studentId' => $room['sid']]);
                $count = $stmt->fetchColumn();

                if ($count === false) {
                    // Handle error
                    die('Error checking for student ID in availabilities.');
                } elseif ($count === 0) {
                    // If there are no more instances of the student ID, check if the fine amount is 0
                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                    $stmt->execute([':studentId' => $room['sid']]);
                    $fineAmount = $stmt->fetchColumn();

                    if ($fineAmount === false) {
                        // Handle error
                        die('Error fetching fine amount for student.');
                    } elseif ($fineAmount == 0) {
                        // If fine amount is 0, remove the student from the student table
                        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                        $stmt->execute([':studentId' => $room['sid']]);
                    } else {
                        echo '      Student not removed. Fine amount is not 0.';
                    }
                } else {
                    echo '      Student not removed. Student ID still exists in availabilities.';
                }

                echo "      Tardy booking removed successfully for room {$room['room']}.";
            } else {
                echo "  Timeout counter still in effect. Key not issued for room {$room['room']}. No action taken.";
            }
        }
    }
} else {
    echo "  No bookings at this current time. Lets check and see if there are any old keys still issued.\n";
    // Get distinct room keys that are currently issued and not available
    $stmt = $pdo->prepare('SELECT DISTINCT room FROM room_keys WHERE key_available = false');
    $stmt->execute();
    $issuedRoomKeys = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($issuedRoomKeys) !== 0) {
        foreach ($issuedRoomKeys as $room) {
            echo "      Late key from room: {$room}\n";

            // Get the 30-minute interval before the current timeslot
            $previousTimeslot = date('H:i:00', strtotime($currentTimeslot) - 1800); 
            echo "      Previous Timeslot Check: {$previousTimeslot}\n";

            // Get sid from late booking for fine
            $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND sid IS NOT NULL AND date = :date and timeslot = :timeslot');
            $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
            $selectedSid = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($selectedSid) !== 0) {
                foreach ($selectedSid as $sid) {
                    echo "      Tardy sid: {$sid}\n";
                    echo "      Current datetime: ({$currentDate} {$currentTimeslot})\n";

                    // Check if the fine is already at $5
                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :sid AND fine >= 5');
                    $stmt->execute([':sid' => $sid]);
                    $fine_limit_reached = $stmt->fetch(PDO::FETCH_ASSOC);

                    // Fine the sid
                    $stmt = $pdo->prepare('UPDATE student SET fine = fine + 1 WHERE sid = :sid AND fine < 5');
                    $stmt->execute([':sid' => $sid]);

                    // Check if current (technically the next) timeslot is available for the room
                    $stmt = $pdo->prepare('SELECT * FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot AND sid IS NULL AND is_blocked = false');
                    $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot]); // Prevents day wraparounds
                    $nextTimeslotAvailable = $stmt->fetch(PDO::FETCH_ASSOC) !== false;
                
                    if ($nextTimeslotAvailable && !$fine_limit_reached) {
                        echo "      Current datetime available to extend booking with fine!\n";

                        // Book the sid on the current rounded timselot since its the next timeslot and its available
                        $stmt = $pdo->prepare('UPDATE availabilities SET is_available = false, sid = :sid WHERE room = :room AND date = :date AND timeslot = :timeslot');
                        $stmt->execute([':sid' => $sid, ':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot]);

                        // Carry over selected options to new booking
                        $stmt = $pdo->prepare('SELECT DISTINCT option_name FROM room_options WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_selected = true');
                        $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $previousTimeslot]);
                        $carryoverOptions = $stmt->fetchAll(PDO::FETCH_COLUMN);

                        // Update room options with option_selected true for selected options
                        $stmt = $pdo->prepare('UPDATE room_options SET option_selected = true WHERE room = :room AND date = :date AND timeslot = :timeslot AND option_name = :option_name');
                        foreach ($carryoverOptions as $selectedOption) {
                            $stmt->execute([':room' => $room, ':date' => $currentDate, ':timeslot' => $currentTimeslot, ':option_name' => $selectedOption]);
                        }

                        // New booking carryover done. Skip stock update since its carrying over

                        // Return old key like return_key.php (ignore increasing stock since carryover keeps em)
                        $rroom = $room;
                        $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
                        $stmt->execute([':room' => $rroom]);
                        $issueTime = $stmt->fetchColumn();
                        if ($issueTime !== false) {
                            $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);
                            $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
                            $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));
                            $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                            $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                            $studentId = $stmt->fetchColumn();
                            if ($studentId !== false) {
                                $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                                $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                                $stmt->execute([':studentId' => $studentId]);
                                $count = $stmt->fetchColumn();
                                if ($count === false) {
                                    die('Error checking for student ID in availabilities.');
                                } elseif ($count === 0) {
                                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                                    $stmt->execute([':studentId' => $studentId]);
                                    $fineAmount = $stmt->fetchColumn();
                                    if ($fineAmount === false) {
                                        die('Error fetching fine amount for student.');
                                    } elseif ($fineAmount == 0) {
                                        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                                        $stmt->execute([':studentId' => $studentId]);
                                    } else {
                                        echo '          Student not removed. Fine amount is not 0.';
                                    }
                                } else {
                                    echo '          Student not removed. Student ID still exists in availabilities.';
                                }
                                echo '          Booking removed successfully.';
                                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                                $stmt->execute([':room' => $rroom]);
                            } else {
                                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                                $stmt->execute([':room' => $rroom]);
                                echo "          No booking found for room $rroom at $issueTimeslotTimeslot. Voided key returned. This shouldnt be possible to get to.";
                            }
                        } else {
                            echo "          No key issued for room $rroom.";
                        }

                        // Reissue new key for the extended booking
                        $issueTime = date('Y-m-d H:i:s');
                        $currentDate = date('Y-m-d');
                        $currentTimeslot = date('H:i:00', floor(time() / 1800) * 1800); 
                        $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                        $stmt->execute([':room' => $rroom, ':date' => $currentDate, ':timeslot' => $currentTimeslot]);
                        $sid = $stmt->fetchColumn();
                        if ($sid) {
                            $stmt = $pdo->prepare('UPDATE room_keys SET key_available = false, issue_time = :issue_time WHERE room = :room');
                            $stmt->bindParam(':room', $rroom, PDO::PARAM_STR);
                            $stmt->bindParam(':issue_time', $issueTime, PDO::PARAM_STR);
                            $stmt->execute();
                            if ($stmt->rowCount() > 0) {
                                echo "          Key issued successfully for room $rroom at $issueTime.";
                            } else {
                                echo "          Failed to issue key for room $rroom.";
                            }
                        } else {    
                            echo "          No student booked for room $rroom at $currentTimeslot.";
                        }
                    } else {
                        echo "          Fine limit reached. Cannot reschedule again. Removing booking and closing.\n";
                        
                        // Fine the sid for being late returning the key when someone else needs it
                        $stmt = $pdo->prepare('UPDATE student SET fine = fine + 1 WHERE sid = :sid AND fine < 5');
                        $stmt->execute([':sid' => $sid]);

                        // Return_key.php clone removes old booking since theyre late and they cant get an immediate extension since a new person wants in. 
                        $rroom = $room;

                        // Get the issue_time for the room from room_keys
                        $stmt = $pdo->prepare('SELECT DISTINCT issue_time FROM room_keys WHERE room = :room AND key_available = false');
                        $stmt->execute([':room' => $rroom]);
                        $issueTime = $stmt->fetchColumn();

                        if ($issueTime !== false) {
                            // Get the current timestamp rounded down to the nearest 30-minute increment
                            $issueTimeSlot = date('Y-m-d H:i:00', floor(strtotime($issueTime) / 1800) * 1800);

                            // Split the issueTimeSlot into date and timeslot
                            $issueTimeslotDate = date('Y-m-d', strtotime($issueTimeSlot));
                            $issueTimeslotTimeslot = date('H:i:00', strtotime($issueTimeSlot));

                            // Check if there was a booking at the specified key issue time for the room
                            $stmt = $pdo->prepare('SELECT sid FROM availabilities WHERE room = :room AND date = :date AND timeslot = :timeslot');
                            $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);
                            $studentId = $stmt->fetchColumn();

                            if ($studentId !== null) {
                                // Update availabilities to set is_available true and remove the student ID for the selected room, date, and timeslot
                                $stmt = $pdo->prepare('UPDATE availabilities SET is_available = true, sid = NULL WHERE room = :room AND date = :date AND timeslot = :timeslot');
                                $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

                                // Reset room_options to set option_selected false for the selected room, date, and timeslot
                                $stmt = $pdo->prepare('UPDATE room_options SET option_selected = false WHERE room = :room AND date = :date AND timeslot = :timeslot');
                                $stmt->execute([':room' => $rroom, ':date' => $issueTimeslotDate, ':timeslot' => $issueTimeslotTimeslot]);

                                // Check if there are any more instances of the student ID in availabilities
                                $stmt = $pdo->prepare('SELECT COUNT(*) FROM availabilities WHERE sid = :studentId');
                                $stmt->execute([':studentId' => $studentId]);
                                $count = $stmt->fetchColumn();

                                if ($count === false) {
                                    // Handle error
                                    die('Error checking for student ID in availabilities.');
                                } elseif ($count === 0) {
                                    // If there are no more instances of the student ID, check if the fine amount is 0
                                    $stmt = $pdo->prepare('SELECT fine FROM student WHERE sid = :studentId');
                                    $stmt->execute([':studentId' => $studentId]);
                                    $fineAmount = $stmt->fetchColumn();

                                    if ($fineAmount === false) {
                                        // Handle error
                                        die('Error fetching fine amount for student.');
                                    } elseif ($fineAmount == 0) {
                                        // If fine amount is 0, remove the student from the student table
                                        $stmt = $pdo->prepare('DELETE FROM student WHERE sid = :studentId');
                                        $stmt->execute([':studentId' => $studentId]);
                                    } else {
                                        echo '      Student not removed. Fine amount is not 0.';
                                    }
                                } else {
                                    echo '      Student not removed. Student ID still exists in availabilities.';
                                }

                                echo '      Booking removed successfully.';

                                // Return the room key since the room has been cleared
                                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                                $stmt->execute([':room' => $rroom]);
                            } else {
                                // Room was not booked at the specified time, set key_available back to true for the room
                                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                                $stmt->execute([':room' => $rroom]);
            
                                // Return an error message
                                echo "  No booking found for room $rroom at $issueTimeslotTimeslot. Voided key returned.";

                                // Close the database connection
                                $pdo = null;
                                exit(); // Exit the script
                            }
                        } else {
                            // No key issued for the room, return an error message
                            echo "  No key issued for room $rroom.";
                        }
                    }
                }
            } else {
                $stmt = $pdo->prepare('UPDATE room_keys SET key_available = true WHERE room = :room');
                $stmt->execute([':room' => $room]);
                echo "Removed void room key.\n";
            }
        }
    } else {
        echo "  There are not.\n";
    }
}

// Close the database connection
$pdo = null;
?>
