<?php
// Include the Database class
require_once __DIR__ . '/../app/core/Database.php';

echo "Running Database Connection Test...\n";

// Test database connection using singleton pattern
$database = Database::getInstance();
$connection = $database->connect();

if ($connection) {
    echo "<h3>SUCCESS: Database connection established!</h3>\n";
} else {
    echo "ERROR: Failed to connect to database.\n";
    exit(1);
}

echo "Database test completed successfully.\n";

?>