<?php

namespace App\Actions\Catalogue\ProductCategory\UI;

use Lorisleiva\Actions\ActionRequest;

trait WithCategoryOfferPermissions
{
    public function canEditOffers(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "discounts.{$this->shop->id}.edit",
            "supervisor-discounts.{$this->shop->id}",
        ]);
    }
}
