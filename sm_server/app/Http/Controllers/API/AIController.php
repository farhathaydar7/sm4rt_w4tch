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
            // Super detailed debugging of the incoming request
            $allData = $request->all();

            // Handle the common case where data is a top-level field
            $dataField = isset($allData['data']) && is_array($allData['data'])
                ? $allData['data']
                : (array)$request->input('data', []);

            // Log the structure we've extracted
            Log::debug('DETAILED REQUEST STRUCTURE', [
                'request_method' => $request->method(),
                'content_type' => $request->header('Content-Type'),
                'has_data_field' => isset($allData['data']) ? 'yes' : 'no',
                'data_field_type' => isset($allData['data']) ? gettype($allData['data']) : 'not_set'
            ]);

            // Get activity metrics from the structured data
            $activityMetrics = [];
            if (isset($dataField['activity_metrics'])) {
                $activityMetrics = $dataField['activity_metrics'];
            }

            // Debug the extracted activity metrics
            Log::debug('Extracted Activity Metrics:', [
                'metrics_type' => gettype($activityMetrics),
                'is_array' => is_array($activityMetrics) ? 'yes' : 'no',
                'metrics_json' => json_encode($activityMetrics)
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

            // Before sending to the AI, verify that we have valid data
            try {
                // Extract steps, active_minutes, and distance ensuring they're scalar values
                $steps = 0;
                $activeMinutes = 0;
                $distance = 0;

                if (isset($activityMetrics['daily_steps'])) {
                    if (is_array($activityMetrics['daily_steps'])) {
                        // Try to extract a numeric value from the array
                        if (isset($activityMetrics['daily_steps'][0]) && is_numeric($activityMetrics['daily_steps'][0])) {
                            $steps = $activityMetrics['daily_steps'][0];
                        } elseif (isset($activityMetrics['daily_steps']['value']) && is_numeric($activityMetrics['daily_steps']['value'])) {
                            $steps = $activityMetrics['daily_steps']['value'];
                        }
                    } else {
                        $steps = is_numeric($activityMetrics['daily_steps']) ? $activityMetrics['daily_steps'] : 0;
                    }
                }

                if (isset($activityMetrics['active_minutes'])) {
                    if (is_array($activityMetrics['active_minutes'])) {
                        // Try to extract a numeric value from the array
                        if (isset($activityMetrics['active_minutes'][0]) && is_numeric($activityMetrics['active_minutes'][0])) {
                            $activeMinutes = $activityMetrics['active_minutes'][0];
                        } elseif (isset($activityMetrics['active_minutes']['value']) && is_numeric($activityMetrics['active_minutes']['value'])) {
                            $activeMinutes = $activityMetrics['active_minutes']['value'];
                        }
                    } else {
                        $activeMinutes = is_numeric($activityMetrics['active_minutes']) ? $activityMetrics['active_minutes'] : 0;
                    }
                }

                if (isset($activityMetrics['distance'])) {
                    if (is_array($activityMetrics['distance'])) {
                        // Try to extract a numeric value from the array
                        if (isset($activityMetrics['distance'][0]) && is_numeric($activityMetrics['distance'][0])) {
                            $distance = $activityMetrics['distance'][0];
                        } elseif (isset($activityMetrics['distance']['value']) && is_numeric($activityMetrics['distance']['value'])) {
                            $distance = $activityMetrics['distance']['value'];
                        }
                    } else {
                        $distance = is_numeric($activityMetrics['distance']) ? $activityMetrics['distance'] : 0;
                    }
                }

                // Use the sanitized values for the AI request
                $cleanedMetrics = [
                    'daily_steps' => $steps,
                    'active_minutes' => $activeMinutes,
                    'distance' => $distance
                ];

                // Re-format the prompt with cleaned data
                $userMessage = $this->formatInsightsPrompt($cleanedMetrics, [
                    'avg_steps' => round($avgSteps),
                    'avg_active_minutes' => round($avgActiveMinutes),
                    'avg_distance' => round($avgDistance, 2)
                ]);

                Log::debug("Using cleaned metrics for AI request", [
                    'cleaned_metrics' => $cleanedMetrics
                ]);
            } catch (\Exception $e) {
                Log::warning("Error while sanitizing metrics: " . $e->getMessage(), [
                    'trace' => $e->getTraceAsString()
                ]);
                // Continue with the original message if there was an error
            }

            // Prepare the request payload
            $requestPayload = [
                'model' => $this->aiModel,
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->getSystemMessage()
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

            // Extract activity metrics safely
            $safeMetrics = [];
            $rawMetrics = $request->input('data.activity_metrics', []);

            // Log what we received
            Log::debug('Received activity metrics in exception handler', [
                'type' => gettype($rawMetrics),
                'is_array' => is_array($rawMetrics) ? 'yes' : 'no'
            ]);

            // Convert to safe format regardless of input structure
            if (is_array($rawMetrics)) {
                // Handle potential nested array structures
                if (isset($rawMetrics['daily_steps'])) {
                    $safeMetrics['daily_steps'] = is_array($rawMetrics['daily_steps'])
                        ? (isset($rawMetrics['daily_steps'][0]) ? $rawMetrics['daily_steps'][0] : 0)
                        : $rawMetrics['daily_steps'];
                }

                if (isset($rawMetrics['active_minutes'])) {
                    $safeMetrics['active_minutes'] = is_array($rawMetrics['active_minutes'])
                        ? (isset($rawMetrics['active_minutes'][0]) ? $rawMetrics['active_minutes'][0] : 0)
                        : $rawMetrics['active_minutes'];
                }

                if (isset($rawMetrics['distance'])) {
                    $safeMetrics['distance'] = is_array($rawMetrics['distance'])
                        ? (isset($rawMetrics['distance'][0]) ? $rawMetrics['distance'][0] : 0)
                        : $rawMetrics['distance'];
                }
            }

            // Generate a simple fallback insights in case of any error
            $fallbackInsights = $this->generateFallbackInsights(
                $safeMetrics,
                [
                    'avg_steps' => round($avgSteps),
                    'avg_active_minutes' => round($avgActiveMinutes),
                    'avg_distance' => round($avgDistance, 2)
                ]
            );

            // Provide a clearer error message
            $errorMessage = "AI service error";
            if (strpos($e->getMessage(), "Array to string conversion") !== false) {
                $errorMessage = "Data format issue. Using fallback insights.";
            } else {
                $errorMessage = "AI service error: " . substr($e->getMessage(), 0, 100);
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'insights' => $fallbackInsights,
                    'is_fallback' => true,
                    'message' => $errorMessage
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
        // Create a more detailed prompt for better insights
        $prompt = "Analyze this user's health and activity data to provide health insights:\n\n";

        // Include current metrics with clear labels
        $prompt .= "TODAY'S ACTIVITY:\n";
        $metricStr = [];
        foreach ($currentMetrics as $metric => $value) {
            if ($value !== null) {
                $label = str_replace(['daily_', '_'], ['', ' '], $metric);
                $metricStr[] = "$label: $value";
            }
        }
        $prompt .= implode(", ", $metricStr) . "\n";

        // Include averages with context
        $prompt .= "\nHISTORICAL CONTEXT (14-day averages):\n";
        $prompt .= "Average steps: {$averages['avg_steps']}\n";
        $prompt .= "Average active minutes: {$averages['avg_active_minutes']}\n";
        $prompt .= "Average distance: {$averages['avg_distance']} km\n\n";

        // Add health benchmarks for reference
        $prompt .= "HEALTH BENCHMARKS:\n";
        $prompt .= "- Recommended daily steps: 10,000 steps\n";
        $prompt .= "- Recommended daily active minutes: 30 minutes\n";
        $prompt .= "- Weekly active minutes target: 150 minutes\n\n";

        $prompt .= "RESPONSE FORMAT INSTRUCTIONS:\n";
        $prompt .= "1. Return ONLY a valid JSON object with no surrounding markdown code blocks or commentary\n";
        $prompt .= "2. Use only standard JSON syntax - NO Python f-strings or any templated strings\n";
        $prompt .= "3. DO NOT use ellipsis (...) or abbreviations to truncate content\n";
        $prompt .= "4. Ensure all JSON is properly structured with matching brackets and quotes\n";
        $prompt .= "5. Always use double quotes for strings and keys as per JSON standard\n";
        $prompt .= "6. Follow this exact structure with these exact key names:\n\n";

        $prompt .= "{\n";
        $prompt .= "  \"summary\": {\n";
        $prompt .= "    \"current_activity\": {\n";
        $prompt .= "      \"steps\": 0,\n";
        $prompt .= "      \"active_minutes\": 0,\n";
        $prompt .= "      \"distance\": 0\n";
        $prompt .= "    },\n";
        $prompt .= "    \"historical_context\": {\n";
        $prompt .= "      \"average_steps\": 0,\n";
        $prompt .= "      \"average_active_minutes\": 0,\n";
        $prompt .= "      \"average_distance\": 0\n";
        $prompt .= "    },\n";
        $prompt .= "    \"health_benchmarks\": {\n";
        $prompt .= "      \"recommended_daily_steps\": 10000,\n";
        $prompt .= "      \"recommended_daily_active_minutes\": 30,\n";
        $prompt .= "      \"weekly_target\": 150\n";
        $prompt .= "    },\n";
        $prompt .= "    \"assessment\": \"A string describing the user's current activity compared to their average and health benchmarks\"\n";
        $prompt .= "  },\n";
        $prompt .= "  \"health_impact\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"title\": \"Impact point title\",\n";
        $prompt .= "      \"description\": \"Detailed description of health impact\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"recommendations\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"title\": \"Recommendation title\",\n";
        $prompt .= "      \"description\": \"Detailed description of recommendation\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"next_steps\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"action\": \"Specific action to take\",\n";
        $prompt .= "      \"timeframe\": \"When to do it\",\n";
        $prompt .= "      \"target\": \"Measurable target\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ],\n";
        $prompt .= "  \"long_term_benefits\": [\n";
        $prompt .= "    {\n";
        $prompt .= "      \"benefit\": \"Long-term benefit title\",\n";
        $prompt .= "      \"description\": \"Detailed description of long-term benefit\"\n";
        $prompt .= "    }\n";
        $prompt .= "  ]\n";
        $prompt .= "}\n\n";

        $prompt .= "Analyze the data provided and fill in this exact JSON structure with your insights. Do not add any text before or after the JSON.";

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
        // First ensure that currentMetrics is an array
        if (!is_array($currentMetrics)) {
            Log::error('generateFallbackInsights received non-array currentMetrics', [
                'type' => gettype($currentMetrics),
                'value' => $currentMetrics
            ]);
            $currentMetrics = [];
        }

        // Extract values safely
        $steps = 0;
        if (isset($currentMetrics['daily_steps'])) {
            if (is_array($currentMetrics['daily_steps'])) {
                // If it's an array, log it and try to extract a usable value
                Log::debug('daily_steps is an array', [
                    'array_value' => $currentMetrics['daily_steps']
                ]);
                // Try to get the first value, or a 'value' key if it exists
                if (isset($currentMetrics['daily_steps'][0])) {
                    $steps = is_numeric($currentMetrics['daily_steps'][0]) ?
                        $currentMetrics['daily_steps'][0] : 0;
                } elseif (isset($currentMetrics['daily_steps']['value'])) {
                    $steps = is_numeric($currentMetrics['daily_steps']['value']) ?
                        $currentMetrics['daily_steps']['value'] : 0;
                }
            } else {
                // It's a scalar value, use it directly
                $steps = is_numeric($currentMetrics['daily_steps']) ?
                    $currentMetrics['daily_steps'] : 0;
            }
        }

        $activeMinutes = 0;
        if (isset($currentMetrics['active_minutes'])) {
            if (is_array($currentMetrics['active_minutes'])) {
                Log::debug('active_minutes is an array', [
                    'array_value' => $currentMetrics['active_minutes']
                ]);
                if (isset($currentMetrics['active_minutes'][0])) {
                    $activeMinutes = is_numeric($currentMetrics['active_minutes'][0]) ?
                        $currentMetrics['active_minutes'][0] : 0;
                } elseif (isset($currentMetrics['active_minutes']['value'])) {
                    $activeMinutes = is_numeric($currentMetrics['active_minutes']['value']) ?
                        $currentMetrics['active_minutes']['value'] : 0;
                }
            } else {
                $activeMinutes = is_numeric($currentMetrics['active_minutes']) ?
                    $currentMetrics['active_minutes'] : 0;
            }
        }

        $distance = 0;
        if (isset($currentMetrics['distance'])) {
            if (is_array($currentMetrics['distance'])) {
                Log::debug('distance is an array', [
                    'array_value' => $currentMetrics['distance']
                ]);
                if (isset($currentMetrics['distance'][0])) {
                    $distance = is_numeric($currentMetrics['distance'][0]) ?
                        $currentMetrics['distance'][0] : 0;
                } elseif (isset($currentMetrics['distance']['value'])) {
                    $distance = is_numeric($currentMetrics['distance']['value']) ?
                        $currentMetrics['distance']['value'] : 0;
                }
            } else {
                $distance = is_numeric($currentMetrics['distance']) ?
                    $currentMetrics['distance'] : 0;
            }
        }

        // Log the processed metrics for debugging
        Log::debug('Fallback Insights - Processed Metrics:', [
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

        // Provide recommendations
        $recommendations = [];
        if ($steps < 10000) {
            $stepsToIncrease = 10000 - $steps;
            $recommendations[] = "Aim to increase your daily steps by approximately $stepsToIncrease to reach the recommended 10,000 steps.";
        } else {
            $recommendations[] = "Maintain your excellent step count of $steps steps per day.";
        }

        // Next steps
        $nextSteps = [
            "Take a 15-minute walk during your lunch break or after dinner.",
            "Set a specific goal to increase your steps by 500 each day for the next week."
        ];

        // Add long-term benefits section
        $longTermBenefits = [
            "Maintaining regular physical activity can reduce your risk of heart disease and stroke by up to 35%.",
            "Consistent daily walking has been shown to improve mood and reduce symptoms of depression and anxiety.",
            "Regular physical activity helps maintain healthy weight and reduces the risk of type 2 diabetes."
        ];

        return [
            'summary' => $summary,
            'health_impact' => $healthImpact,
            'recommendations' => $recommendations,
            'next_steps' => $nextSteps,
            'long_term_benefits' => $longTermBenefits
        ];
    }

    /**
     * Get the system message for AI interactions
     */
    private function getSystemMessage()
    {
        return 'You are a health analytics AI that provides personalized, actionable health insights. You MUST structure your response as a valid JSON object WITHOUT any surrounding text, markdown code blocks, or commentary. Follow the structure specified in the prompt exactly. Never use Python f-strings, templates, variable interpolation, or any non-JSON syntax. Never truncate your response with ellipsis or incomplete entries.';
    }
}
