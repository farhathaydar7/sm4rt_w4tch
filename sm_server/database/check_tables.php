<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check if the csv_uploads table exists and its structure
try {
    $tableExists = \Illuminate\Support\Facades\Schema::hasTable('csv_uploads');
    echo "CSV Uploads table exists: " . ($tableExists ? "Yes" : "No") . "\n";

    if ($tableExists) {
        // Get columns
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('csv_uploads');
        echo "Columns: " . implode(", ", $columns) . "\n";

        // Check status column type
        $connection = \Illuminate\Support\Facades\DB::connection();
        $statusType = null;

        foreach($connection->select("SHOW COLUMNS FROM csv_uploads WHERE Field = 'status'") as $column) {
            $statusType = $column->Type;
        }

        echo "Status column type: " . $statusType . "\n";

        // Print records
        $uploads = \Illuminate\Support\Facades\DB::table('csv_uploads')->get();
        echo "Number of records: " . count($uploads) . "\n";

        if (count($uploads) > 0) {
            foreach ($uploads as $upload) {
                echo "ID: {$upload->id}, User ID: {$upload->user_id}, Status: {$upload->status}, File: {$upload->file_path}\n";
            }
        }
    }

    // Check activity_metrics table
    $activityMetricsExists = \Illuminate\Support\Facades\Schema::hasTable('activity_metrics');
    echo "\nActivity Metrics table exists: " . ($activityMetricsExists ? "Yes" : "No") . "\n";

    if ($activityMetricsExists) {
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing('activity_metrics');
        echo "Columns: " . implode(", ", $columns) . "\n";

        $metrics = \Illuminate\Support\Facades\DB::table('activity_metrics')->count();
        echo "Number of records: " . $metrics . "\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
