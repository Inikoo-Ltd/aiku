<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 24 Jan 2024 16:14:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Fulfilment\FulfilmentCustomer;

use App\Actions\Fulfilment\Fulfilment\Hydrators\FulfilmentHydrateCustomers;
use App\Actions\OrgAction;
use App\Enums\Helpers\SerialReference\SerialReferenceModelEnum;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Fulfilment\FulfilmentCustomer;

class StoreFulfilmentCustomerFromCustomer extends OrgAction
{
    public function handle(Customer $customer, Shop $shop, array $modelData): FulfilmentCustomer
    {
        /** @var FulfilmentCustomer $fulfilmentCustomer */

        $fulfilmentCustomer = $customer->fulfilmentCustomer()->create(
            array_merge(
                $modelData,
                [
                    'created_at'      => $customer->created_at,
                    'fulfilment_id'   => $shop->fulfilment->id,
                    'group_id'        => $customer->group_id,
                    'organisation_id' => $customer->organisation_id,
                    'deleted_at'      => $customer->deleted_at
                ]
            )
        );

        $fulfilmentCustomer->refresh();


        $fulfilmentCustomer->serialReferences()->create(
            [
                'model'           => SerialReferenceModelEnum::PALLET_DELIVERY,
                'organisation_id' => $fulfilmentCustomer->organisation->id,
                'format'          => $fulfilmentCustomer->slug.'-%03d'
            ]
        );

        $fulfilmentCustomer->serialReferences()->create(
            [
                'model'           => SerialReferenceModelEnum::PALLET_RETURN,
                'organisation_id' => $fulfilmentCustomer->organisation->id,
                'format'          => $fulfilmentCustomer->slug.'-r%03d'
            ]
        );


        $fulfilmentCustomer->serialReferences()->create(
            [
                'model'           => SerialReferenceModelEnum::PALLET,
                'organisation_id' => $fulfilmentCustomer->organisation->id,
                'format'          => $fulfilmentCustomer->slug.'-p%04d'
            ]
        );

        $fulfilmentCustomer->serialReferences()->create(
            [
                'model'           => SerialReferenceModelEnum::RECURRING_BILL,
                'organisation_id' => $fulfilmentCustomer->organisation->id,
                'format'          => $fulfilmentCustomer->slug.'-b%03d'
            ]
        );

        $fulfilmentCustomer->serialReferences()->create(
            [
                'model'           => SerialReferenceModelEnum::STORED_ITEM_AUDIT,
                'organisation_id' => $fulfilmentCustomer->organisation->id,
                'format'          => $fulfilmentCustomer->slug.'-sia%03d'
            ]
        );

        FulfilmentHydrateCustomers::dispatch($fulfilmentCustomer->fulfilment);


        return $fulfilmentCustomer;
    }
}
