<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
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
 * Finishes Meta's Embedded Signup, the only way to connect a number that is in use on the
 * WhatsApp Business app without deleting the app account (Coexistence). The browser brings
 * back a code that expires within a minute, so it is exchanged straight away for the business
 * token Meta issues for the shop's own account, kept on the shop beside the number.
 *
 * Whether the number is a Coexistence one is read from Meta, not from the browser. Such a
 * number is already registered by the app and is never registered again; Meta gives 24 hours
 * to ask for its contacts and chat history, which then arrive on the history webhook.
 */
class ConnectWhatsappEmbeddedSignup extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappPhoneNumberResponse;

    /**
     * @param  array{code: string, waba_id: string, phone_number_id?: string|null}  $modelData
     *
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function handle(Shop $shop, array $modelData): array
    {
        ['app_id' => $appId, 'app_secret' => $appSecret] = $this->metaAppCredentials($shop->organisation);

        if ($appId === '' || $appSecret === '') {
            return ['ok' => false, 'message' => __('Set the organisation Meta App ID and App Secret first.'), 'code' => 422];
        }

        $token = Http::get($this->whatsappEndpoint('oauth/access_token'), [
            'client_id'     => $appId,
            'client_secret' => $appSecret,
            'code'          => $modelData['code'],
        ]);

        $accessToken = (string) Arr::get($token->json(), 'access_token');

        if ($token->failed() || $accessToken === '') {
            return $this->graphFailure($token, __('Meta did not confirm the WhatsApp signup.'));
        }

        // The session event Meta posts to the browser names the account; the number is looked
        // up under it when the event leaves it out.
        $phoneNumberId = (string) (Arr::get($modelData, 'phone_number_id') ?: Arr::get(
            Http::withToken($accessToken)->get($this->whatsappEndpoint($modelData['waba_id'].'/phone_numbers'))->json(),
            'data.0.id'
        ));

        if ($phoneNumberId === '') {
            return ['ok' => false, 'message' => __('Meta did not say which number was connected.'), 'code' => 422];
        }

        $phone = Http::withToken($accessToken)->get($this->whatsappEndpoint($phoneNumberId), [
            'fields' => 'display_phone_number,'.GetWhatsappPhoneNumberStatus::COEXISTENCE_FIELDS,
        ]);

        if ($phone->failed()) {
            return $this->graphFailure($phone, __('Meta did not return the number that was connected.'));
        }

        $isCoexistence = (bool) Arr::get($phone->json(), 'is_on_biz_app');

        $settings = $shop->settings ?? [];
        Arr::set($settings, 'whatsapp', array_merge(Arr::get($settings, 'whatsapp') ?? [], [
            'enabled'         => true,
            'waba_id'         => $modelData['waba_id'],
            'phone_number_id' => $phoneNumberId,
            'phone_number'    => '+'.preg_replace('/\D/', '', (string) Arr::get($phone->json(), 'display_phone_number')),
            'access_token'    => $accessToken,
            'mode'            => $isCoexistence ? self::WHATSAPP_COEXISTENCE : 'cloud_api',
            'connected_at'    => now()->toIso8601String(),
        ]));
        $shop->update(['settings' => $settings]);

        $subscribed = SubscribeWhatsappApp::make()->handle($shop);

        if (!$subscribed['ok']) {
            return $subscribed;
        }

        if ($isCoexistence) {
            foreach (['smb_app_state_sync', 'history'] as $syncType) {
                $sync = Http::withToken($accessToken)->post($this->whatsappEndpoint($phoneNumberId.'/smb_app_data'), [
                    'messaging_product' => 'whatsapp',
                    'sync_type'         => $syncType,
                ]);

                if ($sync->failed()) {
                    return $this->graphFailure($sync, __('Meta did not start copying the chats from the WhatsApp Business app.'));
                }

                $settings = $shop->refresh()->settings;
                Arr::set($settings, 'whatsapp.coexistence_sync.'.$syncType, Arr::get($sync->json(), 'request_id'));
                $shop->update(['settings' => $settings]);
            }
        }

        GetWhatsappPhoneNumberStatus::make()->handle($shop->refresh());

        return ['ok' => true, 'data' => ['mode' => Arr::get($shop->refresh()->settings, 'whatsapp.mode')]];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'shop-admin.'.$this->shop->id]);
    }

    public function rules(): array
    {
        return [
            'code'            => ['required', 'string'],
            'waba_id'         => ['required', 'string', 'regex:/^\d+$/'],
            'phone_number_id' => ['nullable', 'string', 'regex:/^\d+$/'],
        ];
    }

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>, code?: int}
     */
    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }
}
