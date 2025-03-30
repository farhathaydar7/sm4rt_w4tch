<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Facades\JWTAuth;

class SocialAuthController extends Controller
{
    /**
     * Redirect the user to the Provider authentication page.
     *
     * @param string $provider
     * @return JsonResponse
     */
    public function redirectToProvider(string $provider): JsonResponse
    {
        try {
            $url = Socialite::driver($provider)->stateless()->redirect()->getTargetUrl();
            return response()->json(['url' => $url]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Provider not supported or misconfigured'], 400);
        }
    }

    /**
     * Obtain the user information from the Provider.
     *
     * @param Request $request
     * @param string $provider
     * @return JsonResponse
     */
    public function handleProviderCallback(Request $request, string $provider): JsonResponse
    {
        try {
            if ($request->has('error')) {
                return response()->json(['error' => $request->error_description], 401);
            }

            // For API clients that can't use the redirect flow
            if ($request->has('code')) {
                $providerUser = Socialite::driver($provider)->stateless()->user();
            } else if ($request->has('access_token')) {
                $providerUser = Socialite::driver($provider)
                    ->stateless()
                    ->userFromToken($request->access_token);
            } else {
                return response()->json(['error' => 'Invalid request'], 400);
            }

            // Find existing user or create a new one
            $user = User::where('email', $providerUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $providerUser->getName() ?? $providerUser->getNickname(),
                    'email' => $providerUser->getEmail(),
                    'password' => Hash::make(Str::random(16)),
                    'provider' => $provider,
                    'provider_id' => $providerUser->getId(),
                ]);
            } else {
                // Update provider information
                $user->provider = $provider;
                $user->provider_id = $providerUser->getId();
                $user->save();
            }

            // Generate JWT token
            $token = JWTAuth::fromUser($user);

            return response()->json([
                'user' => new UserResource($user),
                'authorization' => [
                    'token' => $token,
                    'type' => 'bearer',
                    'expires_in' => auth()->factory()->getTTL() * 60
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
