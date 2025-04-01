<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use App\Models\ActivityMetric;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class AITest extends TestCase
{
    use WithFaker;

    protected $user;
    protected $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test user
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Generate JWT token
        $this->token = JWTAuth::fromUser($this->user);

        // Create some test activity metrics
        $this->createTestActivityMetrics();
    }

    protected function tearDown(): void
    {
        // Clean up the test data
        if ($this->user) {
            ActivityMetric::where('user_id', $this->user->id)->delete();
            $this->user->delete();
        }
        parent::tearDown();
    }

    /**
     * Create test activity metrics
     */
    private function createTestActivityMetrics()
    {
        $startDate = Carbon::now()->subDays(14);

        // Create a mock CSV upload entry for test data
        $csvUpload = \App\Models\CsvUpload::create([
            'user_id' => $this->user->id,
            'file_path' => 'storage/app/test_activity_data.csv',
            'status' => 'processed',
            'filename' => 'test_activity_data.csv',
            'original_filename' => 'test_activity_data.csv',
            'rows_processed' => 14,
            'message' => 'Test data for AI predictions'
        ]);

        for ($i = 0; $i < 14; $i++) {
            $date = $startDate->copy()->addDays($i);

            // Base values
            $steps = rand(7000, 12000);
            $activeMinutes = rand(30, 60);

            // Add some variability - higher on weekends, lower on Mondays
            if ($date->isWeekend()) {
                $steps += rand(1000, 3000);
                $activeMinutes += rand(10, 20);
            } elseif ($date->dayOfWeek === Carbon::MONDAY) {
                $steps -= rand(500, 1500);
                $activeMinutes -= rand(5, 15);
            }

            // Create the activity metric
            ActivityMetric::create([
                'user_id' => $this->user->id,
                'csv_upload_id' => $csvUpload->id,
                'activity_date' => $date->format('Y-m-d'),
                'steps' => $steps,
                'active_minutes' => $activeMinutes,
                'distance' => rand(40, 100) / 10, // 4.0 to 10.0 km
            ]);
        }
    }

    /**
     * Test AI model connection
     */
    public function test_ai_connection()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->getJson('/api/ai/test');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'message',
                    'data'
                ]);
    }

    /**
     * Test getting comprehensive predictions with provided activity history
     */
    public function test_get_comprehensive_predictions_with_data()
    {
        $testData = [
            'data' => [
                'activity_history' => [
                    [
                        'date' => Carbon::now()->subDays(7)->format('Y-m-d'),
                        'steps' => 8000,
                        'active_minutes' => 35,
                        'calories_burned' => 380
                    ],
                    [
                        'date' => Carbon::now()->subDays(6)->format('Y-m-d'),
                        'steps' => 8500,
                        'active_minutes' => 40,
                        'calories_burned' => 410
                    ],
                    [
                        'date' => Carbon::now()->subDays(5)->format('Y-m-d'),
                        'steps' => 7800,
                        'active_minutes' => 30,
                        'calories_burned' => 350
                    ],
                    [
                        'date' => Carbon::now()->subDays(4)->format('Y-m-d'),
                        'steps' => 9200,
                        'active_minutes' => 48,
                        'calories_burned' => 450
                    ],
                    [
                        'date' => Carbon::now()->subDays(3)->format('Y-m-d'),
                        'steps' => 12000,
                        'active_minutes' => 65,
                        'calories_burned' => 580
                    ],
                    [
                        'date' => Carbon::now()->subDays(2)->format('Y-m-d'),
                        'steps' => 6500,
                        'active_minutes' => 25,
                        'calories_burned' => 300
                    ],
                    [
                        'date' => Carbon::now()->subDays(1)->format('Y-m-d'),
                        'steps' => 7200,
                        'active_minutes' => 30,
                        'calories_burned' => 340
                    ]
                ],
                'goals' => [
                    'daily_steps' => 10000,
                    'weekly_active_minutes' => 150
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/predict', $testData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'predictions'
                    ]
                ]);

        // Log the response for debugging
        $responseData = $response->json();
        if (!empty($responseData['data']['predictions'])) {
            $this->logJsonResponse('prediction', $responseData['data']['predictions']);
        }
    }

    /**
     * Test getting predictions using database activity history
     */
    public function test_get_predictions_from_database()
    {
        $testData = [
            'data' => [
                'goals' => [
                    'daily_steps' => 10000,
                    'weekly_active_minutes' => 150
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/predict', $testData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'predictions'
                    ]
                ]);

        // Log the response for debugging
        $responseData = $response->json();
        if (!empty($responseData['data']['predictions'])) {
            $this->logJsonResponse('prediction', $responseData['data']['predictions']);
        }
    }

    /**
     * Test getting detailed health insights with provided metrics
     */
    public function test_get_detailed_insights_with_data()
    {
        $testData = [
            'data' => [
                'activity_metrics' => [
                    'daily_steps' => 8500,
                    'active_minutes' => 45,
                    'calories_burned' => 420,
                    'heart_rate' => 72,
                    'sleep_hours' => 7.5,
                    'resting_heart_rate' => 65,
                    'stress_level' => 'medium'
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/insights', $testData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'insights'
                    ]
                ]);

        // Log the response for debugging
        $responseData = $response->json();
        if (!empty($responseData['data']['insights'])) {
            $this->logJsonResponse('insight', $responseData['data']['insights']);
        }
    }

    /**
     * Test getting insights using database activity metrics
     */
    public function test_get_insights_from_database()
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/insights', []);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data' => [
                        'insights'
                    ]
                ]);
    }

    /**
     * Test unauthorized access
     */
    public function test_unauthorized_access()
    {
        $response = $this->getJson('/api/ai/test');
        $response->assertStatus(401);
    }

    /**
     * Test connection to local LM Studio API
     */
    public function test_local_llm_connection()
    {
        // Test the /v1/models endpoint
        $response = Http::get('http://localhost:1234/v1/models');

        $this->assertTrue(
            $response->successful(),
            'Connection to LM Studio models endpoint failed: ' . $response->status()
        );

        // Try a simple chat completion request
        $chatResponse = Http::post('http://localhost:1234/v1/chat/completions', [
            'model' => 'local-model', // This can be any value, LM Studio will use the loaded model
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                ['role' => 'user', 'content' => 'Hello, are you working?']
            ],
            'max_tokens' => 50
        ]);

        $this->assertTrue(
            $chatResponse->successful(),
            'Chat completion request failed: ' . $chatResponse->status()
        );

        // Log the response for debugging
        $this->logJsonResponse('llm_test', $chatResponse->json());
    }

    /**
     * Helper method to log JSON response for debugging
     */
    private function logJsonResponse($type, $response)
    {
        // Convert the response to a string
        $responseStr = json_encode($response, JSON_PRETTY_PRINT);

        // Log the response (could use Laravel's logging system in production)
        file_put_contents(
            storage_path('logs/' . $type . '_test_' . time() . '.json'),
            $responseStr
        );
    }
}
