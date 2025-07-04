<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Jul 2025 20:42:45 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay\Orders;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class FetchEbayUserOrders extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    /**
     * @throws \Throwable
     */
    public function handle(EbayUser $ebayUser): void
    {
        $existingOrderKeys = $ebayUser
            ->customerSalesChannel
            ->orders()
            ->pluck('data')
            ->map(fn($data) => Arr::get($data, 'orderId'))
            ->filter()
            ->toArray();


        print_r($existingOrderKeys);

        $response = $ebayUser->getOrders();

        print_r($response);

        print "=====";


        //        DB::transaction(function () use ($ebayUser) {
        //
        //            foreach ($response['orders'] as $order) {
        //                if (in_array(Arr::get($order, 'orderId'), $existingOrderKeys, true)) {
        //                    continue;
        //                }
        //
        //                if (!empty(array_filter(Arr::get($order, 'buyer'))) && !empty(array_filter(Arr::get($order, 'buyer.buyerRegistrationAddress')))) {
        //                    StoreOrderFromEbay::run($ebayUser, $order);
        //                } else {
        //                    \Sentry::captureMessage('The order doesnt have shipping, order: id ' . Arr::get($order, 'orderId'));
        //                }
        //            }
        //        });
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($ebayUser->organisation, $request);

        $this->handle($ebayUser);
    }
}
