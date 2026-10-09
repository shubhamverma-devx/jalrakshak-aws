<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OfficerAuthController extends Controller
{
    /** Single hardcoded officer login. Returns the shared demo token. */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $okEmail = hash_equals(strtolower(config('jalrakshak.officer.email')), strtolower($data['email']));
        $okPassword = hash_equals(config('jalrakshak.officer.password'), $data['password']);

        if (! $okEmail || ! $okPassword) {
            return response()->json(['message' => 'Those officer details did not match.'], 422);
        }

        return response()->json([
            'token' => config('jalrakshak.officer.token'),
            'officer' => [
                'email' => config('jalrakshak.officer.email'),
                'name' => 'District Control Room',
            ],
        ]);
    }
}
