<?php

namespace App\Services;

use App\Models\CsvUpload;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CsvProcessingService
{
    protected $activityMetricRepository;

    public function __construct(ActivityMetricRepositoryInterface $activityMetricRepository)
    {
        $this->activityMetricRepository = $activityMetricRepository;
    }

    /**
     * Process a CSV file and create activity metrics
     *
     * @param CsvUpload $csvUpload
     * @return bool
     */
    public function processCsvFile(CsvUpload $csvUpload): bool
    {
        try {
            // Mark upload as processing
            $csvUpload->status = 'processing';
            $csvUpload->save();

            // Read and parse the CSV file
            $filePath = Storage::disk('public')->path($csvUpload->file_path);

            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $file = fopen($filePath, 'r');
            if ($file === false) {
                throw new \Exception("Unable to open the CSV file: {$filePath}");
            }

            // Skip header row (expects: user_id,date,steps,distance_km,active_minutes)
            $headers = fgetcsv($file);
            if ($headers === false || count($headers) < 5) {
                throw new \Exception("Invalid CSV file format. Expected header row with at least 5 columns.");
            }

            // Prepare data for batch insert
            $metricsData = [];
            $rowCount = 0;
            $errorCount = 0;

            while (($row = fgetcsv($file)) !== false) {
                $rowCount++;

                // Skip rows that don't have enough columns
                if (count($row) < 5) {
                    $errorCount++;
                    Log::warning("Skipping row {$rowCount}: Not enough columns", [
                        'csv_upload_id' => $csvUpload->id,
                        'row_data' => $row
                    ]);
                    continue;
                }

                try {
                    // Map CSV columns to database fields (user_id,date,steps,distance_km,active_minutes)
                    $userId = trim($row[0]);

                    // Replace placeholder {user_id} with the actual user ID
                    if ($userId === "{user_id}") {
                        $userId = $csvUpload->user_id;
                    }

                    $date = $this->parseDate(trim($row[1]));
                    $steps = intval($row[2]);
                    $distance = floatval($row[3]);
                    $activeMinutes = intval($row[4]);

                    if (!$date) {
                        $errorCount++;
                        Log::warning("Skipping row {$rowCount}: Invalid date format", [
                            'csv_upload_id' => $csvUpload->id,
                            'date_value' => $row[1]
                        ]);
                        continue;
                    }

                    // Validate user_id matches the upload owner (optional security check)
                    // This is a business decision - you might want to allow multiple users in one file
                    // or restrict to only the upload owner's data
                    if ($userId != $csvUpload->user_id) {
                        $errorCount++;
                        Log::warning("Skipping row {$rowCount}: User ID mismatch", [
                            'csv_upload_id' => $csvUpload->id,
                            'row_user_id' => $userId,
                            'upload_user_id' => $csvUpload->user_id
                        ]);
                        continue;
                    }

                    // Check if a record with this user_id and activity_date already exists
                    $existingMetric = $this->activityMetricRepository->findByUserAndDate($csvUpload->user_id, $date);

                    if ($existingMetric) {
                        // Update the existing record
                        $this->activityMetricRepository->update($existingMetric->id, [
                            'csv_upload_id' => $csvUpload->id,
                            'steps' => $steps,
                            'distance' => $distance,
                            'active_minutes' => $activeMinutes,
                            'updated_at' => now()
                        ]);
                    } else {
                        // Add to batch for creation
                        $metricsData[] = [
                            'user_id' => $csvUpload->user_id,
                            'csv_upload_id' => $csvUpload->id,
                            'activity_date' => $date,
                            'steps' => $steps,
                            'distance' => $distance,
                            'active_minutes' => $activeMinutes,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }

                    // Process in batches of 1000 to avoid memory issues with large files
                    if (count($metricsData) >= 1000) {
                        $this->activityMetricRepository->createMany($metricsData);
                        $metricsData = [];
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    Log::warning("Error processing row {$rowCount}: " . $e->getMessage(), [
                        'csv_upload_id' => $csvUpload->id,
                        'row_data' => $row
                    ]);
                }
            }

            fclose($file);

            // Insert any remaining metrics in database
            if (!empty($metricsData)) {
                $this->activityMetricRepository->createMany($metricsData);
            }

            // If there were any errors during processing, mark as partially processed
            if ($errorCount > 0 && $rowCount > $errorCount) {
                $csvUpload->status = 'partially_processed';
                $csvUpload->save();

                Log::info("CSV file partially processed with {$errorCount} errors out of {$rowCount} rows", [
                    'csv_upload_id' => $csvUpload->id
                ]);

                return true;
            } else if ($errorCount > 0 && $rowCount <= $errorCount) {
                // If all rows had errors, mark as failed
                $csvUpload->status = 'failed';
                $csvUpload->save();

                Log::error("CSV processing failed - all rows had errors", [
                    'csv_upload_id' => $csvUpload->id
                ]);

                return false;
            }

            // Mark upload as processed
            $csvUpload->status = 'processed';
            $csvUpload->save();

            Log::info("CSV file successfully processed with {$rowCount} rows", [
                'csv_upload_id' => $csvUpload->id
            ]);

            return true;
        } catch (\Exception $e) {
            // Log the error
            Log::error('CSV Processing Error: ' . $e->getMessage(), [
                'csv_upload_id' => $csvUpload->id,
                'file_path' => $csvUpload->file_path,
                'trace' => $e->getTraceAsString()
            ]);

            // Mark upload as failed
            $csvUpload->status = 'failed';
            $csvUpload->save();

            return false;
        }
    }

    /**
     * Parse a date string from CSV
     *
     * @param string|null $dateString
     * @return string|null
     */
    protected function parseDate(?string $dateString): ?string
    {
        if (empty($dateString)) {
            return null;
        }

        try {
            // Try different date formats
            $date = \DateTime::createFromFormat('Y-m-d', $dateString);

            if (!$date) {
                $date = \DateTime::createFromFormat('m/d/Y', $dateString);
            }

            if (!$date) {
                $date = \DateTime::createFromFormat('d/m/Y', $dateString);
            }

            if ($date) {
                return $date->format('Y-m-d');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
