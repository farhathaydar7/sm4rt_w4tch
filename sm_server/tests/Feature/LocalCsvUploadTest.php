<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class LocalCsvUploadTest extends TestCase
{
    use RefreshDatabase;

    protected $localCsvPath;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create a storage disk for uploads
        Storage::fake('public');

        // Set the path to the local CSV file
        $this->localCsvPath = public_path('smrt.csv');

        // Create a backup of the actual file if it exists
        if (File::exists($this->localCsvPath)) {
            File::copy($this->localCsvPath, $this->localCsvPath . '.bak');
        }
    }

    /**
     * Clean up after the test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Restore the original file if a backup exists
        if (File::exists($this->localCsvPath . '.bak')) {
            File::copy($this->localCsvPath . '.bak', $this->localCsvPath);
            File::delete($this->localCsvPath . '.bak');
        } elseif (File::exists($this->localCsvPath)) {
            // If no backup exists but a test file exists, remove it
            File::delete($this->localCsvPath);
        }

        parent::tearDown();
    }

    /**
     * Test that CSV data is uploaded on login if a valid file exists.
     *
     * @return void
     */
    public function test_csv_is_uploaded_on_login_with_valid_file()
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Create a valid CSV file with data for this user
        $csvContent = "user_id,date,steps,distance_km,active_minutes\n";
        $csvContent .= "{$user->id},2023-04-01,9500,7.2,55\n";
        $csvContent .= "{$user->id},2023-04-02,8200,6.1,48\n";
        File::put($this->localCsvPath, $csvContent);

        // Send login request
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert successful login
        $response->assertStatus(200);

        // Assert response includes a CSV upload
        $response->assertJsonStructure([
            'user',
            'authorization' => [
                'token',
                'type',
                'expires_in'
            ],
            'csv_upload' => [
                'id',
                'status',
                'message'
            ]
        ]);

        // Assert a CSV upload record was created
        $uploadId = $response->json('csv_upload.id');
        $this->assertDatabaseHas('csv_uploads', [
            'id' => $uploadId,
            'user_id' => $user->id
        ]);

        // Assert the file was stored
        $csvUpload = \App\Models\CsvUpload::find($uploadId);
        Storage::disk('public')->assertExists($csvUpload->file_path);
    }

    /**
     * Test that no CSV is uploaded if file doesn't exist.
     *
     * @return void
     */
    public function test_no_csv_upload_when_file_missing()
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Make sure the CSV file doesn't exist
        if (File::exists($this->localCsvPath)) {
            File::delete($this->localCsvPath);
        }

        // Send login request
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert successful login
        $response->assertStatus(200);

        // Assert response indicates no CSV upload
        $response->assertJsonPath('csv_upload.status', 'none');

        // Assert no CSV upload record was created
        $this->assertDatabaseMissing('csv_uploads', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Test that invalid CSV data is not uploaded.
     *
     * @return void
     */
    public function test_invalid_csv_is_not_uploaded()
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Create an invalid CSV file (wrong user ID)
        $csvContent = "user_id,date,steps,distance_km,active_minutes\n";
        $csvContent .= "999,2023-04-01,9500,7.2,55\n"; // Using a different user ID
        File::put($this->localCsvPath, $csvContent);

        // Send login request
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert successful login
        $response->assertStatus(200);

        // Assert response indicates no CSV upload
        $response->assertJsonPath('csv_upload.status', 'none');

        // Assert no CSV upload record was created
        $this->assertDatabaseMissing('csv_uploads', [
            'user_id' => $user->id
        ]);
    }

    /**
     * Test that malformed CSV data is not uploaded.
     *
     * @return void
     */
    public function test_malformed_csv_is_not_uploaded()
    {
        // Create a user
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123')
        ]);

        // Create a malformed CSV file (wrong header)
        $csvContent = "wrong,header,format\n";
        $csvContent .= "{$user->id},2023-04-01,9500\n";
        File::put($this->localCsvPath, $csvContent);

        // Send login request
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123'
        ]);

        // Assert successful login
        $response->assertStatus(200);

        // Assert response indicates no CSV upload
        $response->assertJsonPath('csv_upload.status', 'none');

        // Assert no CSV upload record was created
        $this->assertDatabaseMissing('csv_uploads', [
            'user_id' => $user->id
        ]);
    }
}
