<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\Interfaces\ActivityMetricRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ActivityController extends Controller
{
    protected $activityMetricRepository;

    public function __construct(ActivityMetricRepositoryInterface $activityMetricRepository)
    {
        $this->activityMetricRepository = $activityMetricRepository;
    }

    /**
     * Get all activity data for the authenticated user
     *
     * @return JsonResponse
     */
    public function getAll(): JsonResponse
    {
        $userId = Auth::id();
        $activities = $this->activityMetricRepository->getByUserId($userId);

        return response()->json([
            'status' => 'success',
            'data' => $activities
        ]);
    }

    /**
     * Get activity data for a specific date
     *
     * @param string $date
     * @return JsonResponse
     */
    public function getByDate(string $date): JsonResponse
    {
        $userId = Auth::id();
        $activity = $this->activityMetricRepository->findByUserAndDate($userId, $date);

        if (!$activity) {
            return response()->json([
                'status' => 'error',
                'message' => 'No activity data found for this date'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $activity
        ]);
    }

    /**
     * Get weekly summary of activity data
     *
     * @return JsonResponse
     */
    public function getWeeklySummary(): JsonResponse
    {
        $userId = Auth::id();
        $endDate = Carbon::now();
        $startDate = Carbon::now()->subDays(7);

        $activities = $this->activityMetricRepository->getByDateRange(
            $userId,
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $summary = [
            'total_steps' => $activities->sum('steps'),
            'total_distance' => $activities->sum('distance'),
            'total_active_minutes' => $activities->sum('active_minutes'),
            'average_steps_per_day' => round($activities->avg('steps')),
            'average_distance_per_day' => round($activities->avg('distance'), 2),
            'average_active_minutes_per_day' => round($activities->avg('active_minutes')),
            'daily_data' => $activities->map(function ($activity) {
                return [
                    'date' => $activity->activity_date,
                    'steps' => $activity->steps,
                    'distance' => $activity->distance,
                    'active_minutes' => $activity->active_minutes
                ];
            })
        ];

        return response()->json([
            'status' => 'success',
            'data' => $summary
        ]);
    }

    /**
     * Get overall activity stats
     *
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        $userId = Auth::id();
        $activities = $this->activityMetricRepository->getByUserId($userId);

        $stats = [
            'total_steps' => $activities->sum('steps'),
            'total_distance' => $activities->sum('distance'),
            'total_active_minutes' => $activities->sum('active_minutes'),
            'average_steps_per_day' => round($activities->avg('steps')),
            'average_distance_per_day' => round($activities->avg('distance'), 2),
            'average_active_minutes_per_day' => round($activities->avg('active_minutes')),
            'total_days_tracked' => $activities->count(),
            'best_day' => [
                'date' => $activities->sortByDesc('steps')->first()?->activity_date,
                'steps' => $activities->max('steps'),
                'distance' => $activities->max('distance'),
                'active_minutes' => $activities->max('active_minutes')
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }
}
