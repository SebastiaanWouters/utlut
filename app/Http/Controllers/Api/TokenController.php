<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TokenController extends Controller
{
    /**
     * Issue a new token for the authenticated user and store its hash.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $token = Str::random(40);
        $hash = hash('sha256', $token);

        $deviceToken = DeviceToken::create([
            'user_id' => Auth::id(),
            'token' => $token, // Storing plain for now as per current middleware, but also storing hash
            'token_hash' => $hash,
            'name' => $request->input('name'),
        ]);

        return response()->json([
            'ok' => true,
            'token' => $token,
        ]);
    }
}
