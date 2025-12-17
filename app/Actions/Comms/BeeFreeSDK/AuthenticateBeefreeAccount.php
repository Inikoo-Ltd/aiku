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
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class AuthenticateBeefreeAccount extends OrgAction
{
    public string $commandSignature = 'beefree:auth {organisation} {modelData?*}';

    public function handle(Organisation $organisation, array $modelData): array
    {

        $beefreeSettings = $this->group->settings['beefree'];
        $clientId = Arr::get($beefreeSettings, 'client_id');
        $clientSecret = Arr::get($beefreeSettings, 'client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('BeeFree credentials not configured');
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://auth.getbee.io/loginV2', [
            'client_id' => $clientId ?? null,
            'client_secret' => $clientSecret ?? null,
            'uid' => Arr::get($modelData, 'uid', 'CmsUserName')
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('BeeFree auth failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        throw new \Exception('Failed to authenticate with BeeFree');
    }

    public function asController(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation, $this->validatedData);
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
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();
        if (!$organisation) {
            $command->error('Organisation not found');
            return;
        }
        $modelData = $command->argument('modelData') ?? [];
        $this->initialisation($organisation, $modelData);
        $result = $this->handle($organisation, $modelData);
        $command->info('BeeFree authentication successful');
        $command->info(json_encode($result, JSON_PRETTY_PRINT));
    }
}
