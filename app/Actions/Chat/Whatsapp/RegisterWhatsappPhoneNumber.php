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
 * Last step: registers the verified number on the cloud API with its two step
 * verification PIN, which is what finally lets the shop send and receive.
 *
 * The PIN is passed through to Meta and never stored, so it is also kept out of logs and
 * out of the shop settings. Meta asks for the same PIN again on any later re-registration,
 * so whoever sets it has to keep it.
 */
class RegisterWhatsappPhoneNumber extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function handle(Shop $shop, string $pin): array
    {
        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return $this->notConfigured();
        }

        $response = Http::withToken($accessToken)->asForm()->post(
            $this->whatsappEndpoint($phoneNumberId.'/register'),
            [
                'messaging_product' => 'whatsapp',
                'pin'               => $pin,
            ]
        );

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not register this number.'));
        }

        return ['ok' => true];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function rules(): array
    {
        return [
            'pin' => ['required', 'digits:6'],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData['pin']);
    }
}
