<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    // Get connection
    $connection = \Illuminate\Support\Facades\DB::connection();

    // Check status column type
    $statusInfo = $connection->select("SHOW COLUMNS FROM csv_uploads WHERE Field = 'status'");

    if (!empty($statusInfo)) {
        echo "Status column type: " . $statusInfo[0]->Type . "\n";
    } else {
        echo "Status column not found!\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
