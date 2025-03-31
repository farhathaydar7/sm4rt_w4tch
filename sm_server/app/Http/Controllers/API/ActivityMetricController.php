<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityMetricResource;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

    /**
     * Get daily activity metrics for the current day
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function daily(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $today = Carbon::today()->toDateString();

        $metrics = $this->activityMetricRepository->getByDateRange(
            $userId,
            $today,
            $today
        );

        // Calculate totals
        $totalSteps = $metrics->sum('steps');
        $totalDistance = $metrics->sum('distance');
        $totalCalories = $metrics->sum('calories');

        return response()->json([
            'date' => $today,
            'total_steps' => $totalSteps,
            'total_distance' => $totalDistance,
            'total_calories' => $totalCalories,
            'records' => ActivityMetricResource::collection($metrics)
        ]);
    }

    /**
     * Get weekly activity metrics for the current week
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function weekly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfWeek = Carbon::now()->startOfWeek()->toDateString();
        $endOfWeek = Carbon::now()->endOfWeek()->toDateString();

        $metrics = $this->activityMetricRepository->getByDateRange(
            $userId,
            $startOfWeek,
            $endOfWeek
        );

        // Calculate totals
        $totalSteps = $metrics->sum('steps');
        $totalDistance = $metrics->sum('distance');
        $totalCalories = $metrics->sum('calories');

        return response()->json([
            'start_date' => $startOfWeek,
            'end_date' => $endOfWeek,
            'total_steps' => $totalSteps,
            'total_distance' => $totalDistance,
            'total_calories' => $totalCalories,
            'records' => ActivityMetricResource::collection($metrics)
        ]);
    }

    /**
     * Get monthly activity metrics for the current month
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function monthly(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $startOfMonth = Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = Carbon::now()->endOfMonth()->toDateString();

        $metrics = $this->activityMetricRepository->getByDateRange(
            $userId,
            $startOfMonth,
            $endOfMonth
        );

        // Calculate totals
        $totalSteps = $metrics->sum('steps');
        $totalDistance = $metrics->sum('distance');
        $totalCalories = $metrics->sum('calories');

        return response()->json([
            'start_date' => $startOfMonth,
            'end_date' => $endOfMonth,
            'total_steps' => $totalSteps,
            'total_distance' => $totalDistance,
            'total_calories' => $totalCalories,
            'records' => ActivityMetricResource::collection($metrics)
        ]);
    }
}
