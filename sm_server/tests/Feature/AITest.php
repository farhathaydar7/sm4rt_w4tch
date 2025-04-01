<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Http;

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
    }

    protected function tearDown(): void
    {
        // Clean up the test user
        if ($this->user) {
            $this->user->delete();
        }
        parent::tearDown();
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
     * Test getting predictions
     */
    public function test_get_predictions()
    {
        $testData = [
            'data' => [
                'activity_history' => [
                    ['date' => '2024-01-01', 'steps' => 8000],
                    ['date' => '2024-01-02', 'steps' => 8500],
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/predict', $testData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data'
                ]);
    }

    /**
     * Test getting insights
     */
    public function test_get_insights()
    {
        $testData = [
            'data' => [
                'activity_metrics' => [
                    'daily_steps' => 8000,
                    'calories_burned' => 400,
                    'active_minutes' => 45
                ]
            ]
        ];

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->token
        ])->postJson('/api/ai/insights', $testData);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'status',
                    'data'
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
        $this->logLlmResponse($chatResponse->json());
    }

    /**
     * Helper method to log LLM response for debugging
     */
    private function logLlmResponse($response)
    {
        // Convert the response to a string
        $responseStr = json_encode($response, JSON_PRETTY_PRINT);

        // Log the response (could use Laravel's logging system in production)
        file_put_contents(
            storage_path('logs/llm_test_' . time() . '.json'),
            $responseStr
        );
    }
}
