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
 * Exclusives that were never recorded, found among products hidden from the site (not for
 * sale, no live webpage) by who bought them. Every invoice to a partner organisation's customer
 * account: the intercompany range, exclusive to all partner customers of the shop since that
 * range is shared. Every invoice to one single other customer: private label, exclusive to
 * that customer. Lists by default; --fix writes and leaves a csv under storage/app/repairs.
 */
class RepairUnrecordedExclusiveProducts
{
    use AsAction;

    public string $commandSignature = 'repair:unrecorded_exclusive_products {shop : Shop slug} {--fix : Record the exclusivity, otherwise only report}';

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

    protected function buyers(): \Illuminate\Database\Query\Builder
    {
        return DB::table('invoice_transactions as it')
            ->join('invoices as i', 'i.id', 'it.invoice_id')
            ->whereNull('it.deleted_at')
            ->whereNull('i.deleted_at')
            ->whereColumn('it.asset_id', 'products.asset_id');
    }

    protected function hidden(Shop $shop): Builder
    {
        return Product::where('shop_id', $shop->id)
            ->where('state', ProductStateEnum::ACTIVE)
            ->where('is_main', true)
            ->where('is_for_sale', false)
            ->whereNull('exclusive_for_customer_id')
            ->whereDoesntHave('webpage', fn ($query) => $query->where('state', WebpageStateEnum::LIVE))
            ->whereExists($this->buyers())
            ->orderBy('code');
    }

    /**
     * @param  array<int, int>  $partnerCustomerIds
     */
    public function candidates(Shop $shop, array $partnerCustomerIds): Builder
    {
        return $this->hidden($shop)
            ->whereNotExists($this->buyers()->whereNotIn('i.customer_id', $partnerCustomerIds));
    }

    /**
     * Products every invoice of which went to one customer, that customer's id alongside.
     */
    public function singleBuyerCandidates(Shop $shop): Builder
    {
        return $this->hidden($shop)
            ->addSelect(['products.*', 'buyer_id' => $this->buyers()->selectRaw('min(i.customer_id)')])
            ->whereRaw('('.$this->buyers()->selectRaw('count(distinct i.customer_id)')->toRawSql().') = 1');
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
            $rows[] = [$product->code, $product->name, 'partners', implode(' ', $partners)];
            if ($command->option('fix')) {
                $this->handle($product, $partners);
            }
        }
        $partnerCount = count($rows);

        foreach ($this->singleBuyerCandidates($shop)->get() as $product) {
            $rows[] = [$product->code, $product->name, 'single customer', $product->buyer_id];
            if ($command->option('fix')) {
                $this->handle($product, [$product->buyer_id]);
            }
        }

        $command->table(['Code', 'Name', 'Exclusive to', 'Customer ids'], $rows);
        $command->info($partnerCount.' products sold only to partners, '.(count($rows) - $partnerCount).' sold to one single customer, all hidden from the site.');

        if (!$command->option('fix')) {
            $command->line('Dry run. Pass --fix to record them as exclusive.');

            return 0;
        }

        $csv = fopen('php://temp', 'r+');
        fputcsv($csv, ['code', 'name', 'exclusive_to', 'customer_ids']);
        foreach ($rows as $row) {
            fputcsv($csv, $row);
        }
        rewind($csv);
        $path = 'repairs/partner_exclusive_products_'.$shop->slug.'_'.now()->format('Ymd_His').'.csv';
        Storage::disk('local')->put($path, stream_get_contents($csv));
        fclose($csv);

        $command->info(count($rows).' products made exclusive. Record: storage/app/'.$path);

        return 0;
    }
}
