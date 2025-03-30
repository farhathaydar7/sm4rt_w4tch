<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCsvUploadRequest;
use App\Http\Resources\CsvUploadResource;
use App\Repositories\Interfaces\CsvUploadRepositoryInterface;
use App\Services\CsvProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
        // Store the uploaded file
        $file = $request->file('csv_file');
        $path = $file->store('csv_uploads/' . $request->user()->id, 'public');

        // Create record in database
        $csvUpload = $this->csvUploadRepository->create([
            'user_id' => $request->user()->id,
            'file_path' => $path,
            'status' => 'pending'
        ]);

        // Process the CSV file (this would typically be done in a background job in a real application)
        $this->csvProcessingService->processCsvFile($csvUpload);

        return response()->json([
            'data' => new CsvUploadResource($csvUpload),
            'message' => 'CSV file uploaded successfully'
        ], 201);
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

        // Delete the file
        if (Storage::disk('public')->exists($csvUpload->file_path)) {
            Storage::disk('public')->delete($csvUpload->file_path);
        }

        // Delete the record
        $this->csvUploadRepository->delete($id);

        return response()->json([
            'message' => 'CSV upload deleted successfully'
        ]);
    }
}
