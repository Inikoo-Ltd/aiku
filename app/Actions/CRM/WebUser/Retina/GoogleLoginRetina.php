<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 03-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\CRM\Customer\PreRegisterCustomer;
use App\Actions\CRM\WebUser\LogWebUserLogin;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
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

class GoogleLoginRetina extends RetinaAction
{
    /**
     * @throws \Throwable
     */
    use AsAction;
    use WithAttributes;

    protected Shop $shop;

    public function handle(Shop $shop, array $modelData)
    {
        if ($shop->type == ShopTypeEnum::FULFILMENT) {
            abort(404);
        }

        $googleUser = $this->google_user ?? [];

        $webUser = WebUser::where('shop_id', $shop->id)
            ->where('email', Arr::get($googleUser, 'email'))
            ->first();

        if (!$webUser) {
            PreRegisterCustomer::run($shop, [
                'google_user' => $googleUser,
            ]);
            return;
        }

        $webUser->update([
            'google_id' => Arr::get($googleUser, 'id'),
            'email' => Arr::get($googleUser, 'email'),
            'contact_name' => Arr::get($googleUser, 'name'),
        ]);
        $webUser->refresh();

        auth('retina')->login($webUser);
    }

    public function postProcessRetinaLoginGoogle($request): RedirectResponse
    {
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
            dd($payload);
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
        $googleCredential = $request->input('google_credential', null);
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
    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->registerDropshippingInitialisation($shop, $request);
        $this->handle($shop, $this->validatedData);
        return $this->postProcessRetinaLoginGoogle($request);
    }
}
