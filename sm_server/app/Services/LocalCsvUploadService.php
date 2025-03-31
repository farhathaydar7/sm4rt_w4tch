<?php

namespace App\Services;

use App\Jobs\ProcessCsvUploadJob;
use App\Models\User;
use App\Models\CsvUpload;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LocalCsvUploadService
{
    protected $csvUploadRepository;
    protected $localFilePath;

    /**
     * Create a new service instance.
     *
     * @param CsvUploadRepositoryInterface $csvUploadRepository
     */
    public function __construct(CsvUploadRepositoryInterface $csvUploadRepository)
    {
        $this->csvUploadRepository = $csvUploadRepository;
        $this->localFilePath = public_path('smrt.csv');
    }

    /**
     * Upload the local CSV file for the user if it exists and is valid.
     *
     * @param User $user
     * @return CsvUpload|null
     */
    public function uploadLocalCsvForUser(User $user): ?CsvUpload
    {
        try {
            // Check if local file exists
            if (!file_exists($this->localFilePath)) {
                Log::info('Local CSV file not found', [
                    'path' => $this->localFilePath,
                    'user_id' => $user->id
                ]);
                return null;
            }

            // Validate the file content - at least check if it's a valid CSV with the right header
            if (!$this->validateCsvFormat($this->localFilePath, $user->id)) {
                Log::warning('Invalid CSV format or user ID mismatch', [
                    'path' => $this->localFilePath,
                    'user_id' => $user->id
                ]);
                return null;
            }

            // Create a unique name for the stored file
            $destinationPath = 'csv_uploads/' . $user->id . '/' . Str::random(10) . '_activity_data.csv';

            // Store the file in the storage directory
            Storage::disk('public')->put($destinationPath, file_get_contents($this->localFilePath));

            // Create a record in the database
            $csvUpload = $this->csvUploadRepository->create([
                'user_id' => $user->id,
                'file_path' => $destinationPath,
                'status' => 'pending'
            ]);

            // Process the CSV file in the background
            ProcessCsvUploadJob::dispatch($csvUpload);

            Log::info('Uploaded local CSV file for user', [
                'user_id' => $user->id,
                'csv_upload_id' => $csvUpload->id
            ]);

            return $csvUpload;
        } catch (\Exception $e) {
            Log::error('Failed to upload local CSV file', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return null;
        }
    }

    /**
     * Validate the CSV file format and check if it contains data for the current user.
     *
     * @param string $filePath
     * @param int $userId
     * @return bool
     */
    protected function validateCsvFormat(string $filePath, int $userId): bool
    {
        if (($handle = fopen($filePath, 'r')) === false) {
            return false;
        }

        // Check header row
        $header = fgetcsv($handle);
        if ($header === false || count($header) < 5 ||
            $header[0] !== 'user_id' ||
            $header[1] !== 'date' ||
            $header[2] !== 'steps' ||
            $header[3] !== 'distance_km' ||
            $header[4] !== 'active_minutes') {
            fclose($handle);
            return false;
        }

        // Check at least one data row for the user
        $hasValidData = false;
        while (($row = fgetcsv($handle)) !== false) {
            // Skip comment lines
            if (isset($row[0]) && substr(trim($row[0]), 0, 1) === '#') {
                continue;
            }

            // Skip rows that don't have enough columns
            if (count($row) < 5) {
                continue;
            }

            $rowUserId = trim($row[0]);
            // If at least one row has the correct user ID, the file is considered valid
            if ($rowUserId == $userId) {
                $hasValidData = true;
                break;
            }
        }

        fclose($handle);
        return $hasValidData;
    }
}
