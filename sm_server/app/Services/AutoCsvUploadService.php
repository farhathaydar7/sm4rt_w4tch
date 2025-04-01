<?php

namespace App\Services;

use App\Jobs\ProcessCsvUploadJob;
use App\Models\User;
use App\Models\CsvUpload;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AutoCsvUploadService
{
    protected $csvUploadRepository;
    protected $sampleDataPath;

    /**
     * Create a new service instance.
     *
     * @param CsvUploadRepositoryInterface $csvUploadRepository
     */
    public function __construct(CsvUploadRepositoryInterface $csvUploadRepository)
    {
        $this->csvUploadRepository = $csvUploadRepository;
        $this->sampleDataPath = storage_path('app/sample_data/sample_activity_data.csv');
    }

    /**
     * Upload a sample CSV file for the user.
     *
     * @param User $user
     * @return CsvUpload|null
     */
    public function uploadSampleCsvForUser(User $user): ?CsvUpload
    {
        try {
            // Check if sample data exists
            if (!file_exists($this->sampleDataPath)) {
                $this->generateSampleData();
            }

            // Create a unique name for the stored file
            $destinationPath = 'csv_uploads/' . $user->id . '/' . Str::random(10) . '_activity_data.csv';

            // Store the file
            Storage::disk('public')->put($destinationPath, file_get_contents($this->sampleDataPath));

            // Create a record in the database
            $csvUpload = $this->csvUploadRepository->create([
                'user_id' => $user->id,
                'file_path' => $destinationPath,
                'status' => 'pending'
            ]);

            // Process the CSV file in the background
            ProcessCsvUploadJob::dispatch($csvUpload);

            Log::info('Auto-uploaded CSV file for user', [
                'user_id' => $user->id,
                'csv_upload_id' => $csvUpload->id
            ]);

            return $csvUpload;
        } catch (\Exception $e) {
            Log::error('Failed to auto-upload CSV file', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Generate sample data if it doesn't exist
     *
     * @return void
     */
    protected function generateSampleData(): void
    {
        // Create the directory if it doesn't exist
        if (!file_exists(dirname($this->sampleDataPath))) {
            mkdir(dirname($this->sampleDataPath), 0755, true);
        }

        // Generate sample CSV content with a variety of activity data
        $content = "user_id,date,steps,distance_km,active_minutes\n";

        // Get the current date
        $date = new \DateTime();

        // Add 30 days of data
        for ($i = 30; $i >= 1; $i--) {
            $day = clone $date;
            $day->modify("-$i days");

            // Random activity data with some variance
            $steps = rand(5000, 15000);
            $distance = round($steps * 0.0007, 2); // Approximate distance based on steps
            $activeMinutes = rand(30, 90);

            $content .= "{user_id},{$day->format('Y-m-d')},$steps,$distance,$activeMinutes\n";
        }

        // Save the sample data
        file_put_contents($this->sampleDataPath, $content);
    }
}
