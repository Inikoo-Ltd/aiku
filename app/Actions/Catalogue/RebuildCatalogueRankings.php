<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 11:30:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The catalogue's all time top listed families, top listed products and top sold products, per shop,
 * so the catalogue tabs read a few thousand rows instead of totalling millions of portfolios and
 * invoice transactions on every view (10-20 seconds each). Summed per shop so the group tabs add
 * the shops up: a portfolio's shop is always its customer's shop, so customer counts add up too.
 * Each table is replaced inside one transaction, so a reader sees the previous totals until the
 * new ones are in.
 */
class RebuildCatalogueRankings
{
    use AsAction;

    public string $commandSignature = 'catalogue:rebuild_rankings';

    public string $jobQueue = 'long-low-priority';

    public int $jobTimeout = 1800;

    public function handle(): void
    {
        DB::transaction(function () {
            DB::table('catalogue_top_listed_families')->delete();
            DB::statement("
                insert into catalogue_top_listed_families (shop_id, family_id, total_listed, total_customers)
                select portfolios.shop_id, products.family_id, count(portfolios.id), count(distinct portfolios.customer_id)
                from portfolios
                join products on portfolios.item_id = products.id and portfolios.item_type = 'Product'
                join assets on assets.id = products.asset_id
                join product_categories as families on families.id = products.family_id
                where assets.type = 'product' and portfolios.last_removed_at is null and products.family_id is not null
                group by portfolios.shop_id, products.family_id
            ");
        });

        DB::transaction(function () {
            DB::table('catalogue_top_listed_products')->delete();
            DB::statement("
                insert into catalogue_top_listed_products (shop_id, asset_id, total_listed, total_customers)
                select portfolios.shop_id, assets.id, count(portfolios.id), count(distinct portfolios.customer_id)
                from portfolios
                join products on portfolios.item_id = products.id and portfolios.item_type = 'Product'
                join assets on assets.id = products.asset_id
                where assets.type = 'product' and portfolios.last_removed_at is null
                group by portfolios.shop_id, assets.id
            ");
        });

        DB::transaction(function () {
            DB::table('catalogue_top_sold_products')->delete();
            DB::statement("
                insert into catalogue_top_sold_products (shop_id, asset_id, total_sold, total_amount, total_grp_amount)
                select invoice_transactions.shop_id, assets.id, sum(invoice_transactions.quantity), sum(invoice_transactions.net_amount), sum(invoice_transactions.grp_net_amount)
                from invoice_transactions
                join assets on invoice_transactions.asset_id = assets.id and invoice_transactions.model_type = 'Product'
                join invoices on invoice_transactions.invoice_id = invoices.id
                where assets.type = 'product' and invoice_transactions.deleted_at is null and invoice_transactions.in_process = false
                group by invoice_transactions.shop_id, assets.id
            ");
        });
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $this->handle();
        $command->info('Catalogue rankings rebuilt');

        return 0;
    }
}
