<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\User;

use App\Actions\Dropshipping\Allegro\Traits\WithAllegroOAuth;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\AllegroUser;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Platform;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AuthenticateAllegroAccount extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAllegroOAuth;

    public function handle(array $modelData): AllegroUser|array|string|null
    {
        try {
            $platform = Platform::where('type', PlatformTypeEnum::ALLEGRO->value)->first();

            $customer = null;
            if (Arr::get($modelData, 'state')) {
                $stateData = json_decode(base64_decode(Arr::get($modelData, 'state')), true);
                $customer = Customer::find(Arr::get($stateData, 'customer_id'));
                $codeVerifier = Cache::pull('allegro_code_verifier_' . Arr::get($stateData, 'session_id'));
            }

            // Exchange authorization code for tokens
            $tokenData = $this->exchangeCodeForTokens(
                Arr::get($modelData, 'code'),
                route('webhooks.allegro.callback'),
                $codeVerifier ?? null
            );

            if (isset($tokenData['access_token'])) {
                // Calculate expiration timestamps
                $accessTokenExpiresAt = now()->addSeconds($tokenData['expires_in'])->timestamp;
                $refreshTokenExpiresAt = isset($tokenData['refresh_token'])
                    ? now()->addDays(90)->timestamp // Allegro refresh tokens typically last 90 days
                    : null;

                $userData = [
                    'allegro_id' => Arr::get($tokenData, 'allegro_user_id', Str::uuid()->toString()),
                    'name' => $customer?->name ?? 'Allegro User',
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
                    $allegroUser = StoreAllegroUser::make()->action($customer, $userData);
                } elseif (!$allegroUser && $customer === null) {
                    $allegroUser = AllegroUser::create($userData);
                }

                if ($customer?->id && $allegroUser) {
                    $allegroUser = UpdateAllegroUser::make()->action($allegroUser, $userData);
                }

                if ($allegroUser) {
                    SaveShopDataAllegroChannel::run($allegroUser);
                    $allegroUser->refresh();

                    if ($customer?->id) {
                        CheckAllegroChannel::run($allegroUser);
                    }
                }

                $model = $allegroUser;
            } else {
                $model = __('Failed to obtain access token from Allegro');
            }

            return redirect()->route('aiku-public.allegro.onboarding', [
                'allegro_code' => base64_encode(json_encode([
                    'allegro_user_id' => $model instanceof AllegroUser ? $model->id : null,
                    'message' => $model instanceof AllegroUser ? null : $model
                ]))
            ]);

        } catch (\Exception $e) {
            Log::error('Allegro authentication failed: ' . $e->getMessage());

            return redirect()->route('aiku-public.allegro.onboarding', [
                'allegro_code' => base64_encode(json_encode([
                    'allegro_user_id' => null,
                    'message' => $e->getMessage()
                ]))
            ]);
        }
    }

    public function redirectToAllegro(Customer $customer): string
    {
        $sessionId = Str::uuid()->toString();
        $codeVerifier = $this->generateCodeVerifier();
        $codeChallenge = $this->generateCodeChallenge($codeVerifier);

        // Store code verifier for later use (expires in 10 minutes)
        Cache::put('allegro_code_verifier_' . $sessionId, $codeVerifier, 600);

        $state = base64_encode(json_encode([
            'customer_id' => $customer->id,
            'session_id' => $sessionId
        ]));

        $redirectUri = route('webhooks.allegro.callback');
        $scope = 'allegro:api:sale:offers:read allegro:api:sale:offers:write allegro:api:orders:read allegro:api:orders:write';

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

    public function asController(ActionRequest $request): AllegroUser|array|string|null
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($validatedData);
    }
}
