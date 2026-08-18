<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;

class GetWhatsappMessageTemplate
{
    use AsAction;

    /**
     * @return array<string, mixed>
     */
    public function handle(Shop $shop): array
    {
        $wabaId      = (string) Arr::get($shop->settings, 'whatsapp.waba_id');
        $accessToken = (string) Arr::get($shop->organisation->settings, 'meta.access_key');

        if ($wabaId === '' || $accessToken === '') {
            Log::warning('WhatsApp WABA ID or access token is not set', ['shop' => $shop->slug]);

            return [];
        }

        $url = sprintf(
            '%s/%s/%s/message_templates',
            config('meta.base_endpoint'),
            config('meta.whatsapp.api_version'),
            $wabaId
        );

        $response = Http::withToken($accessToken)->get($url);

        $data = $response->json() ?? [];

        Log::info('WhatsApp message templates response', $data);

        return $data;
    }

    public function getCommandSignature(): string
    {
        return 'whatsapp:templates {shop : Shop slug}';
    }

    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $this->handle($shop);

        return 0;
    }
}
