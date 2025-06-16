<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-11h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\Offer\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Offer $offer): string
    {
        return $offer->id;
    }

    public function handle(Offer $offer): void
    {
        $stats = [
            'number_invoices'   => $offer->invoiceTransactions()->distinct()->count('invoice_transaction_has_offer_components.invoice_id'),
        ];


        $offer->stats()->update($stats);
    }


}
