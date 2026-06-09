<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Wed, 10 Jun 2026 11:22:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\BeeFreeSDK;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class BeefreeExportJsonToHtml extends OrgAction
{
    public string $commandSignature = 'beefree:export-json-to-html {organisation} {json}';

    public function handle(Organisation $organisation, array $modelData)
    {
        $authResponse = AuthenticateBeefreeAccount::make()->action($organisation, $modelData);
        $token = Arr::get($authResponse, 'access_token');

        if (!$token) {
            Log::error('BeeFree access token not found in auth response', [
                'response' => $authResponse
            ]);
            throw new \Exception('BeeFree access token not found in auth response');
        }

        $json = Arr::get($modelData, 'json');

        $htmlResponse = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.getbee.io/v1/message/html', $json);

        if ($htmlResponse->successful()) {
            return $htmlResponse->body();
        }

        Log::error('BeeFree export JSON to HTML failed', [
            'status' => $htmlResponse->status(),
            'body' => $htmlResponse->body()
        ]);

        throw new \Exception('Failed to export BeeFree JSON to HTML');
    }

    public function asController(Organisation $organisation, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);
        return $this->handle($organisation, $this->validatedData);
    }

    public function rules(): array
    {
        return [
            'json' => ['required', 'array'],
        ];
    }

    public function asCommand(Command $command): void
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();
        if (!$organisation) {
            $command->error('Organisation not found');
            return;
        }
        $modelData = ['json' => json_decode($command->argument('json'), true)];
        $this->initialisation($organisation, $modelData);
        $result = $this->handle($organisation, $modelData);
        $command->info('BeeFree JSON to HTML export successful');
        $command->info(json_encode($result, JSON_PRETTY_PRINT));
    }
}
