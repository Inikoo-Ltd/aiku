<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\Dropshipping\CustomerSalesChannel\StoreCustomerSalesChannel;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthenticateTiktokAccount extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Customer $customer, array $modelData)
    {
        try {
            $response = Http::get(config('services.tiktok.auth_url')."/api/v2/token/get", [
                'app_key' => config('services.tiktok.client_id'),
                'app_secret' => config('services.tiktok.client_secret'),
                'auth_code' => Arr::get($modelData, 'code'),
                'grant_type' => 'authorized_code'
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['data']['access_token'])) {
                $userData = $data['data'];

                if (isset($userData)) {
                    $userData = [
                        'tiktok_id' => $userData['open_id'],
                        'name' => $userData['seller_name'],
                        'username' => $userData['seller_name'],
                        'access_token' => $userData['access_token'],
                        'access_token_expire_in' => $userData['access_token_expire_in'],
                        'refresh_token' => $userData['refresh_token'],
                        'refresh_token_expire_in' => $userData['refresh_token_expire_in'],
                    ];

                    $tiktokUser = $customer->tiktokUser?->where('tiktok_id', $userData['tiktok_id'])->withTrashed()->first();

                    if ($tiktokUser) {
                        if ($tiktokUser->deleted_at) {
                            $tiktokUser->restore();
                            $platform = Platform::where('type', PlatformTypeEnum::TIKTOK->value)->first();
                            StoreCustomerSalesChannel::make()->action($customer, $platform, []);
                        }
                    } else {
                        $tiktokUser = StoreTiktokUser::make()->action($customer, $userData);
                    }

                    $tiktokUser = UpdateTiktokUser::make()->action($tiktokUser, $userData);

                    $tiktokShop = $tiktokUser->getAuthorizedShop();

                    data_set($userData, 'data.authorized_shop', Arr::get($tiktokShop, 'data.shops.0'));

                    return UpdateTiktokUser::make()->action($tiktokUser, $userData);
                }
            }

            throw ValidationException::withMessages(['message' => __('tiktok.access_token')]);

        } catch (\Exception $e) {
            dd($e);
        }
    }

    public function redirectToTikTok(Customer $customer)
    {
        $clientId = config('services.tiktok.client_id');
        $redirectUri = urlencode($customer->shop?->website?->getFullUrl() . config('services.tiktok.redirect_uri'));
        $state = uniqid();

        return config('services.tiktok.auth_url')."/oauth/authorize?app_key={$clientId}&state={$state}&redirect_uri={$redirectUri}";
    }

    public function checkIsAuthenticated(Customer $customer): bool
    {
        if (!$customer->tiktokUser) {
            return false;
        }

        return (bool) $customer->tiktokUser;
    }

    public function checkIsAuthenticatedExpired(Customer $customer): bool
    {
        if (!$customer->tiktokUser) {
            return false;
        }

        return $customer->tiktokUser && now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($customer->tiktokUser?->access_token_expire_in));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string']
        ];
    }

    public function asController(ActionRequest $request): void
    {
        $customer = $request->user()->customer;
        $this->initialisation($request);

        $this->handle($customer, $this->validatedData);
    }
}
