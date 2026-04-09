<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class DebugController extends Controller
{
    private const DEFAULT_WEB_BALANCE = 1000;

    public function addCoins(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'amount' => 'required|integer|min:1',
        ]);

        $user = User::find($request->user_id);
        $user->increment('coins', $request->amount);

        return response()->json([
            'success' => true,
            'message' => "Se agregaron {$request->amount} coins",
            'coins' => $user->fresh()->coins,
        ]);
    }

    public function addCoinsView(Request $request)
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        $currentBalance = (int) $request->session()->get('web_demo_balance', self::DEFAULT_WEB_BALANCE);
        $newBalance = $currentBalance + (int) $request->amount;
        $request->session()->put('web_demo_balance', $newBalance);

        return redirect('/')->with([
            'message' => "Se agregaron {$request->amount} coins. Nuevo balance: {$newBalance} TP",
            'message_type' => 'success',
        ]);
    }
}
