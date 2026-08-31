<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Tue, 15 Dec 2025 11:08:59 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, eka yudinata
 */

namespace App\Actions\Comms\BeeFreeSDK;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
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

        $builderType = Arr::get($modelData, 'builder_type', 'email');
        $beefreeSettings = $builderType === 'email' ? Arr::get($this->group->settings, 'beefree', []) : Arr::get($this->group->settings, 'beefree.page_builder', []);
        $clientId = Arr::get($beefreeSettings, 'client_id');
        $clientSecret = Arr::get($beefreeSettings, 'client_secret');

        if (!$clientId || !$clientSecret) {
            throw new \Exception('BeeFree credentials not configured');
        }

        $uid = Arr::get($modelData, 'uid', 'CmsUserName');

        // ponytail: 4 min cache, BeeFree access tokens last 5 min and the SDK refreshes them itself
        return Cache::remember(
            "beefree-token:{$this->group->id}:{$builderType}:{$uid}",
            240,
            fn () => $this->fetchToken($clientId, $clientSecret, $uid)
        );
    }

    protected function fetchToken(string $clientId, string $clientSecret, string $uid): array
    {
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post('https://auth.getbee.io/loginV2', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'uid' => $uid
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
            'builder_type' => ['sometimes', 'required', 'string', 'in:page,email'],
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

    public function action(Organisation $organisation, array $modelData = []): array
    {
        $this->asAction = true;
        $this->initialisation($organisation, $modelData);
        return $this->handle($organisation, $modelData);
    }
}
