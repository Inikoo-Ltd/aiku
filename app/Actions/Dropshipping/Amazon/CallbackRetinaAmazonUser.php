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
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\AmazonUser;
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


    public function handle(Customer $customer, array $modelData): ?AmazonUser
    {


        dd($customer, $modelData);



        return null;
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

    public function asController(ActionRequest $request): ?AmazonUser
    {
        return $this->handle($request->user()->customer, $request->all());
    }
}
