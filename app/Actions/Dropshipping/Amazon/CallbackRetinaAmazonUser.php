<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon;

use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\Dropshipping\Amazon\Traits\WithAmazonApiRequest;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AmazonUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class CallbackRetinaAmazonUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithAmazonApiRequest;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Customer $customer, array $modelData): ?AmazonUser
    {
        // $config = $this->getAmazonConfig();


        dd($modelData);

        // try {
        //     $response = Http::asForm()->withHeaders([
        //         'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
        //     ])->post($this->getAmazonTokenUrl(), [
        //         'grant_type' => 'authorization_code',
        //         'code' => Arr::get($modelData, 'code'),
        //         'redirect_uri' => $config['redirect_uri']
        //     ]);

        //     if ($response->successful()) {
        //         $tokenData = $response->json();

        //         /** @var AmazonUser $AmazonUser */
        //         $AmazonUser = StoreAmazonUser::run($customer, [
        //             'settings' => [
        //                 'credentials' => [
        //                     'amazon_access_token' => $tokenData['access_token'],
        //                     'amazon_refresh_token' => $tokenData['refresh_token'],
        //                     'amazon_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
        //                 ]
        //             ]
        //         ]);

        //         $AmazonUser->refresh();

        //         // Setup Amazon seller account settings
        //         $AmazonUser->initializeSellerAccount();

        //         UpdateCustomerSalesChannel::run($AmazonUser->customerSalesChannel, [
        //             'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        //         ]);

        //         return $AmazonUser;
        //     }

        //     throw new Exception('Failed to exchange code for token: ' . $response->body());
        // } catch (Exception $e) {
        //     Log::error('Amazon OAuth Token Exchange Error: ' . $e->getMessage());
        //     throw $e;
        // }

        return null;
    }

    public function htmlResponse(AmazonUser $AmazonUser): Response
    {
        $routeName = match ($AmazonUser->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $AmazonUser->customerSalesChannel->slug
        ]));
    }

    public function asController(ActionRequest $request): ?AmazonUser
    {
        return $this->handle($request->user()->customer, $request->all());
    }
}
