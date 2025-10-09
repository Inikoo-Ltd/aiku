<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
    public function handle(Customer $customer, array $modelData): string
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
                $userData = $ebayUser->getUser();

                if (CustomerSalesChannel::where('name', Arr::get($userData, 'username'))->exists()) {
                    $ebayUser->customerSalesChannel->delete();
                    $ebayUser->delete();
                    return route('retina.dropshipping.customer_sales_channels.create', [
                        'status' => 'error',
                        'reason' => 'duplicate-ebay'
                    ]);
                }

                $ebayUser = UpdateEbayUser::run($ebayUser, [
                    'name' => Arr::get($userData, 'username'),
                ]);

                CheckEbayChannel::run($ebayUser);

                UpdateCustomerSalesChannel::run($ebayUser->customerSalesChannel, [
                    'reference' => Arr::get($userData, 'username'),
                    'name' => Arr::get($userData, 'username')
                ]);

                UpdateEbayUserData::dispatch($ebayUser);

                $routeName = match ($ebayUser->customer->is_fulfilment) {
                    true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
                    default => 'retina.dropshipping.customer_sales_channels.show'
                };

                return route($routeName, [
                    'customerSalesChannel' => $ebayUser->customerSalesChannel->slug
                ]);
            }

            throw new Exception('Failed to exchange code for token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay OAuth Token Exchange Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function htmlResponse(string $url): Response
    {
        return redirect($url);
    }

    public function asController(ActionRequest $request): string
    {
        return $this->handle($request->user()->customer, $request->all());
    }
}
