<?php

namespace App\Jobs;

use App\Models\CsvUpload;
use App\Services\CsvProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessCsvUploadJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 60;

    /**
     * The CSV upload instance.
     *
     * @var \App\Models\CsvUpload
     */
    protected $csvUpload;

    /**
     * Create a new job instance.
     *
     * @param  \App\Models\CsvUpload  $csvUpload
     * @return void
     */
    public function __construct(CsvUpload $csvUpload)
    {
        $this->csvUpload = $csvUpload;
    }

    /**
     * Execute the job.
     *
     * @param  \App\Services\CsvProcessingService  $csvProcessingService
     * @return void
     */
    public function handle(CsvProcessingService $csvProcessingService)
    {
        Log::info('Starting CSV processing job', [
            'csv_upload_id' => $this->csvUpload->id,
            'user_id' => $this->csvUpload->user_id
        ]);

        $result = $csvProcessingService->processCsvFile($this->csvUpload);

        if ($result) {
            Log::info('CSV processing job completed successfully', [
                'csv_upload_id' => $this->csvUpload->id
            ]);
        } else {
            Log::error('CSV processing job failed', [
                'csv_upload_id' => $this->csvUpload->id,
                'status' => $this->csvUpload->status
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        Log::error('CSV processing job failed with exception', [
            'csv_upload_id' => $this->csvUpload->id,
            'exception' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Update the CSV upload status to failed
        $this->csvUpload->status = 'failed';
        $this->csvUpload->save();
    }
}
