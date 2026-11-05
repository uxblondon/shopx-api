<?php

namespace App\Http\Controllers\Auth;

use OpenApi\Attributes as OA;

use App\Http\Controllers\Controller;


#[OA\Tag(name: "auth", description: "All Endpoints of Authentication")]
class AuthController extends Controller
{
    

    #[OA\Post(
        path: "/api/auth/login",
        tags: ["auth"],
        summary: "Get access token for the API",
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['email', 'password'], properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ]
        )),
        responses: [new OA\Response(response: 200, description: "Successful response"), new OA\Response(response: 400, description: "Bad request")],
    )]
    public function login()
    {
        $credentials = request()->validate([
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|max:4096',
        ]);
        $credentials += ['admin' => 1, 'disabled' => 0, 'deleted_at' => null];

        if (!$token = auth()->attempt($credentials)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $this->respondWithToken($token);
    }

    /**
     * Get the authenticated User.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function me()
    {
        return response()->json(auth()->user());
    }

    #[OA\Post(
        path: "/api/auth/logout",
        tags: ["auth"],
        summary: "Logout from the API",
        responses: [new OA\Response(response: 200, description: "Successful response"), new OA\Response(response: 400, description: "Bad request")],
    )]
    public function logout()
    {
        auth()->logout();

        return response()->json(['message' => 'Successfully logged out']);
    }

    /**
     * Refresh a token.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh()
    {
        return $this->respondWithToken(auth()->refresh());
    }

    /**
     * Get the token array structure.
     *
     * @param  string $token
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function respondWithToken($token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60
        ]);
    }
}
