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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\ActionRequest;

/**
 * A registered number still delivers nothing to Aiku unless the Meta app is subscribed to
 * the WABA's webhooks, which is a separate Graph resource from the number itself. Read live
 * for the same reason the number status is: the subscription can be removed at Meta's end.
 */
class GetWhatsappSubscribedApps extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
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

        $response = Http::withToken($accessToken)->get($this->whatsappEndpoint($wabaId.'/subscribed_apps'));

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not return the app subscriptions for this account.'));
        }

        return [
            'ok'   => true,
            // ponytail: any subscribed app counts as subscribed, match on app id when a WABA
            // has to distinguish Aiku's app from another subscriber.
            'data' => ['subscribed' => filled(Arr::get($response->json(), 'data'))],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
