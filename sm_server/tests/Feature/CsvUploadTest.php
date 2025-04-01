<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\CsvUpload;
use App\Models\ActivityMetric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CsvUploadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create the storage directory for uploads
        Storage::fake('public');
    }

    /**
     * Test CSV file upload.
     *
     * @return void
     */
    public function test_user_can_upload_csv_file()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a fake CSV file
        $csvContent = "user_id,date,steps,distance_km,active_minutes\n";
        $csvContent .= "{$user->id},2023-01-01,8500,6.2,45\n";
        $csvContent .= "{$user->id},2023-01-02,10200,7.5,60\n";
        $csvContent .= "{$user->id},2023-01-03,5600,4.1,30\n";

        $file = UploadedFile::fake()->createWithContent(
            'activity_data.csv',
            $csvContent
        );

        // Act as the user and send the request
        $response = $this->actingAs($user, 'api')
            ->postJson('/api/csv-uploads', [
                'csv_file' => $file,
            ]);

        // Assert the response is successful (202 Accepted)
        $response->assertStatus(202)
                ->assertJsonStructure([
                    'data' => [
                        'id',
                        'user_id',
                        'file_path',
                        'status',
                        'created_at',
                        'updated_at'
                    ],
                    'message'
                ]);

        // Assert a CSV upload record was created with any status (not specifically 'pending')
        $this->assertDatabaseHas('csv_uploads', [
            'user_id' => $user->id,
        ]);

        // Get the created upload
        $upload = CsvUpload::latest()->first();

        // Assert the file was stored
        Storage::disk('public')->assertExists($upload->file_path);
    }

    /**
     * Test processing of a CSV file.
     *
     * @return void
     */
    public function test_csv_file_processing()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a CSV upload record
        $csvUpload = CsvUpload::create([
            'user_id' => $user->id,
            'file_path' => 'test_path.csv',
            'status' => 'pending'
        ]);

        // Create a fake CSV file
        $csvContent = "user_id,date,steps,distance_km,active_minutes\n";
        $csvContent .= "{$user->id},2023-01-01,8500,6.2,45\n";
        $csvContent .= "{$user->id},2023-01-02,10200,7.5,60\n";

        // Store the file
        Storage::fake('public');
        Storage::disk('public')->put($csvUpload->file_path, $csvContent);

        // Process the file directly (since we can't easily test the job)
        $csvProcessor = app(\App\Services\CsvProcessingService::class);
        $result = $csvProcessor->processCsvFile($csvUpload);

        // Refresh the model
        $csvUpload->refresh();

        // Assert the status was updated
        $this->assertEquals('processed', $csvUpload->status);

        // Assert the metrics were created
        $this->assertDatabaseCount('activity_metrics', 2);
        $this->assertDatabaseHas('activity_metrics', [
            'user_id' => $user->id,
            'csv_upload_id' => $csvUpload->id,
            'steps' => 8500,
            'distance' => 6.2,
            'active_minutes' => 45
        ]);
    }

    /**
     * Test validation rejects invalid files.
     *
     * @return void
     */
    public function test_validation_rejects_invalid_files()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a fake file that's not a CSV
        $file = UploadedFile::fake()->createWithContent(
            'document.txt',
            'This is not a CSV file'
        );

        // Act as the user and send the request
        $response = $this->actingAs($user, 'api')
            ->postJson('/api/csv-uploads', [
                'csv_file' => $file,
            ]);

        // Assert the response has errors (either 400 or 422)
        $response->assertStatus(400);

        // No upload record should be created
        $this->assertDatabaseCount('csv_uploads', 0);
    }

    /**
     * Test user can check upload status.
     *
     * @return void
     */
    public function test_user_can_check_upload_status()
    {
        // Create a user
        $user = User::factory()->create();

        // Create a CSV upload
        $csvUpload = CsvUpload::create([
            'user_id' => $user->id,
            'file_path' => 'test_path.csv',
            'status' => 'processing'
        ]);

        // Act as the user and get the status
        $response = $this->actingAs($user, 'api')
            ->getJson("/api/csv-uploads/{$csvUpload->id}/status");

        // Assert the response is successful
        $response->assertStatus(200)
                ->assertJson([
                    'data' => [
                        'id' => $csvUpload->id,
                        'status' => 'processing'
                    ]
                ]);
    }

    /**
     * Test user cannot access another user's uploads.
     *
     * @return void
     */
    public function test_user_cannot_access_other_users_uploads()
    {
        // Create two users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        // Create a CSV upload for user 1
        $csvUpload = CsvUpload::create([
            'user_id' => $user1->id,
            'file_path' => 'test_path.csv',
            'status' => 'processed'
        ]);

        // Act as user 2 and try to get the status
        $response = $this->actingAs($user2, 'api')
            ->getJson("/api/csv-uploads/{$csvUpload->id}/status");

        // Assert access is denied
        $response->assertStatus(404);
    }
}
