<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Feb 2026 16:03:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateDeliveryNotesIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesCustomersStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrderIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateOrdersStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateTransactions;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Catalogue\Asset;
use Illuminate\Support\Facades\DB;

class HydrateAssetsSales
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:assets_sales {organisations?*} {--S|shop= shop slug} {--slugs=}';

    public function __construct()
    {
        $this->model = Asset::class;
    }

    public function handle(Asset $asset): void
    {
        AssetHydrateDeliveryNotesIntervals::run($asset->id);
        AssetHydrateSalesIntervals::run($asset->id);
        AssetHydrateInvoiceIntervals::run($asset->id);
        AssetHydrateInvoicedCustomersIntervals::run($asset->id);
        AssetHydrateTransactions::run($asset);
        AssetHydrateOrderIntervals::run($asset->id);
        AssetHydrateInvoicesCustomersStats::run($asset->id);
        AssetHydrateInvoicesStats::run($asset->id);
        AssetHydrateOrdersStats::run($asset->id);


        $stats = [
            'last_order_submitted_at'  => DB::table('transactions')->where('asset_id', $asset->id)->max('submitted_at'),
            'last_order_dispatched_at' => DB::table('transactions')->where('asset_id', $asset->id)->max('dispatched_at'),
        ];

        $asset->orderingStats()->update($stats);
    }


}
