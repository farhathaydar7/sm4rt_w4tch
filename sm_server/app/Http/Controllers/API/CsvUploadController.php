<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCsvUploadRequest;
use App\Http\Resources\CsvUploadResource;
use App\Jobs\ProcessCsvUploadJob;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use App\Services\CsvProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CsvUploadController extends Controller
{
    protected $csvUploadRepository;
    protected $csvProcessingService;

    public function __construct(
        CsvUploadRepositoryInterface $csvUploadRepository,
        CsvProcessingService $csvProcessingService
    ) {
        $this->csvUploadRepository = $csvUploadRepository;
        $this->csvProcessingService = $csvProcessingService;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $csvUploads = $this->csvUploadRepository->getByUserId($request->user()->id);

        return response()->json([
            'data' => CsvUploadResource::collection($csvUploads)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCsvUploadRequest $request
     * @return JsonResponse
     */
    public function store(StoreCsvUploadRequest $request): JsonResponse
    {
        try {
            // Validate the CSV file structure
            $file = $request->file('csv_file');

            // Check if file is readable
            if (!$file->isReadable()) {
                return response()->json([
                    'error' => 'The uploaded file is not readable.'
                ], 400);
            }

            // Check CSV structure (at least the header row)
            $csvHandle = fopen($file->getRealPath(), 'r');
            if ($csvHandle === false) {
                return response()->json([
                    'error' => 'Unable to open the CSV file for validation.'
                ], 400);
            }

            $headers = fgetcsv($csvHandle);
            fclose($csvHandle);

            if ($headers === false || count($headers) < 5) {
                return response()->json([
                    'error' => 'Invalid CSV format. Expected header row with columns: user_id, date, steps, distance_km, active_minutes'
                ], 400);
            }

            // Store the uploaded file in a user-specific directory
            $path = $file->store('csv_uploads/' . $request->user()->id, 'public');

            if (!$path) {
                Log::error('Failed to store CSV file', [
                    'user_id' => $request->user()->id,
                    'file_name' => $file->getClientOriginalName()
                ]);

                return response()->json([
                    'error' => 'Failed to store the uploaded file. Please try again.'
                ], 500);
            }

            // Create record in database with 'pending' status
            $csvUpload = $this->csvUploadRepository->create([
                'user_id' => $request->user()->id,
                'file_path' => $path,
                'status' => 'pending'
            ]);

            // Dispatch a job to process the CSV file in the background
            ProcessCsvUploadJob::dispatch($csvUpload);

            return response()->json([
                'data' => new CsvUploadResource($csvUpload),
                'message' => 'CSV file uploaded successfully and queued for processing'
            ], 202); // 202 Accepted - tells the client we've accepted the request but processing is async
        } catch (\Exception $e) {
            Log::error('CSV upload error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while processing your request. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $csvUpload = $this->csvUploadRepository->find($id);

        if (!$csvUpload || $csvUpload->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'CSV upload not found'
            ], 404);
        }

        return response()->json([
            'data' => new CsvUploadResource($csvUpload)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $csvUpload = $this->csvUploadRepository->find($id);

        if (!$csvUpload || $csvUpload->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'CSV upload not found'
            ], 404);
        }

        try {
            // Delete the file
            if (Storage::disk('public')->exists($csvUpload->file_path)) {
                Storage::disk('public')->delete($csvUpload->file_path);
            }

            // Delete the record (and related activity metrics due to cascade)
            $this->csvUploadRepository->delete($id);

            return response()->json([
                'message' => 'CSV upload deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('CSV delete error: ' . $e->getMessage(), [
                'user_id' => $request->user()->id,
                'csv_upload_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'An error occurred while deleting the upload. Please try again.'
            ], 500);
        }
    }

    /**
     * Get the processing status of a CSV upload.
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function checkStatus(int $id, Request $request): JsonResponse
    {
        $csvUpload = $this->csvUploadRepository->find($id);

        if (!$csvUpload || $csvUpload->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'CSV upload not found'
            ], 404);
        }

        // Return the processing status
        return response()->json([
            'data' => [
                'id' => $csvUpload->id,
                'status' => $csvUpload->status,
                'created_at' => $csvUpload->created_at,
                'updated_at' => $csvUpload->updated_at
            ]
        ]);
    }
}
