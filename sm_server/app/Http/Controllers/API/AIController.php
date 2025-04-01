<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIController extends Controller
{
    protected $aiEndpoint = 'http://localhost:1234';

    /**
     * Test the AI model connection
     */
    public function testConnection()
    {
        try {
            // LM Studio API uses /v1/models for checking health
            $response = Http::get($this->aiEndpoint . '/v1/models');

            if ($response->successful()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'AI model is running',
                    'data' => $response->json()
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'AI model is not responding',
                'error' => $response->body()
            ], 503);

        } catch (\Exception $e) {
            Log::error('AI Model Connection Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to connect to AI model',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity predictions
     */
    public function getPredictions(Request $request)
    {
        try {
            // Use the chat completions endpoint to get predictions
            $activityHistory = $request->input('data.activity_history', []);

            // Format the prompt for the LLM
            $userMessage = "Based on this activity history, predict future trends:\n";
            foreach ($activityHistory as $activity) {
                $userMessage .= "Date: {$activity['date']}, Steps: {$activity['steps']}\n";
            }
            $userMessage .= "\nProvide a prediction of future activity trends.";

            $response = Http::post($this->aiEndpoint . '/v1/chat/completions', [
                'model' => 'local-model', // LM Studio uses the loaded model
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a fitness analysis AI that predicts activity trends based on historical data.'],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'max_tokens' => 500
            ]);

            if ($response->successful()) {
                // Extract the LLM response
                $llmResponse = $response->json();
                $prediction = $llmResponse['choices'][0]['message']['content'] ?? 'No prediction available';

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'prediction' => $prediction,
                        'raw_response' => $llmResponse
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
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get predictions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity insights
     */
    public function getInsights(Request $request)
    {
        try {
            // Use the chat completions endpoint for insights
            $activityMetrics = $request->input('data.activity_metrics', []);

            // Format the prompt for the LLM
            $userMessage = "Based on these activity metrics, provide insights:\n";
            foreach ($activityMetrics as $metric => $value) {
                $userMessage .= ucfirst(str_replace('_', ' ', $metric)) . ": {$value}\n";
            }
            $userMessage .= "\nAnalyze this data and provide health insights.";

            $response = Http::post($this->aiEndpoint . '/v1/chat/completions', [
                'model' => 'local-model', // LM Studio uses the loaded model
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a health analytics AI that provides insights based on fitness metrics.'],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'max_tokens' => 500
            ]);

            if ($response->successful()) {
                // Extract the LLM response
                $llmResponse = $response->json();
                $insights = $llmResponse['choices'][0]['message']['content'] ?? 'No insights available';

                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'insights' => $insights,
                        'raw_response' => $llmResponse
                    ]
                ]);
            }

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get insights',
                'error' => $response->body()
            ], 503);

        } catch (\Exception $e) {
            Log::error('AI Insights Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get insights',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
