<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Credenciales inválidas',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'coins' => $user->coins,
            ],
            // Hackathon mode: lightweight token placeholder (no Sanctum dependency)
            'token' => base64_encode($user->id.'|'.Str::random(40)),
        ]);
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'coins' => 1000,
        ]);

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'coins' => $user->coins,
            ],
            // Hackathon mode: lightweight token placeholder (no Sanctum dependency)
            'token' => base64_encode($user->id.'|'.Str::random(40)),
        ]);
    }

    public function authenticate(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');

        if (! $baseUrl || ! $appId) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al acceder al servicio de Toka.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'authCode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'El authCode es requerido y debe ser un texto válido.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $endpoint = rtrim($baseUrl, '/').'/v1/user/authenticate';

            $response = Http::withHeaders([
                'X-App-Id' => $appId,
                'Accept'   => 'application/json',
            ])->post($endpoint, [
                'authCode' => $request->input('authCode'),
            ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'success' => false,
                'statusCode' => $response->status(),
                'message' => 'Hubo un error al autenticar con Toka.',
                'error_details' => $response->json()
            ], $response->status());

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.'
            ], 500);
        }
    }
}
