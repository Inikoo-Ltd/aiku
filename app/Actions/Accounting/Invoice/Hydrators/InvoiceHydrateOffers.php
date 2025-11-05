<?php

/*
 * author Arya Permana - Kirin
 * created on 18-11-2024-14h-22m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Accounting\Invoice\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class InvoiceHydrateOffers implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Invoice $invoice): string
    {
        return $invoice->id;
    }

    public function handle(Invoice $invoice): void
    {

        $stats = [
            'number_offer_allowances' => $invoice->invoiceTransactions->sum(function ($transaction) {
                return $this->countOfferAllowances($transaction);
            }),
            'number_offers' => $invoice->invoiceTransactions->sum(function ($transaction) {
                return $this->countOffers($transaction);
            }),
            'number_offer_campaigns' => $invoice->invoiceTransactions->sum(function ($transaction) {
                return $this->countOfferCampaigns($transaction);
            }),
        ];


        $invoice->stats()->update($stats);
    }

    public function countOfferAllowances(InvoiceTransaction $transaction): int
    {
        return $transaction->offerAllowances()
            ->distinct('offer_allowance_id')
            ->count('offer_allowance_id');
    }

    public function countOffers(InvoiceTransaction $transaction): int
    {
        return $transaction->offer()
            ->distinct('offer_id')
            ->count('offer_id');
    }

    public function countOfferCampaigns(InvoiceTransaction $transaction): int
    {
        return $transaction->offerCampaign()
            ->distinct('offer_campaign_id')
            ->count('offer_campaign_id');
    }

}
