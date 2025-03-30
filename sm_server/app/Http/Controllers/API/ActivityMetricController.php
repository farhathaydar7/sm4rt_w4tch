<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityMetricResource;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityMetricController extends Controller
{
    protected $activityMetricRepository;

    public function __construct(ActivityMetricRepositoryInterface $activityMetricRepository)
    {
        $this->activityMetricRepository = $activityMetricRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Check if date range filters are applied
        if ($request->has(['start_date', 'end_date'])) {
            $metrics = $this->activityMetricRepository->getByDateRange(
                $userId,
                $request->start_date,
                $request->end_date
            );
        } else {
            $metrics = $this->activityMetricRepository->getByUserId($userId);
        }

        return response()->json([
            'data' => ActivityMetricResource::collection($metrics)
        ]);
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
        $metric = $this->activityMetricRepository->find($id);

        if (!$metric || $metric->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Activity metric not found'
            ], 404);
        }

        return response()->json([
            'data' => new ActivityMetricResource($metric)
        ]);
    }

    /**
     * Get metrics by CSV upload ID.
     *
     * @param int $csvUploadId
     * @param Request $request
     * @return JsonResponse
     */
    public function getByCsvUpload(int $csvUploadId, Request $request): JsonResponse
    {
        // First check if the user has access to this CSV upload
        // This would typically be handled in a middleware or policy

        $metrics = $this->activityMetricRepository->getByCsvUploadId($csvUploadId);

        return response()->json([
            'data' => ActivityMetricResource::collection($metrics)
        ]);
    }
}
