<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-11h-59m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\OfferAllowance\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\OfferAllowance;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferAllowanceHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferAllowance $offerAllowance): string
    {
        return $offerAllowance->id;
    }

    public function handle(OfferAllowance $offerAllowance): void
    {
        $stats = [
            'number_invoices' => $offerAllowance->invoiceTransactions()->distinct()->count('invoice_transaction_has_offer_allowances.invoice_id')
        ];

        $offerAllowance->stats()->update($stats);
    }

}
