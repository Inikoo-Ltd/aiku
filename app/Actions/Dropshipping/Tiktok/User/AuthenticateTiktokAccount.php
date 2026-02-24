<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User;

use App\Actions\OrgAction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\TiktokUser;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use function Pest\Laravel\instance;

class AuthenticateTiktokAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(array $modelData): TiktokUser|array|string|null
    {
        try {
            $response = Http::get(config('services.tiktok.auth_url')."/api/v2/token/get", [
                'app_key' => config('services.tiktok.client_id'),
                'app_secret' => config('services.tiktok.client_secret'),
                'auth_code' => Arr::get($modelData, 'code'),
                'grant_type' => 'authorized_code'
            ]);

            $data = json_decode($response->getBody(), true);

            $customer = null;
            if(Arr::get($modelData, 'state')) {
                $customer = Customer::find(base64_decode(Arr::get($modelData, 'state')));
            }

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

                    $tiktokUser = TiktokUser::where('customer_id', $customer->id)
                        ->where('tiktok_id', $userData['tiktok_id'])
                        ->first();

                    if (!$tiktokUser) {
                        $tiktokUser = StoreTiktokUser::make()->action($customer, $userData);
                    }

                    $tiktokUser = UpdateTiktokUser::make()->action($tiktokUser, $userData);

                    SaveShopDataTiktokChannel::run($tiktokUser);
                    $tiktokUser->refresh();
                    CheckTiktokChannel::run($tiktokUser);

                    $model = $tiktokUser;
                } else {
                    $model = __('Something went wrong.');
                }

            } else {
                $model = __('tiktok.access_token');
            }

            return redirect()->route('aiku-public.tiktok.onboarding', [
                'code' => base64_encode(json_encode([
                    'tiktok_user_id' => $model instanceof TiktokUser ? $model->id : null,
                    'message' => $model instanceof TiktokUser ? null : $model
                ]))
            ]);

        } catch (\Exception $e) {
            Log::error('API Request failed: ' . $e->getMessage());

            return $e->getMessage();
        }
    }

    public function redirectToTikTok(Customer $customer): string
    {
        $clientId = config('services.tiktok.client_id');
        $redirectUri = route('webhooks.tiktok.callback');
        $state = base64_encode($customer->id);

        return config('services.tiktok.auth_url')."/oauth/authorize?app_key={$clientId}&state={$state}&redirect_uri={$redirectUri}";
    }

    public function checkIsAuthenticated(TiktokUser $tiktokUser): bool
    {
        return $tiktokUser->customerSalesChannel->platform_status;
    }

    public function checkIsAuthenticatedExpired(TiktokUser $tiktokUser): bool
    {
        return now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($tiktokUser->access_token_expire_in));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string']
        ];
    }

    public function asController(ActionRequest $request): TiktokUser|array|string|null
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }
}
