<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Tue, 15 Dec 2025 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\Comms\BeeFreeSDK;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthenticateBeefreeAccount
{
    use AsAction;


    public string $commandSignature = 'beefree:auth';

    public function handle(string $uid = null): array
    {

        // note: take the client id and client secret from group settings
        $clientId = "";
        $clientSecret = "";

        if (!$clientId || !$clientSecret) {
            throw new \Exception('BeeFree credentials not configured');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://auth.getbee.io/loginV2', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'uid' => $uid ?? "test1-clientside"
        ]);

        \Log::info("data Repponse: " . json_encode($response->json()));
        if ($response->successful()) {
            return $response->json();
        }

        Log::error('BeeFree auth failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        throw new \Exception('Failed to authenticate with BeeFree');
    }

    public function asController(): JsonResponse
    {
        try {
            $uid = request()->input('uid', 'test1-clientside');
            $result = $this->handle($uid);

            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('Auth error: ' . $e->getMessage());

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }

    public function asCommand(): void
    {
        $this->handle(request()->input('uid', 'test1-clientside'));
    }
}
