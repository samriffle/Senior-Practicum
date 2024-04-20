#!/bin/bash

# Get the directory of the script
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"

# Copy db_config.php to /client
cp "$DIR/db_config.php" "$DIR/client/"

# Copy db_config.php to /database
cp "$DIR/db_config.php" "$DIR/database/"

# Copy db_config.php to /admin
cp "$DIR/db_config.php" "$DIR/admin/"

# Copy db_config.php to /auth
cp "$DIR/db_config.php" "$DIR/auth/"

# Copy db_config.php to /server/x64/Release
cp "$DIR/db_config.php" "$DIR/server/x64/Release/"

echo "db_config.php copied to all directories."
