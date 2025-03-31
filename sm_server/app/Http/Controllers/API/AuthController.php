<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\LocalCsvUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    protected $localCsvUploadService;

    /**
     * Create a new AuthController instance.
     *
     * @param LocalCsvUploadService $localCsvUploadService
     * @return void
     */
    public function __construct(LocalCsvUploadService $localCsvUploadService)
    {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
        $this->localCsvUploadService = $localCsvUploadService;
    }

    /**
     * Register a new user
     *
     * @param RegisterRequest $request
     * @return JsonResponse
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = JWTAuth::fromUser($user);

        // We don't automatically upload CSV data on registration anymore
        // since the user needs to manually add the file to the public directory

        return response()->json([
            'user' => new UserResource($user),
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ],
            'message' => 'User registered successfully. To upload activity data, please update the smrt.csv file in the public directory with your user ID.'
        ], 201);
    }

    /**
     * Get a JWT via given credentials.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (!$token = auth()->attempt($request->only('email', 'password'))) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Get the authenticated user
        $user = auth()->user();

        // Try to upload the local CSV file if it exists
        $csvUpload = $this->localCsvUploadService->uploadLocalCsvForUser($user);

        // If CSV was uploaded successfully, include it in the response
        if ($csvUpload) {
            return $this->respondWithToken($token, $csvUpload);
        }

        // If no CSV was uploaded, just return the normal response
        return $this->respondWithToken($token);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @return JsonResponse
     */
    public function logout(): JsonResponse
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the authenticated User.
     *
     * @return JsonResponse
     */
    public function me(): JsonResponse
    {
        return response()->json([
            'user' => new UserResource(auth()->user())
        ]);
    }

    /**
     * Get the token array structure.
     *
     * @param string $token
     * @param CsvUpload|null $csvUpload
     * @return JsonResponse
     */
    protected function respondWithToken(string $token, $csvUpload = null): JsonResponse
    {
        $response = [
            'user' => new UserResource(auth()->user()),
            'authorization' => [
                'token' => $token,
                'type' => 'bearer',
                'expires_in' => auth()->factory()->getTTL() * 60
            ]
        ];

        if ($csvUpload) {
            $response['csv_upload'] = [
                'id' => $csvUpload->id,
                'status' => $csvUpload->status,
                'message' => 'Your activity data from smrt.csv has been uploaded and is being processed.'
            ];
        } else {
            $response['csv_upload'] = [
                'status' => 'none',
                'message' => 'No activity data found. Please update the smrt.csv file in the public directory with your user ID.'
            ];
        }

        return response()->json($response);
    }
}
