<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created: Tue, 03 Jun 2025, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\CRM\WebUser\LogWebUserFailLogin;
use App\Actions\CRM\WebUser\LogWebUserLogin;
use App\Actions\IrisAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use App\Exceptions\GoogleCredentialVerificationException;
use Google\Service\Oauth2;
use Google_Client;

class GoogleLoginRetina extends IrisAction
{
    public function handle(Shop $shop, ActionRequest $request): array|WebUser
    {
        $websiteId = $request->input('website')->id;

        $googleUser = $this->google_user ?? [];
        $webUser    = WebUser::where('shop_id', $shop->id)
            ->where('email', Arr::get($googleUser, 'email'))
            ->first();

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        if (!$webUser) {
            LogWebUserFailLogin::dispatch(
                websiteId: $websiteId,
                credentials: [
                    'username' => Arr::get($googleUser, 'email'),
                ],
                ip: request()->ip(),
                userAgent: $request->header('User-Agent'),
                datetime: now(),
                geoLocation: $geoLocation,
                source: 'G'
            )->delay(now()->addSeconds(5));

            return $googleUser;
        }

        $webUser->update([
            'google_id' => Arr::get($googleUser, 'id'),
        ]);

        auth('retina')->login($webUser);

        return $webUser;
    }


    public function jsonResponse(array|WebUser $result, ActionRequest $request): JsonResponse
    {
        if (is_array($result)) {
            return response()->json([
                'logged_in'           => false,
                'google_user'         => $result,
                'google_access_token' => $request->input('google_access_token'),
            ]);
        }

        return $this->postProcessRetinaLoginGoogle($result, $request);
    }

    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): array|WebUser
    {
        $this->initialisation($request);

        return $this->handle($this->shop, $request);
    }

    public function postProcessRetinaLoginGoogle(WebUser $webUser, $request): JsonResponse
    {
        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];


        LogWebUserLogin::dispatch(
            webUser: $webUser,
            ip: request()->ip(),
            userAgent: $request->header('User-Agent'),
            datetime: now(),
            geoLocation: $geoLocation,
            source: 'G'
        )->delay(now()->addSeconds(5));


        $request->session()->regenerate();
        Session::put('reloadLayout', '1');


        $language = $webUser->language;
        if ($language) {
            app()->setLocale($language->code);
        }

        return response()->json([
            'logged_in' => true,
        ]);
    }


    /**
     * @throws \App\Exceptions\GoogleCredentialVerificationException
     */
    private function verifyGoogleCredential(string $googleAccessToken): array
    {
        try {
            $client = new Google_Client();
            $client->setClientId(config('services.google.client_id'));

            $client->setAccessToken(['access_token' => $googleAccessToken]);

            $oauth2     = new Oauth2($client);
            $googleUser = $oauth2->userinfo->get();

            return [
                'id'    => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
                'name'  => $googleUser->getName(),
            ];
        } catch (\Exception $e) {
            throw new GoogleCredentialVerificationException($e->getMessage());
        }
    }


    public function rules(): array
    {
        return [
            'google_access_token' => ['required', 'string', 'max:2048'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $googleCredential = $request->input('google_access_token');
        if ($googleCredential) {
            try {
                $googleUser                        = $this->verifyGoogleCredential($googleCredential);
                $googleUser['google_access_token'] = $googleCredential;
                $this->set('google_user', $googleUser);
            } catch (\Exception $e) {
                $validator->errors()->add('google_access_token', $e->getMessage());
            }
        }
    }

}
