<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityMetric;
use Carbon\Carbon;

class AIController extends Controller
{
    protected $aiEndpoint;
    protected $aiModel;
    protected $aiTimeout;
    protected $aiTemperature;

    public function __construct()
    {
        $this->aiEndpoint = env('AI_SERVICE_URL', 'http://localhost:1234');
        $this->aiModel = env('AI_MODEL_NAME', 'deepseek-r1-distill-qwen-7b');
        $this->aiTimeout = (int)env('AI_TIMEOUT', 8);
        $this->aiTemperature = (float)env('AI_TEMPERATURE', 0.7);

        Log::info("AI Service Configuration", [
            'endpoint' => $this->aiEndpoint,
            'model' => $this->aiModel,
            'timeout' => $this->aiTimeout,
            'temperature' => $this->aiTemperature
        ]);
    }

    /**
     * Test the AI model connection
     */
    public function testConnection()
    {
        try {
            Log::info("Testing AI connection to: {$this->aiEndpoint}/v1/models");

            // Use a shorter timeout for connection testing
            $response = Http::timeout(5)
                ->get($this->aiEndpoint . '/v1/models');

            if ($response->successful()) {
                // Check if our model exists
                $models = $response->json();
                $modelExists = false;

                if (isset($models['data']) && is_array($models['data'])) {
                    foreach ($models['data'] as $model) {
                        if (isset($model['id']) && $model['id'] === $this->aiModel) {
                            $modelExists = true;
                            break;
                        }
                    }
                }

                Log::info("AI connection test successful", [
                    'model_exists' => $modelExists ? 'yes' : 'no',
                    'response_status' => $response->status()
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'AI model is running',
                    'model_available' => $modelExists,
                    'data' => $response->json()
                ]);
            }

            Log::warning("AI model not responding", [
                'status_code' => $response->status(),
                'response' => $response->body()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'AI model is not responding',
                'error' => $response->body()
            ], 503);

        } catch (\Exception $e) {
            Log::error('AI Model Connection Error: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to AI model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comprehensive activity predictions
     *
     * This method provides four types of predictions:
     * 1. Goal achievement likelihood
     * 2. Activity anomaly detection
     * 3. Future activity projections
     * 4. Actionable insights
     */
    public function getPredictions(Request $request)
    {
        try {
            $activityHistory = $request->input('data.activity_history', []);

            // If no history is provided, try to get it from the database
            if (empty($activityHistory)) {
                $user = Auth::user();
                $metrics = ActivityMetric::where('user_id', $user->id)
                    ->orderBy('activity_date', 'desc')
                    ->limit(30)
                    ->get();

                if ($metrics->isEmpty()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No activity history available for predictions'
                    ], 400);
                }

                foreach ($metrics as $metric) {
                    $activityHistory[] = [
                        'date' => $metric->activity_date,
                        'steps' => $metric->steps,
                        'active_minutes' => $metric->active_minutes,
                        'distance' => $metric->distance
                    ];
                }
            }

            // Extract goals from request or use defaults
            $dailyStepGoal = $request->input('data.goals.daily_steps', 10000);
            $weeklyActiveMinutesGoal = $request->input('data.goals.weekly_active_minutes', 150);

            // Format the prompt for the LLM for comprehensive predictions
            $userMessage = $this->formatPredictionPrompt($activityHistory, $dailyStepGoal, $weeklyActiveMinutesGoal);

            // Make request to AI model with a timeout
            $response = Http::timeout($this->aiTimeout)->post($this->aiEndpoint . '/v1/chat/completions', [
                'model' => $this->aiModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are an advanced fitness analytics AI. You analyze activity data and provide specific predictions and insights. Your responses should be detailed, accurate, and actionable. Structure your response in JSON format.'
                    ],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'temperature' => $this->aiTemperature,
                'max_tokens' => -1
            ]);

            if ($response->successful()) {
                // Process the LLM response
                $llmResponse = $response->json();
                $predictionText = $llmResponse['choices'][0]['message']['content'] ?? '';

                // Try to parse the response as JSON
                $parsedResponse = $this->extractJsonFromResponse($predictionText);

                // Log the entire prediction for debugging
                Log::debug('AI Prediction: ' . json_encode($parsedResponse));

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'predictions' => $parsedResponse,
                        'raw_response' => $predictionText
                    ]
                ]);
            }

            // If we get a connection error or timeout, provide a fallback response
            if ($response->serverError() || $response->status() === 0) {
                Log::warning('AI service timeout or connection error. Using fallback predictions.');

                // Generate a basic fallback prediction
                $fallbackPredictions = $this->generateFallbackPredictions($activityHistory, $dailyStepGoal, $weeklyActiveMinutesGoal);

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'predictions' => $fallbackPredictions,
                        'is_fallback' => true,
                        'message' => 'AI service unavailable. Using fallback predictions.'
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get predictions',
                'error' => $response->body()
            ], 503);

        } catch (\Exception $e) {
            Log::error('AI Prediction Error: ' . $e->getMessage());

            // Generate a simple fallback prediction in case of any error
            $fallbackPredictions = $this->generateFallbackPredictions(
                $request->input('data.activity_history', []),
                $request->input('data.goals.daily_steps', 10000),
                $request->input('data.goals.weekly_active_minutes', 150)
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'predictions' => $fallbackPredictions,
                    'is_fallback' => true,
                    'message' => 'AI service error. Using fallback predictions.'
                ]
            ]);
        }
    }

    /**
     * Get activity insights
     */
    public function getInsights(Request $request)
    {
        try {
            // Get activity metrics from request
            $activityMetrics = $request->input('data.activity_metrics', []);

            // Debug the incoming activity metrics
            Log::debug('Incoming Activity Metrics:', [
                'raw_metrics' => $activityMetrics,
                'type' => gettype($activityMetrics)
            ]);

            // If no metrics are provided, try to get recent data from the database
            if (empty($activityMetrics)) {
                $user = Auth::user();
                $recentMetrics = ActivityMetric::where('user_id', $user->id)
                    ->orderBy('activity_date', 'desc')
                    ->first();

                if (!$recentMetrics) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'No activity metrics available for insights'
                    ], 400);
                }

                $activityMetrics = [
                    'daily_steps' => $recentMetrics->steps,
                    'active_minutes' => $recentMetrics->active_minutes,
                    'distance' => $recentMetrics->distance
                ];
            }

            // Get user's historical data for context
            $user = Auth::user();
            $historicalData = ActivityMetric::where('user_id', $user->id)
                ->orderBy('activity_date', 'desc')
                ->limit(14)
                ->get();

            // Calculate averages for context
            $avgSteps = $historicalData->avg('steps') ?: 0;
            $avgActiveMinutes = $historicalData->avg('active_minutes') ?: 0;
            $avgDistance = $historicalData->avg('distance') ?: 0;

            // Format the prompt for actionable insights
            $userMessage = $this->formatInsightsPrompt($activityMetrics, [
                'avg_steps' => round($avgSteps),
                'avg_active_minutes' => round($avgActiveMinutes),
                'avg_distance' => round($avgDistance, 2)
            ]);

            // Debug info - log the request we're about to make
            Log::info("Preparing AI Insights request", [
                'endpoint' => $this->aiEndpoint,
                'model' => $this->aiModel,
                'prompt_length' => strlen($userMessage)
            ]);

            // Prepare the request payload
            $requestPayload = [
                'model' => $this->aiModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'You are a health analytics AI that provides personalized, actionable health insights. Structure your response in JSON format with specific recommendations.'
                    ],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'temperature' => $this->aiTemperature,
                'max_tokens' => -1
            ];

            // Log the full request for debugging
            Log::debug("AI Insights Request Payload", $requestPayload);

            // Make request to AI model with a timeout
            try {
                // Log that we're about to make the request
                Log::info("Making AI request to {$this->aiEndpoint}/v1/chat/completions", [
                    'model' => $this->aiModel,
                    'prompt_length' => strlen($userMessage),
                    'timeout' => $this->aiTimeout
                ]);

                $response = Http::timeout($this->aiTimeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json'
                    ])
                    ->post($this->aiEndpoint . '/v1/chat/completions', $requestPayload);

                // Log the response status and size
                Log::info("AI Response received", [
                    'status' => $response->status(),
                    'successful' => $response->successful() ? 'yes' : 'no',
                    'response_size' => strlen($response->body())
                ]);

                if ($response->successful()) {
                    // Process the LLM response
                    $llmResponse = $response->json();

                    if (!isset($llmResponse['choices']) || !isset($llmResponse['choices'][0]['message']['content'])) {
                        // The response format is not as expected - use fallback
                        Log::warning('AI response missing expected data structure', [
                            'has_choices' => isset($llmResponse['choices']) ? 'yes' : 'no',
                            'response_keys' => array_keys($llmResponse)
                        ]);

                        // Generate fallback insights
                        $fallbackInsights = $this->generateFallbackInsights($activityMetrics, [
                            'avg_steps' => round($avgSteps),
                            'avg_active_minutes' => round($avgActiveMinutes),
                            'avg_distance' => round($avgDistance, 2)
                        ]);

                        return response()->json([
                            'status' => 'success',
                            'data' => [
                                'insights' => $fallbackInsights,
                                'is_fallback' => true,
                                'message' => 'AI service returned unexpected data format. Using fallback insights.'
                            ]
                        ]);
                    }

                    $insightsText = $llmResponse['choices'][0]['message']['content'];

                    // Log the first part of the response for debugging
                    Log::debug("AI Response content: " . substr($insightsText, 0, 100) . "...");

                    // Try to parse the response as JSON
                    $parsedInsights = $this->extractJsonFromResponse($insightsText);

                    return response()->json([
                        'status' => 'success',
                        'data' => [
                            'insights' => $parsedInsights,
                            'raw_response' => $insightsText
                        ]
                    ]);
                }

                // If we get a connection error or timeout, provide a fallback response
                if ($response->serverError() || $response->status() === 0) {
                    Log::warning('AI service timeout or connection error. Using fallback insights.', [
                        'status_code' => $response->status(),
                        'error' => $response->body()
                    ]);

                    // Generate a basic fallback insights
                    $fallbackInsights = $this->generateFallbackInsights($activityMetrics, [
                        'avg_steps' => round($avgSteps),
                        'avg_active_minutes' => round($avgActiveMinutes),
                        'avg_distance' => round($avgDistance, 2)
                    ]);

                    return response()->json([
                        'status' => 'success',
                        'data' => [
                            'insights' => $fallbackInsights,
                            'is_fallback' => true,
                            'message' => 'AI service unavailable. Using fallback insights.'
                        ]
                    ]);
                }

                // Log any other error types
                Log::error('AI service returned an unexpected status code', [
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to get insights',
                    'error' => $response->body(),
                    'status_code' => $response->status()
                ], 503);

            } catch (\Illuminate\Http\Client\ConnectionException $ce) {
                // Specific handling for connection exceptions
                Log::error('AI Connection Exception: ' . $ce->getMessage(), [
                    'exception' => get_class($ce),
                    'message' => $ce->getMessage(),
                    'trace' => $ce->getTraceAsString()
                ]);

                // Generate a fallback due to connection issue
                $fallbackInsights = $this->generateFallbackInsights($activityMetrics, [
                    'avg_steps' => round($avgSteps),
                    'avg_active_minutes' => round($avgActiveMinutes),
                    'avg_distance' => round($avgDistance, 2)
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'insights' => $fallbackInsights,
                        'is_fallback' => true,
                        'message' => 'AI service connection error: ' . $ce->getMessage()
                    ]
                ]);
            }

        } catch (\Exception $e) {
            // Log the detailed exception
            Log::error('AI Insights Exception: ' . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Get some basic averages for fallback if we have them
            $user = Auth::user();
            $avgSteps = 0;
            $avgActiveMinutes = 0;
            $avgDistance = 0;

            try {
                $historicalData = ActivityMetric::where('user_id', $user->id)
                    ->orderBy('activity_date', 'desc')
                    ->limit(14)
                    ->get();

                $avgSteps = $historicalData->avg('steps') ?: 0;
                $avgActiveMinutes = $historicalData->avg('active_minutes') ?: 0;
                $avgDistance = $historicalData->avg('distance') ?: 0;
            } catch (\Exception $dbError) {
                Log::error('Failed to get activity metrics for fallback: ' . $dbError->getMessage());
            }

            // Generate a simple fallback insights in case of any error
            $fallbackInsights = $this->generateFallbackInsights(
                $request->input('data.activity_metrics', []),
                [
                    'avg_steps' => round($avgSteps),
                    'avg_active_minutes' => round($avgActiveMinutes),
                    'avg_distance' => round($avgDistance, 2)
                ]
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'insights' => $fallbackInsights,
                    'is_fallback' => true,
                    'message' => 'AI service error: ' . $e->getMessage()
                ]
            ]);
        }
    }

    /**
     * Format the prediction prompt for the LLM
     */
    private function formatPredictionPrompt($activityHistory, $dailyStepGoal, $weeklyActiveMinutesGoal)
    {
        $prompt = "Analyze this activity history and provide four types of predictions:\n\n";
        $prompt .= "ACTIVITY HISTORY:\n";

        foreach ($activityHistory as $activity) {
            $prompt .= "Date: {$activity['date']}, ";
            $prompt .= "Steps: " . ($activity['steps'] ?? 'N/A') . ", ";
            $prompt .= "Active Minutes: " . ($activity['active_minutes'] ?? 'N/A') . ", ";
            $prompt .= "Calories: " . ($activity['distance'] ?? 'N/A') . "\n";
        }

        $prompt .= "\nGOALS:\n";
        $prompt .= "Daily Step Goal: $dailyStepGoal steps\n";
        $prompt .= "Weekly Active Minutes Goal: $weeklyActiveMinutesGoal minutes\n\n";

        $prompt .= "Please provide a comprehensive analysis with the following predictions in JSON format:\n\n";
        $prompt .= "1. GOAL ACHIEVEMENT: Likelihood of meeting daily step goal and weekly active minutes goal based on past performance.\n";
        $prompt .= "2. ANOMALY DETECTION: Identify any days where activity levels deviated significantly from normal patterns and explain why they might be anomalies.\n";
        $prompt .= "3. FUTURE PROJECTIONS: Project activity levels (steps and active minutes) for the next 7 days based on observed trends.\n";
        $prompt .= "4. ACTIONABLE INSIGHTS: Suggest specific adjustments to activity routines that could help optimize health goals.\n\n";

        $prompt .= "Format your response as a valid JSON object with these four main sections. For each section, include specific, data-driven insights.";

        return $prompt;
    }

    /**
     * Format the insights prompt for the LLM
     */
    private function formatInsightsPrompt($currentMetrics, $averages)
    {
        // Create a simplified, more compact prompt
        $prompt = "User activity data:\n";

        // Include current metrics in a compact format
        $prompt .= "Current: ";
        $metricStr = [];
        foreach ($currentMetrics as $metric => $value) {
            if ($value !== null) {
                $label = str_replace(['daily_', '_'], ['', ' '], $metric);
                $metricStr[] = "$label: $value";
            }
        }
        $prompt .= implode(", ", $metricStr) . "\n";

        // Include averages in a compact format
        $prompt .= "14-day averages: ";
        $prompt .= "steps: {$averages['avg_steps']}, ";
        $prompt .= "active mins: {$averages['avg_active_minutes']}, ";
        $prompt .= "distance: {$averages['avg_distance']}\n\n";

        $prompt .= "Analyze this data and provide insights in JSON format with these sections:\n";
        $prompt .= "1. summary: Brief assessment of current activity vs historical patterns\n";
        $prompt .= "2. health_impact: Potential health impacts of the activity levels\n";
        $prompt .= "3. recommendations: Specific, actionable recommendations\n";
        $prompt .= "4. next_steps: Immediate actions for improvement\n\n";

        $prompt .= "Return a clean, valid JSON object.";

        return $prompt;
    }

    /**
     * Extract JSON from LLM response
     * Sometimes the LLM will include explanatory text before or after the JSON
     */
    private function extractJsonFromResponse($text)
    {
        // Try to extract JSON from the response if it's not already valid JSON
        try {
            // First try to decode the entire response
            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }

            // If that fails, try to extract JSON from within the text
            // Look for JSON blocks (including nested ones)
            if (preg_match('/```json\s*([\s\S]*?)\s*```/', $text, $matches)) {
                // Handle code blocks with json syntax
                $jsonStr = $matches[1];
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            // Try to find JSON object pattern
            if (preg_match('/\{[\s\S]*\}/s', $text, $matches)) {
                $jsonStr = $matches[0];
                $decoded = json_decode($jsonStr, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }

            // Log the parsing failure for debugging
            Log::warning('Failed to parse AI response as JSON', [
                'response' => $text,
                'json_error' => json_last_error_msg()
            ]);

            // If JSON extraction fails, create a structured response from the text
            // Find key sections if possible
            $sections = [
                'summary' => $this->extractSection($text, 'SUMMARY'),
                'health_impact' => $this->extractSection($text, 'HEALTH IMPACT'),
                'recommendations' => $this->extractSection($text, 'RECOMMENDATIONS'),
                'next_steps' => $this->extractSection($text, 'NEXT STEPS')
            ];

            // If we have at least some sections, return them
            if (!empty($sections['summary']) || !empty($sections['health_impact'])) {
                return $sections;
            }

            // Last resort: just return the plain text
            return [
                'summary' => substr($text, 0, 200) . (strlen($text) > 200 ? '...' : ''),
                'health_impact' => ['Based on the provided data, we have prepared some insights.'],
                'recommendations' => ['Consider consulting the detailed metrics for more information.'],
                'text_response' => $text
            ];
        } catch (\Exception $e) {
            Log::error('JSON extraction error: ' . $e->getMessage());
            return ['error' => 'Failed to parse AI response', 'raw_text' => $text];
        }
    }

    /**
     * Extract a section from the AI text response
     */
    private function extractSection($text, $sectionName)
    {
        // Try to find sections either by headers or by numbered lists
        if (preg_match('/(?:' . $sectionName . '|' . $sectionName . ':)(.*?)(?:\n\s*\n|\n\s*[A-Z][A-Z\s]+:|\Z)/is', $text, $matches)) {
            $content = trim($matches[1]);

            // Check for bullet points or numbered lists
            if (preg_match_all('/(?:^|\n)(?:\d+\.|\*|\-)\s*(.*?)(?=(?:\n(?:\d+\.|\*|\-|[A-Z][A-Z\s]+:)|\Z))/s', $content, $items)) {
                return array_map('trim', $items[1]);
            }

            // If no clear list format, return whole section as array item
            return [$content];
        }

        return [];
    }

    /**
     * Generate fallback predictions when AI service is unavailable
     */
    private function generateFallbackPredictions($activityHistory, $dailyStepGoal, $weeklyActiveMinutesGoal)
    {
        // Calculate average steps and active minutes from history
        $totalSteps = 0;
        $totalActiveMinutes = 0;
        $count = 0;
        $highestSteps = 0;
        $lowestSteps = PHP_INT_MAX;
        $anomalyDays = [];

        foreach ($activityHistory as $activity) {
            if (isset($activity['steps'])) {
                $totalSteps += $activity['steps'];
                $highestSteps = max($highestSteps, $activity['steps']);
                $lowestSteps = min($lowestSteps, $activity['steps']);
                $count++;

                // Check for potential anomalies (30% deviation from average)
                if ($count > 3 && abs($activity['steps'] - ($totalSteps / $count)) > ($totalSteps / $count) * 0.3) {
                    $anomalyDays[] = [
                        'date' => $activity['date'],
                        'steps' => $activity['steps'],
                        'reason' => $activity['steps'] > ($totalSteps / $count) * 1.3 ? 'Unusually high activity' : 'Unusually low activity'
                    ];
                }
            }

            if (isset($activity['active_minutes'])) {
                $totalActiveMinutes += $activity['active_minutes'];
            }
        }

        $avgSteps = $count > 0 ? $totalSteps / $count : 0;
        $avgActiveMinutes = $count > 0 ? $totalActiveMinutes / $count : 0;

        // Calculate likelihood of meeting goals
        $stepGoalLikelihood = $avgSteps >= $dailyStepGoal ? 'high' : ($avgSteps >= $dailyStepGoal * 0.8 ? 'moderate' : 'low');
        $activeMinutesWeeklyEstimate = $avgActiveMinutes * 7;
        $activeMinutesGoalLikelihood = $activeMinutesWeeklyEstimate >= $weeklyActiveMinutesGoal ? 'high' :
                                        ($activeMinutesWeeklyEstimate >= $weeklyActiveMinutesGoal * 0.8 ? 'moderate' : 'low');

        // Generate future projections (simple linear projection)
        $futureProjections = [];
        $today = Carbon::today();

        for ($i = 1; $i <= 7; $i++) {
            $futureDate = $today->copy()->addDays($i);
            $dayOfWeek = $futureDate->dayOfWeek;

            // Apply day-of-week adjustments
            $dayAdjustment = 1.0;
            if ($dayOfWeek == Carbon::SATURDAY || $dayOfWeek == Carbon::SUNDAY) {
                $dayAdjustment = 1.15; // Higher on weekends
            } elseif ($dayOfWeek == Carbon::MONDAY) {
                $dayAdjustment = 0.9; // Lower on Mondays
            }

            $projectedSteps = round($avgSteps * $dayAdjustment);
            $projectedActiveMinutes = round($avgActiveMinutes * $dayAdjustment);

            $futureProjections[] = [
                'date' => $futureDate->format('Y-m-d'),
                'day_of_week' => $futureDate->format('l'),
                'projected_steps' => $projectedSteps,
                'projected_active_minutes' => $projectedActiveMinutes
            ];
        }

        // Create actionable insights based on the data
        $insights = [];

        if ($avgSteps < $dailyStepGoal) {
            $deficit = $dailyStepGoal - $avgSteps;
            $insights[] = "Increase daily steps by " . round($deficit) . " to meet your goal of " . $dailyStepGoal . " steps.";
        }

        if ($activeMinutesWeeklyEstimate < $weeklyActiveMinutesGoal) {
            $deficit = $weeklyActiveMinutesGoal - $activeMinutesWeeklyEstimate;
            $insights[] = "Add about " . round($deficit / 7) . " more active minutes each day to reach your weekly goal of " . $weeklyActiveMinutesGoal . " minutes.";
        }

        if ($count >= 7) {
            $weekdayAvg = 0;
            $weekendAvg = 0;
            $weekdayCount = 0;
            $weekendCount = 0;

            foreach ($activityHistory as $idx => $activity) {
                if (isset($activity['date']) && isset($activity['steps'])) {
                    $activityDate = Carbon::parse($activity['date']);
                    if ($activityDate->isWeekend()) {
                        $weekendAvg += $activity['steps'];
                        $weekendCount++;
                    } else {
                        $weekdayAvg += $activity['steps'];
                        $weekdayCount++;
                    }
                }
            }

            $weekdayAvg = $weekdayCount > 0 ? $weekdayAvg / $weekdayCount : 0;
            $weekendAvg = $weekendCount > 0 ? $weekendAvg / $weekendCount : 0;

            if ($weekendAvg < $weekdayAvg) {
                $insights[] = "Your activity level drops on weekends. Try to maintain consistent activity throughout the week.";
            }
        }

        // Build the complete prediction object
        return [
            'goal_achievement' => [
                'daily_step_goal' => $dailyStepGoal,
                'weekly_active_minutes_goal' => $weeklyActiveMinutesGoal,
                'step_goal_likelihood' => $stepGoalLikelihood,
                'active_minutes_goal_likelihood' => $activeMinutesGoalLikelihood,
                'average_daily_steps' => round($avgSteps),
                'average_daily_active_minutes' => round($avgActiveMinutes)
            ],
            'anomaly_detection' => [
                'anomalies' => $anomalyDays,
                'highest_steps' => $highestSteps,
                'lowest_steps' => $lowestSteps != PHP_INT_MAX ? $lowestSteps : 0
            ],
            'future_projections' => $futureProjections,
            'actionable_insights' => $insights
        ];
    }

    /**
     * Generate fallback insights when AI service is unavailable
     */
    private function generateFallbackInsights($currentMetrics, $averages)
    {
        // Ensure we have scalar values, not arrays
        $steps = isset($currentMetrics['daily_steps']) ? (is_array($currentMetrics['daily_steps']) ? 0 : $currentMetrics['daily_steps']) : 0;
        $activeMinutes = isset($currentMetrics['active_minutes']) ? (is_array($currentMetrics['active_minutes']) ? 0 : $currentMetrics['active_minutes']) : 0;
        $distance = isset($currentMetrics['distance']) ? (is_array($currentMetrics['distance']) ? 0 : $currentMetrics['distance']) : 0;

        // Log the metrics for debugging
        Log::debug('Fallback Insights - Current Metrics:', [
            'raw_metrics' => $currentMetrics,
            'processed_steps' => $steps,
            'processed_active_minutes' => $activeMinutes,
            'processed_distance' => $distance
        ]);

        $avgSteps = $averages['avg_steps'] ?? 0;
        $avgActiveMinutes = $averages['avg_active_minutes'] ?? 0;
        $avgDistance = $averages['avg_distance'] ?? 0;

        // Compare current metrics to averages
        $stepComparison = $steps > $avgSteps ? 'above' : ($steps < $avgSteps ? 'below' : 'at');
        $activeMinutesComparison = $activeMinutes > $avgActiveMinutes ? 'above' : ($activeMinutes < $avgActiveMinutes ? 'below' : 'at');

        // Generate a summary based on the comparison
        $summary = "Your activity is currently $stepComparison your average step count";
        if ($stepComparison != $activeMinutesComparison) {
            $summary .= " and $activeMinutesComparison your average active minutes.";
        } else {
            $summary .= " and active minutes.";
        }

        // Determine health impact
        $healthImpact = [];
        if ($steps >= 10000) {
            $healthImpact[] = "Your current step count of $steps is excellent and associated with good cardiovascular health.";
        } elseif ($steps >= 7500) {
            $healthImpact[] = "Your current step count of $steps is good and associated with moderate health benefits.";
        } else {
            $healthImpact[] = "Your current step count of $steps is below recommended levels for optimal health benefits.";
        }

        if ($activeMinutes >= 30) {
            $healthImpact[] = "Your active minutes of $activeMinutes meet daily recommendations for physical activity.";
        } else {
            $healthImpact[] = "Increasing your active minutes from $activeMinutes to at least 30 minutes daily would improve health outcomes.";
        }

        // Generate recommendations
        $recommendations = [];
        if ($steps < 10000) {
            $stepIncrease = ceil((10000 - $steps) / 1000) * 1000;
            $recommendations[] = "Aim to increase your daily steps by approximately $stepIncrease to reach the recommended 10,000 steps.";
        }

        if ($activeMinutes < 30) {
            $recommendations[] = "Try to incorporate " . (30 - $activeMinutes) . " more minutes of moderate activity into your daily routine.";
        }

        if ($steps >= $avgSteps && $activeMinutes >= $avgActiveMinutes) {
            $recommendations[] = "You're doing well compared to your average. Try to maintain this level of activity consistently.";
        }

        // Generate next steps
        $nextSteps = [];
        if ($steps < 7500) {
            $nextSteps[] = "Take a 15-minute walk during your lunch break or after dinner.";
        }

        if ($activeMinutes < 20) {
            $nextSteps[] = "Schedule a 20-minute workout session tomorrow.";
        }

        $nextSteps[] = "Set a specific goal to increase your steps by 500 each day for the next week.";

        // Build the complete insights object
        return [
            'summary' => $summary,
            'health_impact' => $healthImpact,
            'recommendations' => $recommendations,
            'next_steps' => $nextSteps
        ];
    }
}
