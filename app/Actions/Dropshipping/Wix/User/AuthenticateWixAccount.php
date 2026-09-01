<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\User;

use App\Actions\Dropshipping\Wix\Traits\WithWixApiServices;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\WixUser;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthenticateWixAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWixApiServices;

    /**
     * @throws ValidationException
     */
    public function handle(array $modelData): Response|string|RedirectResponse|null
    {
        try {
            return DB::transaction(function () use ($modelData) {
                $customer = null;

                if (Arr::get($modelData, 'state')) {
                    $stateData = json_decode(base64_decode(Arr::get($modelData, 'state')), true);
                    $customer  = Customer::find(Arr::get($stateData, 'customer_id'));
                }

                $tokenData = $this->exchangeCodeForTokens(Arr::get($modelData, 'code'));

                if (!Arr::get($tokenData, 'access_token')) {
                    throw ValidationException::withMessages([
                        'message' => Arr::get($tokenData, 'message', __('Wix did not return an access token'))
                    ]);
                }

                $instance = $this->getInstanceFromApi($tokenData['access_token']);

                $instanceId = Arr::get($instance, 'instance.instanceId') ?: Arr::get($modelData, 'instanceId');

                if (!$instanceId) {
                    throw ValidationException::withMessages([
                        'message' => __('Wix did not identify the installed site')
                    ]);
                }

                $userData = [
                    'wix_instance_id'        => $instanceId,
                    'wix_site_id'            => Arr::get($instance, 'site.siteId'),
                    'name'                   => Arr::get($instance, 'site.siteDisplayName') ?: $instanceId,
                    'email'                  => Arr::get($instance, 'site.ownerEmail'),
                    'site_url'               => Arr::get($instance, 'site.url'),
                    'access_token'           => $tokenData['access_token'],
                    'access_token_expire_in' => $this->wixAccessTokenExpiry($tokenData),
                    'refresh_token'          => Arr::get($tokenData, 'refresh_token'),
                ];

                $wixUser = WixUser::where('wix_instance_id', $instanceId)->first();

                if (!$wixUser && $customer?->id) {
                    $wixUser = StoreWixUser::make()->action($customer, $userData);
                } elseif ($wixUser) {
                    $wixUser = UpdateWixUser::make()->handle($wixUser, $userData, false);
                }

                if (!$wixUser) {
                    return null;
                }

                SaveShopDataWixChannel::run($wixUser);
                $wixUser->refresh();

                CheckWixChannel::run($wixUser);

                $customerSalesChannel = $wixUser->customerSalesChannel;

                if (!$customerSalesChannel) {
                    return null;
                }

                $domain = "https://{$customerSalesChannel->shop->website->domain}";
                $path   = "/app/dropshipping/channels/$customerSalesChannel->slug";

                return Redirect::away($domain.$path);
            });
        } catch (\Exception $e) {
            \Sentry::captureException($e);

            return $e->getMessage();
        }
    }

    /**
     * The instance endpoint is called with the freshly minted token, before any WixUser exists
     * to carry it, so it cannot go through the model's api trait.
     */
    private function getInstanceFromApi(string $accessToken): array
    {
        $response = Http::withHeaders([
            'Authorization' => $accessToken,
            'Accept'        => 'application/json',
        ])->get(config('services.wix.api_url').'/apps/v1/instance');

        if ($response->failed()) {
            return [];
        }

        return $response->json() ?? [];
    }

    public function getRedirectUrl(): string
    {
        return route('wix.callback');
    }

    public function redirectToWix(Customer $customer): string
    {
        $state = base64_encode(json_encode([
            'customer_id' => $customer->id
        ]));

        return $this->getAuthorizationUrl($this->getRedirectUrl(), $state);
    }

    public function checkIsAuthenticated(WixUser $wixUser): bool
    {
        return $wixUser->customerSalesChannel?->platform_status ?? false;
    }

    public function checkIsAuthenticatedExpired(WixUser $wixUser): bool
    {
        if (!$wixUser->access_token_expire_in) {
            return true;
        }

        return now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($wixUser->access_token_expire_in));
    }

    public function rules(): array
    {
        return [
            'code'       => ['required', 'string'],
            'state'      => ['nullable', 'string'],
            'instanceId' => ['nullable', 'string'],
        ];
    }

    public function asController(ActionRequest $request): Response|string|RedirectResponse|null
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }
}
