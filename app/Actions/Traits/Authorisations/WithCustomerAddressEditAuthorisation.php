<?php

namespace App\Actions\Traits\Authorisations;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithCustomerAddressEditAuthorisation
{
    /**
     * Customer addresses are edited from the customer page and from the order and pallet return pages,
     * so both customer service and the orders team keep the right to change them.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->shop->type == ShopTypeEnum::FULFILMENT) {
            return $request->user()->authTo("fulfilment-shop.{$this->shop->fulfilment->id}.edit");
        }

        return $request->user()->authTo(
            [
                "crm.{$this->shop->id}.edit",
                "orders.{$this->shop->id}.edit",
            ]
        );
    }
}
