<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\LoginRequest;
use App\Http\Requests\V1\RegisterRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use App\Mail\WelcomeMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Register a new user and optionally issue an API token
     *
     * Crea un nuevo usuario validando la petición con `RegisterRequest`. Si se
     * proporciona `device_name` se crea un personal access token y se devuelve
     * junto con el `UserResource`. El token en texto plano se muestra sólo una vez.
     *
     * @group Authentication
     *
     * @bodyParam name string required The user's full name. Example: "Juan Pérez"
     * @bodyParam email string required The user's email. Example: "user@example.com"
     * @bodyParam password string required Minimum 8 characters.
     * @bodyParam password_confirmation string required Must match `password`.
     * @bodyParam device_name string|null Optional device name used to name the token. Example: "iPhone 12"
     *
     * @response 201 {
     *  "data": {
     *    "id": 1,
     *    "name": "Juan Pérez",
     *    "email": "user@example.com",
     *    "created_at": "2026-03-27T12:00:00.000000Z",
     *    "updated_at": "2026-03-27T12:00:00.000000Z"
     *  },
     *  "meta": {
     *    "access_token": "plain-text-token",
     *    "token_type": "Bearer"
     *  }
     * }
     *
     * @unauthenticated
     */
    public function register(RegisterRequest $request)
    {
        $data = $request->validated();
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);

        $device = $data['device_name'] ?? 'api-token';
        $token = $user->createToken($device, ['*'])->plainTextToken;

        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Throwable $e) {
            Log::error('Failed to queue welcome email for user '.$user->id.': '.$e->getMessage());
        }

        return (new UserResource($user))
            ->additional([
                'meta' => [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                ],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Login and issue token
     *
     * Authenticate the user with email and password and return a `UserResource`
     * plus a one-time plain-text personal access token. The token is shown only once.
     *
     * @group Authentication
     *
     * @bodyParam email string required The user's email. Example: "user@example.com"
     * @bodyParam password string required The user's password.
     * @bodyParam device_name string|null Optional device name used to name the token.
     *
     * @response 200 {
     *  "data": {
     *    "id": 1,
     *    "name": "Juan Pérez",
     *    "email": "user@example.com",
     *    "created_at": "2026-03-27T12:00:00.000000Z",
     *    "updated_at": "2026-03-27T12:00:00.000000Z"
     *  },
     *  "meta": {
     *    "access_token": "plain-text-token",
     *    "token_type": "Bearer"
     *  }
     * }
     * @response 401 {
     *  "message": "The provided credentials are incorrect."
     * }
     *
     * @unauthenticated
     */
    public function login(LoginRequest $request)
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'The provided credentials are incorrect.'], 401);
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
     *
     * @group Authentication
     *
     * @header Authorization string required Bearer token. Example: "Bearer {token}"
     *
     * @response 204
     * @response 401 {
     *  "message": "Not authenticated."
     * }
     */
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
