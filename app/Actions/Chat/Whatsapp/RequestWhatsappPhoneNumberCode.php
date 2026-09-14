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
 * First step of bringing a number online: Meta sends a one time code to the number itself,
 * proving whoever is connecting it actually controls it.
 */
class RequestWhatsappPhoneNumberCode extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function handle(Shop $shop, string $codeMethod, string $language): array
    {
        [
            'phone_number_id' => $phoneNumberId,
            'access_token'    => $accessToken,
        ] = $this->whatsappCredentials($shop);

        if ($phoneNumberId === '' || $accessToken === '') {
            return $this->notConfigured();
        }

        $response = Http::withToken($accessToken)->asForm()->post(
            $this->whatsappEndpoint($phoneNumberId.'/request_code'),
            [
                'code_method' => $codeMethod,
                'language'    => $language,
            ]
        );

        if ($response->failed()) {
            return $this->graphFailure($response, __('Meta did not send the verification code.'));
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
            'code_method' => ['sometimes', 'in:SMS,VOICE'],
            'language'    => ['sometimes', 'string', 'max:8'],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, code?: int}
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(
            $shop,
            $request->validated('code_method', 'SMS'),
            $request->validated('language', 'en')
        );
    }
}
