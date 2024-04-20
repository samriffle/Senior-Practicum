#!/bin/bash

while true
do
    # Run PHP scripts and redirect output to log file
    php server/x64/Release/check_bookings.php >> ThorneServer.log
    php server/x64/Release/reset_old_timeslots.php >> ThorneServer.log

    # Generate HTML slides as images using Python scripts and redirect output to log file
    python server/x64/Release/create_slide1.py >> ThorneServer.log
    echo "Monitor slides generated in ~/server/presentation" >> ThorneServer.log

    # Wait for 60 seconds before the next iteration
    sleep 60
done
