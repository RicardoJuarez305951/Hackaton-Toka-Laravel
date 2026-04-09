<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');
        $merchantCode = $request->header('Alipay-MerchantCode');

        if (! $baseUrl || ! $appId) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al acceder al servicio de Toka.',
            ], 500);
        }

        if (! $merchantCode || strlen($merchantCode) !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'El header Alipay-MerchantCode es requerido y debe tener 5 caracteres.',
            ], 400);
        }

        if (strlen($appId) !== 16) {
            return response()->json([
                'success' => false,
                'message' => 'La configuración de Toka no es válida.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'userId' => 'required|string',
            'orderTitle' => 'required|string',
            'orderAmount.value' => 'required|string',
            'orderAmount.currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Los datos del pago no son válidos.',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        try {
            $endpoint = rtrim($baseUrl, '/').'/v1/payment/create';

            $response = Http::withHeaders([
                'X-App-Id' => $appId,
                'Alipay-MerchantCode' => $merchantCode,
                'Accept' => 'application/json',
            ])->post($endpoint, [
                'userId' => $request->input('userId'),
                'orderTitle' => $request->input('orderTitle'),
                'orderAmount' => [
                    'value' => $request->input('orderAmount.value'),
                    'currency' => $request->input('orderAmount.currency'),
                ],
            ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'success' => false,
                'statusCode' => $response->status(),
                'message' => 'Hubo un error al crear el pago con Toka.',
                'data' => [
                    'upstreamResponse' => $response->json() ?? (object) [],
                ],
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.',
            ], 500);
        }
    }

    public function close(Request $request)
    {
        $baseUrl = config('services.toka.url');
        $appId = config('services.toka.program_id');
        $merchantCode = $request->header('Alipay-MerchantCode');

        if (! $baseUrl || ! $appId) {
            return response()->json([
                'success' => false,
                'message' => 'Hubo un error al acceder al servicio de Toka.',
            ], 500);
        }

        if (! $merchantCode || strlen($merchantCode) !== 5) {
            return response()->json([
                'success' => false,
                'message' => 'El header Alipay-MerchantCode es requerido y debe tener 5 caracteres.',
            ], 400);
        }

        if (strlen($appId) !== 16) {
            return response()->json([
                'success' => false,
                'message' => 'La configuración de Toka no es válida.',
            ], 500);
        }

        $validator = Validator::make($request->all(), [
            'paymentId' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'El paymentId es requerido y debe ser un texto válido.',
                'data' => [
                    'errors' => $validator->errors(),
                ],
            ], 422);
        }

        try {
            $endpoint = rtrim($baseUrl, '/').'/v1/payment/close';

            $response = Http::withHeaders([
                'X-App-Id' => $appId,
                'Alipay-MerchantCode' => $merchantCode,
                'Accept' => 'application/json',
            ])->post($endpoint, [
                'paymentId' => $request->input('paymentId'),
            ]);

            if ($response->successful()) {
                return response()->json($response->json(), 200);
            }

            return response()->json([
                'success' => false,
                'statusCode' => $response->status(),
                'message' => 'Hubo un error al cerrar el pago con Toka.',
                'data' => [
                    'upstreamResponse' => $response->json() ?? (object) [],
                ],
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de comunicación con Toka.',
            ], 500);
        }

    }
}
