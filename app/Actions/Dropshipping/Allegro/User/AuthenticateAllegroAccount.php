<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroApiServices;
use App\Actions\Dropshipping\Allegro\Traits\WithAllegroOAuth;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AllegroUser;
use App\Models\Dropshipping\Platform;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthenticateAllegroAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAllegroApiServices;

    /**
     * @throws ValidationException
     */
    public function handle(array $modelData)
    {
        try {
            $customer = null;
            if (Arr::get($modelData, 'state')) {
                $stateData = json_decode(base64_decode(Arr::get($modelData, 'state')), true);
                $customer = Customer::find(Arr::get($stateData, 'customer_id'));
                $codeVerifier = Arr::get($stateData, 'code_verifier');
            }

            $tokenData = $this->exchangeCodeForTokens(
                Arr::get($modelData, 'code'),
                route('retina.dropshipping.allegro.callback'),
                $codeVerifier ?? null
            );

            if (isset($tokenData['access_token'])) {
                $accessTokenExpiresAt = now()->addSeconds($tokenData['expires_in'])->timestamp;
                $refreshTokenExpiresAt = isset($tokenData['refresh_token'])
                    ? now()->addDays(90)->timestamp
                    : null;

                $http = Http::withHeaders([
                    'Authorization'  => 'Bearer ' . $tokenData['access_token'],
                    'Accept'         => $this->allegroApiVersion,
                    'Content-Type'   => $this->allegroApiVersion,
                ])->baseUrl(config('services.allegro.base_url'))
                ->get('/me');

                $response = $http->json();

                $userData = [
                    'allegro_id' => Arr::get($response, 'id'),
                    'name' => (Arr::get($response, 'firstName') && Arr::get($response, 'lastName'))
                        ? Arr::get($response, 'firstName') . ' ' . Arr::get($response, 'lastName')
                        : Arr::get($response, 'company.name'),
                    'access_token' => $tokenData['access_token'],
                    'access_token_expire_in' => $accessTokenExpiresAt,
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'refresh_token_expire_in' => $refreshTokenExpiresAt,
                    'auth_type' => 'oauth',
                ];

                $allegroUser = AllegroUser::where('customer_id', $customer?->id)
                    ->where('allegro_id', $userData['allegro_id'])
                    ->first();

                if (!$allegroUser && $customer?->id) {
                    $allegroUser = StoreAllegroUser::run($customer, $userData);
                } elseif (!$allegroUser && $customer === null) {
                    $allegroUser = AllegroUser::create($userData);
                }

                if ($customer?->id && $allegroUser) {
                    $allegroUser = UpdateAllegroUser::run($allegroUser, $userData);
                }

                if ($allegroUser) {
                    SaveShopDataAllegroChannel::run($allegroUser);
                    $allegroUser->refresh();

                    if ($customer?->id) {
                        CheckAllegroChannel::run($allegroUser);
                    }
                }

                return Redirect::route('retina.dropshipping.customer_sales_channels.show', [
                    'customerSalesChannel' => $allegroUser->customerSalesChannel->slug
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Allegro authentication failed: ' . $e->getMessage());

            \Sentry::captureException($e);

            return $e->getMessage();
        }

        return null;
    }

    public function redirectToAllegro(Customer $customer): string
    {
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        $state = base64_encode(json_encode([
            'customer_id' => $customer->id,
            'code_verifier' => $codeVerifier
        ]));

        $redirectUri = route('retina.dropshipping.allegro.callback');
        $scope = 'allegro:api:sale:offers:read allegro:api:sale:offers:write allegro:api:sale:settings:read allegro:api:sale:settings:write allegro:api:orders:read allegro:api:orders:write allegro:api:ratings allegro:api:disputes allegro:api:bids allegro:api:ads allegro:api:campaigns allegro:api:profile:read allegro:api:profile:write allegro:api:fulfillment:read allegro:api:fulfillment:write allegro:api:shipments:read allegro:api:shipments:write';

        return $this->getAuthorizationUrl($redirectUri, $codeChallenge, $scope, $state);
    }

    public function checkIsAuthenticated(AllegroUser $allegroUser): bool
    {
        return $allegroUser->customerSalesChannel?->platform_status ?? false;
    }

    public function checkIsAuthenticatedExpired(AllegroUser $allegroUser): bool
    {
        if (!$allegroUser->access_token_expire_in) {
            return true;
        }

        return now()->greaterThanOrEqualTo(Carbon::createFromTimestamp($allegroUser->access_token_expire_in));
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
            'state' => ['nullable', 'string']
        ];
    }

    public function asController(ActionRequest $request): Response|string|RedirectResponse|null
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }
}
