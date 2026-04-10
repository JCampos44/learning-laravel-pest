<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Knuckles\Scribe\Attributes\Authenticated;
use Knuckles\Scribe\Attributes\BodyParam;
use Knuckles\Scribe\Attributes\Group;
use Knuckles\Scribe\Attributes\Header;
use Knuckles\Scribe\Attributes\Response;
use Knuckles\Scribe\Attributes\Unauthenticated;

class AuthController extends Controller
{
    /**
     * Register a new user and send an email verification notification.
     *
     * Creates a new user using the validated `RegisterRequest` payload and
     * dispatches Laravel's native email verification notification after the
     * account is successfully created.
     */
    #[Group('Authentication')]
    #[Unauthenticated]
    #[BodyParam('name', 'string', 'The user\'s full name.', true, 'Juan Pérez')]
    #[BodyParam('email', 'string', 'The user\'s email address.', true, 'user@example.com')]
    #[BodyParam('password', 'string', 'The user\'s password. Minimum 8 characters.', true, 'password123')]
    #[BodyParam('password_confirmation', 'string', 'The password confirmation. Must match `password`.', true, 'password123')]
    #[Response([
        'data' => [
            'id' => 1,
            'name' => 'Juan Pérez',
            'email' => 'user@example.com',
            'created_at' => '2026-03-27T12:00:00.000000Z',
            'updated_at' => '2026-03-27T12:00:00.000000Z',
        ],
    ], status: 201)]
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();

        $user = User::create($data);

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::error('Failed to send verification email for user '.$user->id.': '.$e->getMessage());
        }

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Login and issue token
     *
     * Authenticate the user with email and password and return a `UserResource`
     * plus a one-time plain-text personal access token. The token is shown only once.
     * The user must have a verified email address before a token is issued.
     */
    #[Group('Authentication')]
    #[Unauthenticated]
    #[BodyParam('email', 'string', 'The user\'s email address.', true, 'user@example.com')]
    #[BodyParam('password', 'string', 'The user\'s password.', true, 'password123')]
    #[BodyParam('device_name', 'string', 'Optional device name used to name the token.', false, 'iPhone 12', nullable: true)]
    #[Response([
        'data' => [
            'id' => 1,
            'name' => 'Juan Pérez',
            'email' => 'user@example.com',
            'created_at' => '2026-03-27T12:00:00.000000Z',
            'updated_at' => '2026-03-27T12:00:00.000000Z',
        ],
        'meta' => [
            'access_token' => 'plain-text-token',
            'token_type' => 'Bearer',
        ],
    ], status: 200)]
    #[Response(['message' => 'The provided credentials are incorrect.'], status: 401)]
    #[Response(['message' => 'Please verify your email address.'], status: 403)]
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Please verify your email address.'], 403);
        }

        $device = $data['device_name'] ?? 'api-token';
        $token = $user->createToken($device, ['*'])->plainTextToken;

        return (new UserResource($user))
            ->additional(['meta' => ['access_token' => $token, 'token_type' => 'Bearer']])
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Revoke the current personal access token.
     *
     * Revokes the access token associated with the current request.
     */
    #[Group('Authentication')]
    #[Authenticated]
    #[Header('Authorization', 'Bearer {token}')]
    #[Response(status: 204)]
    #[Response(['message' => 'Not authenticated.'], status: 401)]
    public function logout(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Not authenticated.'], 401);
        }

        $token = $user->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->noContent();
    }
}
