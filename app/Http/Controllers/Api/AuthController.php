<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    public function authenticate(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');

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
            $endpoint = rtrim($baseUrl, '/').'/v1/user/authenticate';

            $response = Http::withHeaders([
                'X-App-Id' => $appId,
                'Accept'   => 'application/json',
            ])->post($endpoint, [
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
                'user' => [
                    'id' => $user->id,
                    'toka_id' => $user->toka_id,
                    'coins' => $user->coins,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.',
            ], 500);
        }
    }
}
