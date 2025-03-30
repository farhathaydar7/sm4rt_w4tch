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

            // Skip header row
            $headers = fgetcsv($file);

            // Prepare data for batch insert
            $metricsData = [];

            while (($row = fgetcsv($file)) !== false) {
                // Map CSV columns to database fields
                // This is simplified and would need to be adapted to match your actual CSV structure
                $date = $this->parseDate($row[0] ?? null);
                $steps = isset($row[1]) ? (int)$row[1] : null;
                $distance = isset($row[2]) ? (float)$row[2] : null;
                $activeMinutes = isset($row[3]) ? (int)$row[3] : null;

                if (!$date) {
                    continue; // Skip rows with invalid dates
                }

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

            fclose($file);

            // Insert metrics in database
            if (!empty($metricsData)) {
                $this->activityMetricRepository->createMany($metricsData);
            }

            // Mark upload as processed
            $csvUpload->status = 'processed';
            $csvUpload->save();

            // Generate predictions (in a real app, this would be a separate job)
            // $this->predictionService->generatePredictions($csvUpload);

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
