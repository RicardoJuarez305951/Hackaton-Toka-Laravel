<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');
        $caBundle = config('services.toka.ca_bundle');

        if (!$baseUrl || !$appId) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al acceder al servicio de Toka.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'authcode' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'statusCode' => 422,
                'message' => 'El authcode es requerido y debe ser un texto válido.',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $endpoint = rtrim($baseUrl, '/') . '/v1/user/authenticate';

            $requestBuilder = Http::withHeaders([
                'X-App-Id' => $appId,
                'Accept' => 'application/json',
            ])->timeout(30);

            if ($caBundle) {
                $requestBuilder = $requestBuilder->withOptions([
                    'verify' => $caBundle,
                ]);
            }

            $response = $requestBuilder->post($endpoint, [
                'authcode' => $request->input('authcode'),
            ]);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'statusCode' => $response->status(),
                    'message' => 'Hubo un error al autenticar con Toka.',
                    'error_details' => $response->json(),
                ], $response->status());
            }

            $tokaData = $response->json('data');
            $tokaUserId = $tokaData['userId'] ?? null;

            if (!$tokaUserId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se recibió un userId válido de Toka.',
                ], 500);
            }

            $user = User::firstOrCreate(
                ['toka_id' => $tokaUserId],
                ['coins' => 1000]
            );

            return response()->json([
                'success' => true,
                'accessToken' => $tokaData['accessToken'] ?? null,
                'expiresIn' => $tokaData['expiresIn'] ?? null,
                'userId' => $tokaUserId,
                'user' => [
                    'id' => $user->id,
                    'toka_id' => $user->toka_id,
                    'coins' => $user->coins,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Toka Auth Error: ' . $e->getMessage(), [
                'endpoint' => $endpoint,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function userInfo(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');
        $caBundle = config('services.toka.ca_bundle');

        if (!$baseUrl || !$appId) {
            return response()->json([
                'success' => false,
                'message' => 'Configuración de Toka faltante.',
            ], 500);
        }

        // Recuperar el Bearer token que manda el frontend
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'No se proporcionó un token de autorización.',
            ], 401);
        }

        try {
            $endpoint = rtrim($baseUrl, '/') . '/v1/user/info';

            $requestBuilder = Http::withHeaders([
                'X-App-Id' => $appId,
                'Accept' => 'application/json',
            ])->withToken($token)->timeout(30);

            if ($caBundle) {
                $requestBuilder = $requestBuilder->withOptions([
                    'verify' => $caBundle,
                ]);
            }

            // Forward the payload from the frontend to Toka, in case Toka requires parameters like userId
            $payload = $request->all();

            $response = $requestBuilder->post($endpoint, empty($payload) ? (object)[] : $payload);

            if (!$response->successful()) {
                return response()->json([
                    'success' => false,
                    'statusCode' => $response->status(),
                    'message' => 'Hubo un error al obtener la información en Toka.',
                    'error_details' => $response->json(),
                ], $response->status());
            }

            return response()->json([
                'success' => true,
                'data' => $response->json('data'),
            ]);

        } catch (\Exception $e) {
            Log::error('Toka UserInfo Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
