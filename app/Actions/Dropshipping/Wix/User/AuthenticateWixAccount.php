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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class AuthenticateWixAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithWixApiServices;

    /**
     * @throws ValidationException
     */
    public function handle(array $modelData, ?Customer $customer = null): RedirectResponse|string|null
    {
        try {
            return DB::transaction(function () use ($modelData, $customer) {
                $instancePayload = $this->verifySignedInstance(
                    Arr::get($modelData, 'instance') ?? Arr::get($modelData, 'signedInstance')
                );

                if (!$instancePayload) {
                    throw ValidationException::withMessages([
                        'message' => __('Wix sent an instance we could not verify')
                    ]);
                }

                $instanceId = Arr::get($instancePayload, 'instanceId');

                if (!$instanceId) {
                    throw ValidationException::withMessages([
                        'message' => __('Wix did not identify the installed site')
                    ]);
                }

                $wixUser = WixUser::where('wix_instance_id', $instanceId)->first();

                $customer ??= $wixUser?->customer;

                $tokenData   = $this->createAccessToken($instanceId);
                $accessToken = Arr::get($tokenData, 'access_token');

                if (!$accessToken) {
                    throw ValidationException::withMessages([
                        'message' => Arr::get($tokenData, 'message', __('Wix did not issue an access token'))
                    ]);
                }

                $appInstance = $this->getInstanceFromApi($accessToken);

                $userData = [
                    'wix_site_id'            => Arr::get($appInstance, 'site.siteId'),
                    'name'                   => Arr::get($appInstance, 'site.siteDisplayName') ?: $instanceId,
                    'email'                  => Arr::get($appInstance, 'site.ownerInfo.email'),
                    'site_url'               => Arr::get($appInstance, 'site.url'),
                    'access_token'           => $accessToken,
                    'access_token_expire_in' => $this->wixAccessTokenExpiry($tokenData),
                ];

                if (!$wixUser && !$customer) {
                   WixUser::create(array_merge($userData, ['wix_instance_id' => $instanceId]));

                    return null;
                }

                if (!$wixUser) {
                    $wixUser = StoreWixUser::make()->action(
                        $customer,
                        array_merge($userData, ['wix_instance_id' => $instanceId])
                    );
                } else {
                    $wixUser = UpdateWixUser::make()->handle($wixUser, $userData, false);

                    if ($customer && !$wixUser->customer_id) {
                        $wixUser = AttachWixUserToCustomer::run($wixUser, $customer);
                    }
                }

                SaveShopDataWixChannel::run($wixUser);
                $wixUser->refresh();

                CheckWixChannel::run($wixUser);

                $customerSalesChannel = $wixUser->customerSalesChannel;

                if (!$customerSalesChannel) {
                    return null;
                }

                $domain = "https://{$customerSalesChannel->shop->website->domain}";

                return Redirect::away($domain."/app/dropshipping/channels/$customerSalesChannel->slug");
            });
        } catch (\Exception $e) {
            \Sentry::captureException($e);

            return $e->getMessage();
        }
    }

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

    public function redirectToWix(Customer $customer): string
    {
        $callback = URL::temporarySignedRoute('wix.link', now()->addHours(2), [
            'customer' => $customer->id,
        ]);

        return $this->getInstallUrl($callback);
    }

    public const array WIX_APPENDED_QUERY = ['appId', 'tenantId', 'instanceId', 'signedInstance'];

    public function checkIsAuthenticated(WixUser $wixUser): bool
    {
        return $wixUser->customerSalesChannel?->platform_status ?? false;
    }

    public function rules(): array
    {
        return [
            'signedInstance' => ['required_without:instance', 'string'],
            'instance'       => ['required_without:signedInstance', 'string'],
        ];
    }

    public function asController(Customer $customer, Request $request): RedirectResponse|string|null
    {
        if (!$request->hasValidSignatureWhileIgnoring(self::WIX_APPENDED_QUERY)) {
            throw new AccessDeniedHttpException('Invalid Wix install callback');
        }

        if (!$request->query('instanceId') || !$request->query('signedInstance')) {
            return __('The Wix installation was not completed');
        }

        $this->fillFromRequest($request);

        return $this->handle($this->validateAttributes(), $customer);
    }
}
