<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 16:10:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\SyncProductExclusiveCustomers;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * A shop's intercompany range: products hidden from the site (not for sale, no live webpage)
 * whose every invoice went to a partner organisation's customer account. Those are exclusive
 * to the partners in all but the flag, so this sets the flag: every partner customer of the
 * shop is attached, not only the ones that happened to buy, since the range is shared.
 * Lists by default; --fix writes and leaves a csv under storage/app/repairs.
 */
class RepairPartnerExclusiveProducts
{
    use AsAction;

    public string $commandSignature = 'repair:partner_exclusive_products {shop : Shop slug} {--fix : Make them exclusive, otherwise only report}';

    /**
     * @return array<int, int>
     */
    public function partnerCustomerIds(Shop $shop): array
    {
        return DB::table('org_partners')
            ->join('customers', 'customers.id', 'org_partners.customer_id')
            ->where('customers.shop_id', $shop->id)
            ->pluck('customers.id')
            ->all();
    }

    /**
     * @param  array<int, int>  $partnerCustomerIds
     */
    public function candidates(Shop $shop, array $partnerCustomerIds): Builder
    {
        $buyers = DB::table('invoice_transactions as it')
            ->join('invoices as i', 'i.id', 'it.invoice_id')
            ->whereNull('it.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereColumn('it.asset_id', 'products.asset_id');

        return Product::where('shop_id', $shop->id)
            ->where('state', ProductStateEnum::ACTIVE)
            ->where('is_main', true)
            ->where('is_for_sale', false)
            ->whereNull('exclusive_for_customer_id')
            ->whereDoesntHave('webpage', fn ($query) => $query->where('state', WebpageStateEnum::LIVE))
            ->whereExists((clone $buyers))
            ->whereNotExists((clone $buyers)->whereNotIn('i.customer_id', $partnerCustomerIds))
            ->orderBy('code');
    }

    public function handle(Product $product, array $partnerCustomerIds): Product
    {
        return SyncProductExclusiveCustomers::make()->action($product, ['customer_ids' => $partnerCustomerIds]);
    }

    public function asCommand(Command $command): int
    {
        $shop     = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $partners = $this->partnerCustomerIds($shop);
        if (!$partners) {
            $command->error('Shop '.$shop->slug.' has no partner customer accounts.');

            return 1;
        }

        $rows = [];
        foreach ($this->candidates($shop, $partners)->get() as $product) {
            $rows[] = [$product->code, $product->name];
            if ($command->option('fix')) {
                $this->handle($product, $partners);
            }
        }

        $command->table(['Code', 'Name'], $rows);
        $command->info(count($rows).' products sold only to partners and hidden from the site.');

        if (!$command->option('fix')) {
            $command->line('Dry run. Pass --fix to make them exclusive to the '.count($partners).' partner customers.');

            return 0;
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['code', 'name', 'partner_customer_ids']);
        foreach ($rows as $row) {
            fputcsv($csv, [...$row, implode(' ', $partners)]);
        }
        rewind($csv);
        $path = 'repairs/partner_exclusive_products_'.$shop->slug.'_'.now()->format('Ymd_His').'.csv';
        Storage::disk('local')->put($path, stream_get_contents($csv));
        fclose($csv);

        $command->info(count($rows).' products made exclusive. Record: storage/app/'.$path);

        return 0;
    }
}
