<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DebugController extends Controller
{
    private function resolveDemoUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'demo@toka.local'],
            [
                'name' => 'Demo Toka',
                'password' => Hash::make('password123'),
                'coins' => 1000,
            ]
        );
    }

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

        $user = $this->resolveDemoUser();
        $user->increment('coins', $request->amount);

        return redirect('/')->with([
            'message' => "Se agregaron {$request->amount} coins. Nuevo balance: {$user->fresh()->coins} TP",
            'message_type' => 'success',
        ]);
    }
}
