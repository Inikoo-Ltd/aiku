<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-13h-20m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Discounts\OfferCampaign\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OfferCampaignHydrateInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(OfferCampaign $offerCampaign): string
    {
        return $offerCampaign->id;
    }

    public function handle(OfferCampaign $offerCampaign): void
    {
        $stats = [
            'number_invoices' => $offerCampaign->invoiceTransactions()->distinct()->count('invoice_transaction_has_offer_components.invoice_id')
        ];

        $offerCampaign->stats()->update($stats);
    }


}
