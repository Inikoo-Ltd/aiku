<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\CRM\WebUser\LogWebUserLogin;
use App\Actions\IrisAction;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use Google_Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\JsonResponse;
use App\Exceptions\GoogleCredentialVerificationException;

class GoogleLoginRetina extends IrisAction
{
    public function handle(Shop $shop): array|WebUser
    {


        $googleUser = $this->google_user ?? [];

        $webUser = WebUser::where('shop_id', $shop->id)
            ->where('email', Arr::get($googleUser, 'email'))
            ->first();

        if (!$webUser) {
            return $googleUser;
        }

        $webUser->update([
            'google_id' => Arr::get($googleUser, 'id'),
        ]);

        auth('retina')->login($webUser);
        return $webUser;
    }






    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request)
    {
        $this->initialisation($request);
        $result = $this->handle($this->shop);
        if (is_array($result)) {
            return $result;
        }

        return $this->postProcessRetinaLoginGoogle($request);
    }

    public function postProcessRetinaLoginGoogle($request)
    {
        /** @var WebUser $webUser */
        $webUser = auth('retina')->user();

        LogWebUserLogin::dispatch(
            webUser: $webUser,
            ip: request()->ip(),
            userAgent: $request->header('User-Agent'),
            datetime: now()
        );


        $request->session()->regenerate();
        Session::put('reloadLayout', '1');


        $language = $webUser->language;
        if ($language) {
            app()->setLocale($language->code);
        }

        if ($webUser) {
            return response()->json([
                'is_registered' => true,
            ]);
        } else {
            return response()->json([
                'google_user' => $this->google_user,
                'google_credential' => $request->input('google_credential'),
                'is_registered' => false,
            ]);
        }

    }


    /**
     * @throws \App\Exceptions\GoogleCredentialVerificationException
     */
    private function verifyGoogleCredential(string $credential): array
    {
        $client = new Google_Client(['client_id' => config('services.google.client_id')]);
        $payload = $client->verifyIdToken($credential);
        if ($payload) {
            return [
                'id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'],
            ];
        }
        throw new GoogleCredentialVerificationException();
    }


    public function rules(): array
    {
        return [
            'google_credential'     => ['required', 'string', 'max:2048'],
        ];
    }

    public function afterValidator(Validator $validator, ActionRequest $request): void
    {
        $googleCredential = $request->input('google_credential');
        if ($googleCredential) {
            try {
                $googleUser = $this->verifyGoogleCredential($googleCredential);
                $this->set('google_user', $googleUser);
            } catch (\Exception $e) {
                $validator->errors()->add('google_credential', $e->getMessage());
            }
        }
    }

}
