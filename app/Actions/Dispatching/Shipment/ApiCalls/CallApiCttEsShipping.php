<?php

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Actions\Dispatching\Shipment\GetShippingDeliveryNoteData;
use App\Actions\OrgAction;
use App\Enums\Dispatching\Shipment\ShipmentLabelTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\Shipper;
use App\Models\Fulfilment\PalletReturn;
use App\Http\Resources\Dispatching\ShippingPalletReturnResource;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Sentry;
use Throwable;

class CallApiCttEsShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private const string TOKEN_CACHE_KEY_PREFIX = 'ctt_access_token:';
    private const int TOKEN_FALLBACK_TTL_SECONDS = 3600;
    private const int TOKEN_TTL_BUFFER_SECONDS = 120;
    private const string MANIFEST_LOCK_KEY_PREFIX = 'ctt_manifest_lock:';
    private const int MANIFEST_LOCK_TTL_SECONDS = 180;

    public function getBaseUrl(): string
    {
        if (app()->environment('production')) {
            return 'https://api.cttexpress.com/integrations';
        }

        return 'https://api-test.cttexpress.com/integrations';
    }

    public function getAccessToken(Shipper $shipper, bool $forceRefresh = false): string
    {
        $tokenCacheKey = $this->getTokenCacheKey($shipper);

        if (!$forceRefresh) {
            $cachedToken = Cache::get($tokenCacheKey);

            if (is_string($cachedToken) && $cachedToken !== '') {
                return $cachedToken;
            }
        }

        [$accessToken, $ttlSeconds] = $this->fetchAccessToken($shipper);

        Cache::put(
            $tokenCacheKey,
            $accessToken,
            now()->addSeconds($ttlSeconds)
        );

        return $accessToken;
    }

    private function getTokenCacheKey(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            $cttClientId = Arr::get($shipper->settings, 'client_id', '');
        } else {
            $cttClientId = config('app.sandbox.shipper_ctt_token.client_id', '');
        }

        $signature = hash('sha256', $this->getBaseUrl().'|'.$cttClientId);

        return self::TOKEN_CACHE_KEY_PREFIX.$signature;
    }

    private function getManifestLockKey(DeliveryNote $parent, Shipper $shipper): string
    {
        return self::MANIFEST_LOCK_KEY_PREFIX.$shipper->id.':'.$parent->id;
    }

    private function getClientCenterCode(Shipper $shipper): string
    {
        if (app()->environment('production')) {
            $clientCenterCode = Arr::get($shipper->settings, 'client_center_code', '');
        } else {
            $clientCenterCode = config('app.sandbox.shipper_ctt_token.client_center_code', '');
        }

        return $clientCenterCode;
    }

    private function fetchAccessToken(Shipper $shipper): array
    {
        if (app()->environment('production')) {
            $clientId     = Arr::get($shipper->settings, 'client_id', '');
            $clientSecret = Arr::get($shipper->settings, 'client_secret', '');
            $grantType    = Arr::get($shipper->settings, 'grant_type', 'client_credentials');
            $scope        = Arr::get($shipper->settings, 'scope', '');
        } else {
            $clientId     = config('app.sandbox.shipper_ctt_token.client_id', '');
            $clientSecret = config('app.sandbox.shipper_ctt_token.client_secret', '');
            $grantType    = config('app.sandbox.shipper_ctt_token.grant_type', 'client_credentials');
            $scope        = config('app.sandbox.shipper_ctt_token.scope', '');
        }

        if ($clientId === '' || $clientSecret === '') {
            throw new \RuntimeException('CTT credentials are missing in config (sandbox.ctt.client_id / client_secret).');
        }

        $response = Http::asForm()
            ->connectTimeout(10)
            ->timeout(30)
            ->post($this->getBaseUrl().'/oauth2/token', [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'grant_type'    => $grantType,
                'scope'         => $scope,
            ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                sprintf(
                    'Failed to get CTT ES access token. Status: %d Body: %s',
                    $response->status(),
                    $response->body()
                )
            );
        }

        $accessToken = (string)$response->json('access_token', '');
        if ($accessToken === '') {
            throw new \RuntimeException('CTT token response does not contain access_token.');
        }

        $expiresIn  = (int)$response->json('expires_in', self::TOKEN_FALLBACK_TTL_SECONDS);
        $ttlSeconds = max($expiresIn - self::TOKEN_TTL_BUFFER_SECONDS, 60);

        return [$accessToken, $ttlSeconds];
    }

    private function getHeaders(Shipper $shipper, bool $forceRefreshToken = false, array $extraHeaders = []): array
    {
        return array_merge([
            'Authorization' => 'Bearer '.$this->getAccessToken($shipper, $forceRefreshToken),
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ], $extraHeaders);
    }

    private function requestWithTokenRefresh(Shipper $shipper, callable $request): Response
    {
        $response = $request(false);

        if ($response->status() === 401) {
            Cache::forget($this->getTokenCacheKey($shipper));
            $response = $request(true);
        }

        return $response;
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(DeliveryNote|PalletReturn $parent, Shipper $shipper): array
    {
        $manifestLockKey = $this->getManifestLockKey($parent, $shipper);

        if (!Cache::add($manifestLockKey, 1, self::MANIFEST_LOCK_TTL_SECONDS)) {
            return [
                'status'    => 'fail',
                'modelData' => [],
                'errorData' => [
                    'message' => ['A shipment manifest request is already in progress for this delivery note.'],
                    'others'  => [],
                ],
            ];
        }

        try {
            $url = '/manifest/v2.0/shippings';

            if ($parent instanceof PalletReturn) {
                $parentResource = ShippingPalletReturnResource::make($parent)->getArray();
            } else {
                $parentResource = GetShippingDeliveryNoteData::run($parent);
            }

            $parcels = $parent->parcels ?? [];
            $items = [];
            foreach ($parcels as $parcel) {
                $dimensions = Arr::get($parcel, 'dimensions', []);

                $items[] = [
                    'item_weight_declared' => (float)Arr::get($parcel, 'weight', 1),
                    'item_length_declared' => (float)Arr::get($dimensions, 0, 10),
                    'item_width_declared'  => (float)Arr::get($dimensions, 1, 10),
                    'item_height_declared' => (float)Arr::get($dimensions, 2, 10),
                    'item_comments'        => 'Item from AW Artisan',
                ];
            }

            $fromAddress = Arr::get($parentResource, 'from_address', []);
            $toAddress   = Arr::get($parentResource, 'to_address', []);

            $customerReference = (string)Arr::get($parentResource, 'customer_reference', $parent->reference);
            $idempotencyKey    = hash('sha256', implode('|', [
                'ctt-es',
                $shipper->id,
                $parent->id,
                $customerReference,
            ]));

            $params = [
                'client_center_code'       => $this->getClientCenterCode($shipper),
                'shipping_type_code'       => 'C24',
                'department_code'          => '1',
                'client_references'        => [$customerReference],
                'shipping_weight_declared' => collect($parcels)->sum('weight') ?: 1,
                'item_count'               => count($items),

                'sender_name'                 => Str::limit(
                    Arr::get($parentResource, 'from_company_name')
                        ?: Arr::get($parentResource, 'from_contact_name'),
                    60
                ),
                'sender_country_code'         => Arr::get($fromAddress, 'country_code'),
                'sender_postal_code'          => Arr::get($fromAddress, 'postal_code'),
                'sender_address'              => trim(Arr::get($fromAddress, 'address_line_1').' '.Arr::get($fromAddress, 'address_line_2')),
                'sender_town'                 => Arr::get($fromAddress, 'locality'),
                'sender_email_notify_address' => Arr::get($parentResource, 'from_email'),
                'sender_phones'               => array_values(
                    array_filter(
                        [Arr::get($parentResource, 'from_phone')],
                        fn($phone) => filled($phone)
                    )
                ),

                'recipient_name'                 => Str::limit(
                    Arr::get($parentResource, 'to_company_name')
                        ?: Arr::get($parentResource, 'to_contact_name'),
                    60
                ),
                'recipient_country_code'         => Arr::get($toAddress, 'country_code'),
                'recipient_postal_code'          => Arr::get($toAddress, 'postal_code'),
                'recipient_address'              => trim(Arr::get($toAddress, 'address_line_1').' '.Arr::get($toAddress, 'address_line_2')),
                'recipient_town'                 => Arr::get($toAddress, 'locality'),
                'recipient_email_notify_address' => Arr::get($parentResource, 'to_email'),
                'recipient_phones'               => array_values(
                    array_filter(
                        [Arr::get($parentResource, 'to_phone')],
                        fn($phone) => filled($phone)
                    )
                ),

                'shipping_date' => now()->format('Y-m-d'),
                'delivery'      => [
                    'contact_name' => Str::limit((string)Arr::get($parentResource, 'to_contact_name'), 60),
                    'comments'     => Str::limit(strip_tags((string)($parent->shipping_notes ?? '')), 100),
                ],
                'items'         => $items,
            ];

            $params['custom_origin_name']         = $params['sender_name'];
            $params['custom_origin_country_code'] = $params['sender_country_code'];
            $params['custom_origin_postal_code']  = $params['sender_postal_code'];
            $params['custom_origin_address']      = $params['sender_address'];
            $params['custom_origin_town']         = $params['sender_town'];

            $codData = Arr::get($parentResource, 'cash_on_delivery');
            if (!empty($codData)) {
                $params['additionals'] = [
                    [
                        'additional_code'     => 'REE',
                        'additional_value'    => (float)Arr::get($codData, 'amount', 0),
                        'additional_flag'     => true,
                        'additional_text'     => 'Cash on delivery payment',
                        'additional_sub_code' => '',
                    ]
                ];
            }

            // Do not auto-retry manifest POST to avoid accidental duplicate shipments on ambiguous failures.
            $response = $this->requestWithTokenRefresh($shipper, function (bool $forceRefreshToken) use ($url, $params, $idempotencyKey, $shipper) {
                return Http::withHeaders($this->getHeaders($shipper, $forceRefreshToken, [
                    'Idempotency-Key' => $idempotencyKey,
                ]))
                    ->connectTimeout(10)
                    ->timeout(45)
                    ->post($this->getBaseUrl().$url, $params);
            });

            $apiResponse = $response->json();
            $statusCode  = $response->status();
            
            $modelData = ['api_response' => $apiResponse];
            $errorData = [];

            if ($statusCode === 201 && Arr::has($apiResponse, 'shipping_data.shipping_code')) {
                $status       = 'success';
                $shippingCode = Arr::get($apiResponse, 'shipping_data.shipping_code');

                $modelData['trackings']      = [$shippingCode];
                $modelData['tracking']       = $shippingCode;
                $modelData['label_type']     = ShipmentLabelTypeEnum::PDF;
                $modelData['number_parcels'] = count($parcels);
                $modelData['label']          = $this->getLabel($shipper, $shippingCode);
            } else {
                $status = 'fail';
                
                $errorDesc = Arr::get($apiResponse, 'error.error_description', 'Failed to manifest');
                $extendedMsg = Arr::get($apiResponse, 'error.error_extended_info.message', '');
                
                $fullError = trim($errorDesc . ' ' . $extendedMsg);
                
                $errorData['address'] = $fullError;
                $errorData['message'] = $fullError;
                $errorData['others']  = [$extendedMsg];
            }

            return [
                'status'    => $status,
                'modelData' => $modelData,
                'errorData' => $errorData,
            ];
        } finally {
            Cache::forget($manifestLockKey);
        }
    }

    public function getLabel(Shipper $shipper, string $shippingCode): string
    {
        $content = '';
        $url     = "/trf/labelling/v1.0/shippings/{$shippingCode}/shipping-labels?label_type_code=PDF&model_type_code=SINGLE&label_offset=1";

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = $this->requestWithTokenRefresh($shipper, function (bool $forceRefreshToken) use ($url, $shipper) {
                    return Http::withHeaders($this->getHeaders($shipper, $forceRefreshToken))
                        ->connectTimeout(10)
                        ->timeout(45)
                        ->get($this->getBaseUrl().$url);
                });

                if ($response->successful()) {
                    $apiResponse = $response->json();
                    $content     = (string)Arr::get($apiResponse, 'data.0.label', '');

                    if ($content !== '') {
                        return $content;
                    }
                }
            } catch (Throwable $e) {
                Sentry::captureException($e);
            }

            usleep($attempt * 300000); // 300ms, 600ms, 900ms
        }

        return '';
    }
}
