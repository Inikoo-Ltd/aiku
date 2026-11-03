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
 * Only the organisation's own Meta app counts: another subscriber on the WABA (a previous
 * provider, another app) receives the webhooks Aiku needs, so it must not read as subscribed.
 */
class GetWhatsappSubscribedApps extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function handle(Shop|Organisation $parent): array
    {
        [
            'waba_id'      => $wabaId,
            'access_token' => $accessToken,
        ] = $parent instanceof Shop ? $this->whatsappCredentials($parent) : $this->procurementWhatsappCredentials($parent);

        if ($wabaId === '' || $accessToken === '') {
            return $this->notConfigured($parent);
        }

        $response = Http::withToken($accessToken)->get($this->whatsappEndpoint($wabaId.'/subscribed_apps'));

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not return the app subscriptions for this account.'));
        }

        $subscribedApps = collect(Arr::get($response->json(), 'data', []))
            ->map(fn (array $subscription) => [
                'id'   => (string) Arr::get($subscription, 'whatsapp_business_api_data.id'),
                'name' => (string) Arr::get($subscription, 'whatsapp_business_api_data.name'),
            ]);

        $organisation = $parent instanceof Shop ? $parent->organisation : $parent;
        $appId        = $this->metaAppCredentials($organisation)['app_id'];

        return [
            'ok'   => true,
            'data' => [
                'subscribed' => $appId === '' ? $subscribedApps->isNotEmpty() : $subscribedApps->contains('id', $appId),
                'apps'       => $subscribedApps->all(),
            ],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        if (! isset($this->shop)) {
            return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'org-supervisor.'.$this->organisation->id.'.procurement']);
        }

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

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function inProcurement(Organisation $organisation, ActionRequest $request): array
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }
}
