<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappPhoneNumberResponse;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;

/**
 * Subscribes the Meta app behind the access token to this WABA's webhooks, which is what
 * lets inbound messages and status updates reach Aiku. Meta infers the app from the token,
 * so the call carries no body.
 */
class SubscribeWhatsappApp extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function handle(Shop $shop): array
    {
        [
            'waba_id'      => $wabaId,
            'access_token' => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($wabaId === '' || $accessToken === '') {
            return $this->notConfigured();
        }

        $response = Http::withToken($accessToken)->post($this->whatsappEndpoint($wabaId.'/subscribed_apps'));

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not subscribe the app to this account.'));
        }

        return ['ok' => true];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
