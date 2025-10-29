<?php

namespace App\Actions\Dropshipping\Platform\Shop\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\PlatformShopSalesIntervals;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydratePlatformSalesIntervalsInvoices implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public function handle(Shop $shop, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $platformIds = Invoice::where('shop_id', $shop->id)
            ->select('platform_id')
            ->distinct()
            ->pluck('platform_id')
            ->filter();

        if ($platformIds->isEmpty()) {
            return;
        }

        foreach ($platformIds as $platformId) {
            $queryBase = Invoice
                ::where('in_process', false)
                ->where('platform_id', $platformId)
                ->where('shop_id', $shop->id)
                ->where('type', InvoiceTypeEnum::INVOICE)
                ->selectRaw('count(*) as sum_aggregate');

            $stats = $this->getIntervalsData(
                stats: [],
                queryBase: $queryBase,
                statField: 'invoices_',
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            PlatformShopSalesIntervals::updateOrCreate(
                [
                    'platform_id' => $platformId,
                    'shop_id'     => $shop->id
                ],
                $stats
            );
        }
    }
}
