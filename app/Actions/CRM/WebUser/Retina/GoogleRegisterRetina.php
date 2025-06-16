<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 16:15:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
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
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class GoogleRegisterRetina extends IrisAction
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

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

    public function postProcessRetinaLoginGoogle($request): RedirectResponse
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

        return redirect()->intended('/app/dashboard');
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

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
        throw new \Exception('Invalid Google credential provided!');
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


    /**
     * @throws \Throwable
     */
    public function asController(ActionRequest $request): RedirectResponse|array
    {
        $this->initialisation($request);
        $result = $this->handle($this->shop);
        if (is_array($result)) {
            return $result;
        }

        return $this->postProcessRetinaLoginGoogle($request);
    }
}
