<?php

namespace App\Actions\Traits\Authorisations\Ordering;

use App\Models\Catalogue\Shop;

trait WithOrderPaymentAuthorisation
{
    use WithOrderEditAuthorisation;

    /**
     * Payments, balance moves and invoicing on an order are worked by the orders team and by
     * the accounts office alike.
     *
     * @return array<int, string>
     */
    protected function getOrderEditPermissions(Shop $shop): array
    {
        return [
            "orders.$shop->id.edit",
            "accounting.$shop->organisation_id.edit",
        ];
    }
}
