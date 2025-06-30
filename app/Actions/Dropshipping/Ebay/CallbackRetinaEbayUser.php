<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class CallbackRetinaEbayUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Customer $customer, array $modelData): EbayUser
    {
        $config = $this->getEbayConfig();

        try {
            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
            ])->post($this->getEbayTokenUrl(), [
                'grant_type' => 'authorization_code',
                'code' => Arr::get($modelData, 'code'),
                'redirect_uri' => $config['redirect_uri']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                /** @var EbayUser $ebayUser */
                $ebayUser = StoreEbayUser::run($customer, [
                    'settings' => [
                        'credentials' => [
                            'ebay_access_token' => $tokenData['access_token'],
                            'ebay_refresh_token' => $tokenData['refresh_token'],
                            'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                        ]
                    ]
                ]);

                $ebayUser->refresh();

                UpdateEbayUserData::dispatch($ebayUser);

                return $ebayUser;
            }

            throw new Exception('Failed to exchange code for token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay OAuth Token Exchange Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function htmlResponse(EbayUser $ebayUser): Response
    {
        $routeName = match ($ebayUser->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $ebayUser->customerSalesChannel->slug
        ]));
    }

    public function asController(ActionRequest $request): EbayUser
    {
        return $this->handle($request->user()->customer, $request->all());
    }
}
