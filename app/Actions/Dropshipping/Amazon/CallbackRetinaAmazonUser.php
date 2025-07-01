<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 12-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Dropshipping\Amazon;

use App\Actions\Dropshipping\Amazon\Traits\WithAmazonApiRequest;
use App\Actions\Dropshipping\CustomerSalesChannel\UpdateCustomerSalesChannel;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\CustomerSalesChannelStateEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AmazonUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
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

    public string $commandSignature = 'amazon:auth {customer}';

    public function handle(Customer $customer, array $modelData): AmazonUser
    {
        $spApiAuthCode = Arr::pull($modelData, 'spapi_oauth_code');

        /** @var AmazonUser $amazonUser */
        $amazonUser = StoreAmazonUser::run($customer, []);
        $amazonUser->getAmazonTokens($spApiAuthCode);

        $amazonUser->refresh();
        $seller = $amazonUser->getSellerAccount();
        $amazonUser->getAmazonMarketplaceId();

        $this->update($amazonUser, [
            'data' => [
                'seller' => $seller
            ]
        ]);

        UpdateCustomerSalesChannel::run($amazonUser->customerSalesChannel, [
            'state' => CustomerSalesChannelStateEnum::AUTHENTICATED
        ]);

        return $amazonUser;
    }

    public function htmlResponse(AmazonUser $amazonUser): Response
    {
        $routeName = match ($amazonUser->customer->is_fulfilment) {
            true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
            default => 'retina.dropshipping.customer_sales_channels.show'
        };

        return Inertia::location(route($routeName, [
            'customerSalesChannel' => $amazonUser->customerSalesChannel->slug
        ]));
    }

    public function asController(ActionRequest $request): AmazonUser
    {
        return $this->handle($request->user()->customer, $request->all());
    }

    public function asCommand(Command $command): AmazonUser
    {
        $customer = $command->argument('customer');
        $customer = Customer::where('slug', $customer)->first();

        return $this->handle($customer, []);
    }
}
