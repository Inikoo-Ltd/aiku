<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 00:01:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\Catalogue\Collection\Search\ReindexCollectionSearch;
use App\Actions\Catalogue\Product\Search\ReindexProductSearch;
use App\Actions\Catalogue\ProductCategory\Search\ReindexProductCategorySearch;
use App\Actions\CRM\Customer\Search\ReindexCustomerSearch;
use App\Actions\Goods\Stock\Search\ReindexStockSearch;
use App\Actions\Goods\StockFamily\Search\ReindexStockFamilySearch;
use App\Actions\Goods\TradeUnit\Search\ReindexTradeUnitsSearch;
use App\Actions\Goods\TradeUnitFamily\Search\ReindexTradeUnitFamiliesSearch;
use App\Actions\HydrateModel;
use App\Actions\Inventory\Location\Search\ReindexLocationSearch;
use App\Actions\Ordering\Order\Search\ReindexOrdersSearch;
use App\Actions\SupplyChain\Supplier\Search\ReindexSupplierSearch;
use App\Actions\SysAdmin\Guest\Search\ReindexGuestSearch;
use App\Actions\SysAdmin\User\Search\ReindexUserSearch;
use App\Actions\Traits\WithOrganisationsArgument;
use Illuminate\Console\Command;

class ReindexSearch extends HydrateModel
{
    use WithOrganisationsArgument;

    public string $commandSignature = 'search {--s|sections=*} {--r|reset}';

    public function asCommand(Command $command): int
    {
        if ($this->checkIfCanReindex(['crm', 'cus', 'customers'], $command)) {
            $this->reindexCrm($command);
        }

        if ($this->checkIfCanReindex(['fulfilment', 'ful'], $command)) {
            $this->reindexFulfilment($command);
        }

        if ($this->checkIfCanReindex(['inventory', 'inv'], $command)) {
            $this->reindexInventory($command);
        }

        if ($this->checkIfCanReindex(['goods'], $command)) {
            $this->reindexGoods($command);
        }

        if ($this->checkIfCanReindex(['catalogue', 'cat'], $command)) {
            $this->reindexCatalogue($command);
        }

        if ($this->checkIfCanReindex(['billables'], $command)) {
            $this->reindexBillables($command);
        }

        if ($this->checkIfCanReindex(['discount'], $command)) {
            $this->reindexDiscount($command);
        }

        if ($this->checkIfCanReindex(['website'], $command)) {
            $this->reindexWebsite($command);
        }

        if ($this->checkIfCanReindex(['comms'], $command)) {
            $this->reindexComms($command);
        }

        if ($this->checkIfCanReindex(['sysadmin', 'sys'], $command)) {
            $this->reindexSysadmin($command);
        }

        if ($this->checkIfCanReindex(['ordering', 'o'], $command)) {
            $this->reindexOrdering($command);
        }

        if ($this->checkIfCanReindex(['hr'], $command)) {
            $this->reindexHr($command);
        }

        if ($this->checkIfCanReindex(['accounting'], $command)) {
            $this->reindexAccounting($command);
        }

        if ($this->checkIfCanReindex(['procurement'], $command)) {
            $this->reindexProcurement($command);
        }

        if ($this->checkIfCanReindex(['supply_chain', 'sup'], $command)) {
            $this->reindexSupplyChain($command);
        }

        if ($this->checkIfCanReindex(['production'], $command)) {
            $this->reindexProduction($command);
        }

        return 0;
    }


    protected function reindexGoods(Command $command): void
    {
        $command->info('Goods section ⛅️');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }

        ReindexStockSearch::run(reset: $command->option('reset'));
        ReindexStockFamilySearch::run(reset: $command->option('reset'));
        ReindexTradeUnitsSearch::run(reset: $command->option('reset'));
        ReindexTradeUnitFamiliesSearch::run(reset: $command->option('reset'));



        //todo search $command->call('search:ingredients');

    }

    protected function reindexCatalogue(Command $command): void
    {
        $command->info('Catalogue section 📚');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexCollectionSearch::run(reset: $command->option('reset'));
        ReindexProductCategorySearch::run(reset: $command->option('reset'));
        ReindexProductSearch::run(reset: $command->option('reset'));
    }

    protected function reindexBillables(Command $command): void
    {
        $command->info('Billables section 💸');
        //        $command->call('search:rentals');
        //        $command->call('search:charges');
        //        $command->call('search:services');
    }

    protected function reindexDiscount(Command $command): void
    {
        $command->info('Discount section💲');
        //        $command->call('search:offers');
        //        $command->call('search:offer_campaigns');
    }

    protected function reindexWebsite(Command $command): void
    {
        $command->info('Website section 🌐');
        //        $command->call('search:websites');
        //        $command->call('search:webpages');
        //        $command->call('search:banners');
    }

    protected function reindexComms(Command $command): void
    {
        $command->info('Comms section 📧');
        //todo $command->call('search:post_rooms');
        //todo $command->call('search:outboxes');
        // todo $command->call('search:newsletters');
        // todo $command->call('search:mailshots');
    }

    protected function reindexSysadmin(Command $command): void
    {
        $command->info('Sysadmin section 🛠');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexUserSearch::run(reset: $command->option('reset'));
        ReindexGuestSearch::run(reset: $command->option('reset'));
    }

    protected function reindexOrdering(Command $command): void
    {
        $command->info('Ordering section 🛒');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexOrdersSearch::run(reset: $command->option('reset'));


        //        $command->call('search:invoices');
        //        $command->call('search:delivery_notes');
    }

    protected function reindexHr(Command $command): void
    {
        $command->info('HR section 👩🏻‍💼');
        //        $command->call('search:employees');
        //        $command->call('search:workplaces');
        //        $command->call('search:job_positions');
        //        $command->call('search:clocking_machines');
    }

    protected function reindexAccounting(Command $command): void
    {
        $command->info('Accounting section 💰');
        //        $command->call('search:payments');
        //        $command->call('search:payment_accounts');
    }

    protected function reindexProcurement(Command $command): void
    {
        $command->info('Procurement section 🚚');
        //        $command->call('search:org_suppliers');
        //        $command->call('search:org_agents');
        //        $command->call('search:org_partners');
        //        $command->call('search:purchase_orders');
    }

    protected function reindexSupplyChain(Command $command): void
    {
        $command->info('Supply Chain section 🚛');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexSupplierSearch::run(reset: $command->option('reset'));


        //        $command->call('search:agents');
        //        $command->call('search:supplier_products'); // not yet tested
    }

    protected function reindexProduction(Command $command): void
    {
        $command->info('Production section 🏭');
    }

    protected function reindexInventory(Command $command): void
    {
        $command->info('Inventory section 📦');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        //        $command->call('search:warehouse_areas');
        ReindexLocationSearch::run(reset: $command->option('reset'));

        //        $command->call('search:org_stocks');
        //        $command->call('search:org_stock_families');
    }

    protected function reindexCrm(Command $command): void
    {
        $command->info('CRM section 👸🏻');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexCustomerSearch::run(reset: $command->option('reset'));
        //        $command->call('search:prospects');
    }

    protected function reindexFulfilment(Command $command): void
    {
        $command->info('Fulfillment section 🚛');
        //        $command->call('search:rentals');
        //        $command->call('search:fulfilment_customers');
        //        $command->call('search:stored_items'); // not yet tested
        //        $command->call('search:stored_item_audits'); // not yet tested
        //        $command->call('search:pallet_returns'); // not yet tested
        //        $command->call('search:pallet_deliveries'); // not yet tested
        //        $command->call('search:pallets');

    }

    private function checkIfCanReindex(array $keys, $command): bool
    {
        $result = array_intersect($keys, $command->option('sections'));
        if (count($command->option('sections')) == 0 || count($result)) {
            return true;
        }

        return false;
    }

}
