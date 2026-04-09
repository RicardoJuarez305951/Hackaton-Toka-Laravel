<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    private function getTokaConfig()
    {
        return [
            'baseUrl' => config('services.toka.url'),
            'appId' => config('services.toka.program_id'),
            'merchantCode' => config('services.toka.merchant_code'),
            'caBundle' => config('services.toka.ca_bundle'),
        ];
    }

    public function create(Request $request)
    {
        $config = $this->getTokaConfig();

        if (!$config['baseUrl'] || !$config['appId'] || !$config['merchantCode']) {
            return response()->json(['success' => false, 'message' => 'Configuración de Toka incompleta.'], 500);
        }

        $validator = Validator::make($request->all(), [
            'userId' => 'required|string',
            'orderTitle' => 'required|string',
            'orderAmount.value' => 'required|string',
            'orderAmount.currency' => 'required|string|size:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Datos inválidos.', 'errors' => $validator->errors()], 422);
        }

        try {
            $endpoint = rtrim($config['baseUrl'], '/') . '/v1/payment/create';
            $requestBuilder = Http::withHeaders([
                'X-App-Id' => $config['appId'],
                'Alipay-MerchantCode' => $config['merchantCode'],
                'Accept' => 'application/json',
            ])->withToken($request->bearerToken())->timeout(30);

            if ($config['caBundle']) {
                $requestBuilder = $requestBuilder->withOptions(['verify' => $config['caBundle']]);
            }

            $response = $requestBuilder->post($endpoint, $request->only(['userId', 'orderTitle', 'orderAmount']));

            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error('Toka Payment Create Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error de comunicación con Toka.'], 500);
        }
    }

    public function inquiry(Request $request)
    {
        $config = $this->getTokaConfig();
        $paymentId = $request->input('paymentId');

        if (!$paymentId) {
            return response()->json(['success' => false, 'message' => 'paymentId es requerido.'], 422);
        }

        try {
            $endpoint = rtrim($config['baseUrl'], '/') . '/v1/payment/inquiry';
            $requestBuilder = Http::withHeaders([
                'X-App-Id' => $config['appId'],
                'Accept' => 'application/json',
            ])->withToken($request->bearerToken())->timeout(30);

            if ($config['caBundle']) {
                $requestBuilder = $requestBuilder->withOptions(['verify' => $config['caBundle']]);
            }

            $response = $requestBuilder->post($endpoint, ['paymentId' => $paymentId]);

            return response()->json($response->json(), $response->status());
        } catch (\Exception $e) {
            Log::error('Toka Payment Inquiry Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error de comunicación con Toka.'], 500);
        }
    }

    public function finalize(Request $request)
    {
        $paymentId = $request->input('paymentId');
        $tokaUserId = $request->input('tokaUserId');

        if (!$paymentId || !$tokaUserId) {
            return response()->json(['success' => false, 'message' => 'Datos insuficientes para finalizar.'], 422);
        }

        // 1. Verificar el pago con Toka
        $inquiryResponse = $this->inquiry($request);
        $data = $inquiryResponse->getData(true);

        if (!$inquiryResponse->isSuccessful() || !($data['success'] ?? false)) {
            return response()->json(['success' => false, 'message' => 'No se pudo verificar el pago.'], 400);
        }

        $paymentStatus = $data['data']['paymentStatus'] ?? '';

        if ($paymentStatus !== 'SUCCESS') {
            return response()->json(['success' => false, 'message' => 'El pago no ha sido completado. Estado: ' . $paymentStatus], 400);
        }

        // 2. Si es exitoso, sumar monedas al usuario
        $user = User::where('toka_id', $tokaUserId)->first();
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Usuario no encontrado en base local.'], 404);
        }

        $amount = $data['data']['paymentAmount'] ?? 0;
        // Asumiendo conversión de 10 TC por cada 1 MXN (ajustar según necesidad)
        $coinsToAdd = (int)($amount * 10);

        $user->coins += $coinsToAdd;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => "¡Pago verificado! Se han añadido {$coinsToAdd} TokaCoins.",
            'new_balance' => $user->coins
        ]);
    }

    public function close(Request $request)
    {
        $config = $this->getTokaConfig();
        $paymentId = $request->input('paymentId');

        try {
            $endpoint = rtrim($config['baseUrl'], '/') . '/v1/payment/close';
            $response = Http::withHeaders([
                'X-App-Id' => $config['appId'],
                'Accept' => 'application/json',
            ])->withToken($request->bearerToken())->post($endpoint, ['paymentId' => $paymentId]);

            return response()->json($response->json(), $response->status());
        } catch (\Exception) {
            return response()->json(['success' => false, 'message' => 'Error al cerrar pago.'], 500);
        }
    }

    public function refund(Request $request)
    {
        $config = $this->getTokaConfig();
        try {
            $endpoint = rtrim($config['baseUrl'], '/') . '/v1/payment/refund';
            $response = Http::withHeaders([
                'X-App-Id' => $config['appId'],
                'Alipay-MerchantCode' => $config['merchantCode'],
                'Accept' => 'application/json',
            ])->withToken($request->bearerToken())->post($endpoint, $request->all());

            return response()->json($response->json(), $response->status());
        } catch (\Exception) {
            return response()->json(['success' => false, 'message' => 'Error al procesar reembolso.'], 500);
        }
    }
}

