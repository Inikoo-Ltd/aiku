<?php

/*
 * Author: Eka Yudinata <ekayudinata@gmail.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Calls;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The one place that posts to Meta's calling endpoint. Every action on a call — answering,
 * refusing, hanging up, dialling out — is the same request with a different verb, so they
 * share this rather than each repeating the credentials and the error handling.
 */
class SendWhatsappCallAction
{
    use AsAction;
    use WithWhatsappCredentials;

    /**
     * @param  array<string, mixed>  $payload
     *
     * @return array{ok: bool, body: array<string, mixed>}
     */
    public function handle(Shop $shop, array $payload): array
    {
        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            Log::warning('WhatsApp call action skipped, channel is not configured', [
                'shop_id' => $shop->id,
                'action'  => Arr::get($payload, 'action'),
            ]);

            return ['ok' => false, 'body' => []];
        }

        $response = Http::withToken($accessToken)->post(
            $this->whatsappEndpoint($phoneNumberId.'/calls'),
            array_merge(['messaging_product' => 'whatsapp'], $payload)
        );

        if ($response->failed()) {
            Log::warning('WhatsApp call action failed', [
                'shop_id' => $shop->id,
                'action'  => Arr::get($payload, 'action'),
                'call_id' => Arr::get($payload, 'call_id'),
                'status'  => $response->status(),
                'error'   => Arr::get($response->json(), 'error.message'),
            ]);

            return ['ok' => false, 'body' => (array) $response->json()];
        }

        return ['ok' => true, 'body' => (array) $response->json()];
    }
}
