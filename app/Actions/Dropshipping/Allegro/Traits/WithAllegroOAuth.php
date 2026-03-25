<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Mar 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Allegro\Traits;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

trait WithAllegroOAuth
{
    // OAuth endpoints live on allegro.pl, NOT api.allegro.pl
    public string $allegroAuthUrl;
    protected string $allegroTokenUrl;
    protected string $allegroDeviceUrl;

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    public function __construct()
    {
        $this->allegroAuthUrl   = config('services.allegro.auth_url') . '/auth/oauth/authorize';
        $this->allegroTokenUrl  = config('services.allegro.auth_url') . '/auth/oauth/token';
        $this->allegroDeviceUrl = config('services.allegro.base_url') . '/auth/oauth/device';
    }

    protected function basicAuthHeader(): string
    {
        $clientId     = config('services.allegro.client_id');
        $clientSecret = config('services.allegro.client_secret');

        return 'Basic ' . base64_encode("$clientId:$clientSecret");
    }

    protected function postToTokenEndpoint(array $formParams): array
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => $this->basicAuthHeader(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->post($this->allegroTokenUrl, $formParams);

            if ($response->failed()) {
                $error = Arr::get($response->json(), 'error_description')
                    ?? Arr::get($response->json(), 'error')
                    ?? 'OAuth token request failed';

                throw new \Exception($error);
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Allegro OAuth error: ' . $e->getMessage());
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }

    // -------------------------------------------------------------------------
    // Authorization Code Flow
    // -------------------------------------------------------------------------

    /**
     * Step 1 — Build the URL to redirect the user to Allegro login & consent.
     *
     * @param  string       $redirectUri   Must match one registered in developer portal
     * @param  string|null  $codeChallenge Base64URL(SHA256(codeVerifier)) for PKCE
     * @param  string|null  $scope         Space-separated scopes, e.g. "allegro:api:orders:read"
     * @param  string|null  $state         Optional CSRF state token
     */
    public function getAuthorizationUrl(
        string $redirectUri,
        ?string $codeChallenge = null,
        ?string $scope = null,
        ?string $state = null
    ): string {
        $params = [
            'response_type' => 'code',
            'client_id'     => config('services.allegro.client_id'),
            'redirect_uri'  => $redirectUri,
        ];

        if ($codeChallenge) {
            $params['code_challenge_method'] = 'S256';
            $params['code_challenge']        = $codeChallenge;
        }

        if ($scope) {
            $params['scope'] = $scope;
        }

        if ($state) {
            $params['state'] = $state;
        }

        return $this->allegroAuthUrl . '?' . http_build_query($params);
    }

    /**
     * Step 2 — Exchange the authorization code for access + refresh tokens.
     *
     * @param  string       $code          Code returned to redirect_uri
     * @param  string       $redirectUri   Same URI used in step 1
     * @param  string|null  $codeVerifier  Plain-text PKCE verifier (if PKCE was used)
     * @return array{access_token: string, refresh_token: string, expires_in: int, token_type: string, scope: string}
     */
    public function exchangeCodeForTokens(
        string $code,
        string $redirectUri,
        ?string $codeVerifier = null
    ): array {
        $params = [
            'grant_type'   => 'authorization_code',
            'code'         => $code,
            'redirect_uri' => $redirectUri,
        ];

        // When using PKCE, send client_id + code_verifier instead of Basic auth header
        if ($codeVerifier) {
            $params['client_id']      = config('services.allegro.client_id');
            $params['code_verifier']  = $codeVerifier;

            $response = Http::withHeaders([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])->asForm()->post($this->allegroTokenUrl, $params);

            if ($response->failed()) {
                $error = Arr::get($response->json(), 'error_description')
                    ?? Arr::get($response->json(), 'error')
                    ?? 'OAuth code exchange failed';
                throw ValidationException::withMessages(['message' => $error]);
            }

            return $response->json();
        }

        return $this->postToTokenEndpoint($params);
    }

    // -------------------------------------------------------------------------
    // PKCE Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically random PKCE code verifier (43–128 chars).
     */
    public function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(80)), '+/', '-_'), '=');
    }

    /**
     * Derive the S256 code challenge from a code verifier.
     */
    public function generateCodeChallenge(string $codeVerifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }

    // -------------------------------------------------------------------------
    // Device Flow
    // -------------------------------------------------------------------------

    /**
     * Step 1 — Request a device code + user code.
     * Show the user $result['verification_uri_complete'] or display $result['user_code'].
     *
     * @return array{user_code: string, device_code: string, expires_in: int, interval: int, verification_uri: string, verification_uri_complete: string}
     */
    public function requestDeviceCode(?string $scope = null): array
    {
        try {
            $params = ['client_id' => config('services.allegro.client_id')];

            if ($scope) {
                $params['scope'] = $scope;
            }

            $response = Http::withHeaders([
                'Authorization' => $this->basicAuthHeader(),
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ])->asForm()->post($this->allegroDeviceUrl, $params);

            if ($response->failed()) {
                throw new \Exception(
                    Arr::get($response->json(), 'error_description') ?? 'Device code request failed'
                );
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Allegro Device Flow error: ' . $e->getMessage());
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }

    /**
     * Step 2 — Poll until the user approves or the code expires.
     * Returns the token response when approved, or throws on denial/expiry.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function pollDeviceToken(string $deviceCode, int $intervalSeconds = 5): array
    {
        $attempts  = 0;
        $maxWait   = 600; // 10 minutes safety ceiling
        $interval  = $intervalSeconds;

        while ($attempts * $interval < $maxWait) {
            sleep($interval);
            $attempts++;

            try {
                $response = Http::withHeaders([
                    'Authorization' => $this->basicAuthHeader(),
                    'Content-Type'  => 'application/x-www-form-urlencoded',
                ])->asForm()->post($this->allegroTokenUrl, [
                    'grant_type'  => 'urn:ietf:params:oauth:grant-type:device_code',
                    'device_code' => $deviceCode,
                ]);

                $body = $response->json();

                if ($response->ok()) {
                    // User approved — we have tokens
                    return $body;
                }

                $error = Arr::get($body, 'error', '');

                if ($error === 'authorization_pending') {
                    // Still waiting — keep polling
                    continue;
                }

                if ($error === 'slow_down') {
                    // Back off by 1 second as required by spec
                    $interval++;
                    continue;
                }

                // access_denied, expired, invalid device code — abort
                throw new \Exception(
                    Arr::get($body, 'error_description') ?? $error ?? 'Device authorization failed'
                );
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Allegro Device Flow polling error: ' . $e->getMessage());
                throw ValidationException::withMessages(['message' => $e->getMessage()]);
            }
        }

        throw ValidationException::withMessages(['message' => 'Device flow timed out waiting for user approval.']);
    }

    // -------------------------------------------------------------------------
    // Client Credentials Flow (public/app-level data only)
    // -------------------------------------------------------------------------

    /**
     * Obtain an application-level token (no user context).
     * Only works for endpoints marked "bearer-token-for-application" in the docs.
     *
     * @return array{access_token: string, expires_in: int, token_type: string, scope: string}
     */
    public function getClientCredentialsToken(): array
    {
        return $this->postToTokenEndpoint([
            'grant_type' => 'client_credentials',
        ]);
    }

    // -------------------------------------------------------------------------
    // Token Refresh
    // -------------------------------------------------------------------------

    /**
     * Refresh an expiring access token using the stored refresh token.
     * Returns a new {access_token, refresh_token, expires_in, ...} pair.
     * Both the old access_token AND the old refresh_token are invalidated.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        return $this->postToTokenEndpoint([
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);
    }

    /**
     * Convenience: refresh the token stored on $this->refresh_token and
     * update $this->access_token / $this->refresh_token in place, then
     * persist the new tokens by calling persistAllegroTokens() if defined.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function refreshAndPersistTokens(): array
    {
        $tokens = $this->refreshAccessToken($this->refresh_token);

        $this->access_token  = $tokens['access_token'];
        $this->refresh_token = $tokens['refresh_token'];

        if (method_exists($this, 'persistAllegroTokens')) {
            $this->persistAllegroTokens($tokens);
        }

        return $tokens;
    }

    // -------------------------------------------------------------------------
    // Dynamic Client Registration (DCR)
    // -------------------------------------------------------------------------

    /**
     * Register a new client instance for a customer who has installed your software.
     * Requires a one-time user code generated at https://allegro.pl/uzytkownik/bezpieczenstwo/wygeneruj-kod
     *
     * @return array{client_id: string, client_secret: string, client_name: string, redirect_uris: array}
     */
    public function registerDynamicClient(
        string $userCode,
        string $clientName,
        array $redirectUris,
        string $softwareStatementId
    ): array {
        try {
            $response = Http::withHeaders([
                'Accept'       => 'application/json',
                'Content-Type' => 'application/json',
            ])->post(config('services.allegro.base_url') . '/register', [
                'code'                  => $userCode,
                'client_name'           => $clientName,
                'redirect_uris'         => $redirectUris,
                'software_statement_id' => $softwareStatementId,
            ]);

            if ($response->failed()) {
                throw new \Exception(
                    Arr::get($response->json(), 'error_description')
                    ?? Arr::get($response->json(), 'message')
                    ?? 'Dynamic Client Registration failed'
                );
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Allegro DCR error: ' . $e->getMessage());
            throw ValidationException::withMessages(['message' => $e->getMessage()]);
        }
    }
}
