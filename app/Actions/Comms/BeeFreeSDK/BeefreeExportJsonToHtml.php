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
use Illuminate\Support\Arr;

class BeefreeExportJsonToHtml extends OrgAction
{
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

    public function rules(): array
    {
        return [
            'json' => ['required', 'array'],
        ];
    }
}
