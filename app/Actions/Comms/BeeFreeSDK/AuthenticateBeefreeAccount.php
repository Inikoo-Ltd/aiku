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
use App\Actions\OrgAction;
use App\Models\Comms\Outbox;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

class AuthenticateBeefreeAccount extends OrgAction
{
    public string $commandSignature = 'beefree:auth {outbox:Outbox} {modelData?*}';

    public function handle(?Outbox $outbox, ?array $modelData): array
    {

        // note: take the client id and client secret from group settings
        $beefreeSettings = $this->group->settings['beefree'];
        $clientId = $beefreeSettings['client_id'];
        $clientSecret = $beefreeSettings['client_secret'];
        \Log::info("beefreeSettings: " . json_encode($beefreeSettings));

        if (!$clientId || !$clientSecret) {
            throw new \Exception('BeeFree credentials not configured');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://auth.getbee.io/loginV2', [
            'client_id' => $clientId ?? null,
            'client_secret' => $clientSecret ?? null,
            'uid' => Arr::get($modelData, 'uid', 'test1-clientside')
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

    // public function asController(): JsonResponse
    // {
    //     try {
    //         $uid = request()->input('uid', 'test1-clientside');
    //         $result = $this->handle($uid);

    //         return response()->json($result);
    //     } catch (\Exception $e) {
    //         Log::error('Auth error: ' . $e->getMessage());

    //         return response()->json([
    //             'error' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    public function action(Outbox $outbox, array $modelData): array
    {

        $this->initialisation($outbox->organisation, $modelData);
        return $this->handle($outbox->uid, $this->validatedData);
    }

    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }

    public function rules(): array
    {
        $rules = [
            'uid' => ['sometimes', 'required', 'string'],
        ];

        return $rules;
    }

    public function asCommand(Command $command): void
    {

        $this->handle($command->argument('outbox'), $command->argument('modelData'));
    }
}
