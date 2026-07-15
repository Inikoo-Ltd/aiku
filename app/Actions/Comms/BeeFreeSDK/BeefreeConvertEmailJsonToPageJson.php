<?php

/*
 * Author: eka yudinata <ekayudintha@gmail.com>
 * Created: Thu, 09 Jul 2026 08:40:27 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\BeeFreeSDK;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Actions\OrgAction;
use App\Models\Comms\Mailshot;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class BeefreeConvertEmailJsonToPageJson extends OrgAction
{
    public function handle(Organisation $organisation, Mailshot $mailshot): array
    {
        $authResponse = AuthenticateBeefreeAccount::make()->action($organisation, []);
        $token = Arr::get($authResponse, 'access_token');

        if (!$token) {
            Log::error('BeeFree access token not found in auth response', [
                'response' => $authResponse
            ]);
            throw new \Exception('BeeFree access token not found in auth response');
        }

        $json = $mailshot?->email?->liveSnapshot?->layout ?? [];
        if (empty($json)) {
            Log::error('BeeFree email JSON not found for mailshot', [
                'mailshot_id' => $mailshot->id
            ]);
            throw new \Exception('BeeFree email JSON not found for mailshot');
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Content-Type' => 'application/json',
        ])->post('https://api.getbee.io/v1/conversion/email-to-page', [
            'template' => $json
        ]);

        if ($response->successful()) {
            return $response->json();
        }

        Log::error('BeeFree convert email JSON to page JSON failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        throw new \Exception('Failed to convert BeeFree email JSON to page JSON');
    }

    public function asController(Mailshot $mailshot, ActionRequest $request): array
    {
        $organisation = $mailshot->organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $mailshot);
    }

    public function jsonResponse(array $result): JsonResponse
    {
        return response()->json($result);
    }
}
