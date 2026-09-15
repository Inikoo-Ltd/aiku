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
 * Reads the number's live state from Meta rather than a stored copy: a number can go
 * offline on Meta's side at any time, so a cached badge would be reassuring and wrong.
 */
class GetWhatsappPhoneNumberStatus extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    private const FIELDS = 'status,code_verification_status,verified_name,quality_rating,display_phone_number';

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function handle(Shop $shop): array
    {
        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return $this->notConfigured();
        }

        $response = Http::withToken($accessToken)->get(
            $this->whatsappEndpoint($phoneNumberId),
            ['fields' => self::FIELDS]
        );

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not return the status of this number.'));
        }

        return [
            'ok'   => true,
            'data' => Arr::only($response->json() ?? [], explode(',', self::FIELDS)),
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
