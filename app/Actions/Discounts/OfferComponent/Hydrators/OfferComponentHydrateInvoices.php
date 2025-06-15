<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-11h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\OfferComponent\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\OfferComponent;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferComponentHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferComponent $offerComponent): string
    {
        return $offerComponent->id;
    }

    public function handle(OfferComponent $offerComponent): void
    {
        $stats = [
            'number_invoices' => $offerComponent->invoiceTransactions()->distinct()->count('invoice_transaction_has_offer_components.invoice_id')
        ];

        $offerComponent->stats()->update($stats);
    }

}
