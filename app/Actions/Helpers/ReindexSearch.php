<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 00:01:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\HydrateModel;
use App\Actions\Traits\WithOrganisationsArgument;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class ReindexSearch extends HydrateModel
{
    use WithOrganisationsArgument;

    public string $commandSignature = 'search {--s|sections=*}';

    public function asCommand(Command $command): int
    {
        if ($this->checkIfCanReindex(['crm'], $command)) {
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

        if ($this->checkIfCanReindex(['catalogue'], $command)) {
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

        if ($this->checkIfCanReindex(['ordering'], $command)) {
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

        if ($this->checkIfCanReindex(['supply_chain'], $command)) {
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
        //todo search $command->call('search:stocks');
        //todo search $command->call('search:stock_families');
        //todo search $command->call('search:trade_units');
        //todo search $command->call('search:ingredients');

    }

    protected function reindexCatalogue(Command $command): void
    {
        $command->info('Catalogue section 📚');
        $command->call('search:products');
        $command->call('search:product_categories');
        $command->call('search:collections');
    }

    protected function reindexBillables(Command $command): void
    {
        $command->info('Billables section 💸');
        $command->call('search:rentals');
        $command->call('search:charges');
        $command->call('search:services');
    }

    protected function reindexDiscount(Command $command): void
    {
        $command->info('Discount section💲');
        $command->call('search:offers');
        $command->call('search:offer_campaigns');
    }

    protected function reindexWebsite(Command $command): void
    {
        $command->info('Website section 🌐');
        $command->call('search:websites');
        $command->call('search:webpages');
        $command->call('search:banners');
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
        $command->call('search:users');
        //todo $command->call('search:guests');
        //todo $command->call('search:users requests');
    }

    protected function reindexOrdering(Command $command): void
    {
        $command->info('Ordering section 🛒');
        $command->call('search:orders');
        $command->call('search:invoices');
        //todo $command->call('search:purges');
        $command->call('search:delivery_notes');
    }

    protected function reindexHr(Command $command): void
    {
        $command->info('HR section 👩🏻‍💼');
        $command->call('search:employees');
        $command->call('search:workplaces');
        $command->call('search:job_positions');
        $command->call('search:clocking_machines');
    }

    protected function reindexAccounting(Command $command): void
    {
        $command->info('Accounting section 💰');
        $command->call('search:payments');
        $command->call('search:payment_accounts');
        // $command->call('search:topups'); -> error on record search
    }

    protected function reindexProcurement(Command $command): void
    {
        $command->info('Procurement section 🚚');
        $command->call('search:org_suppliers');
        $command->call('search:org_agents');
        $command->call('search:org_partners');
        $command->call('search:purchase_orders');
    }

    protected function reindexSupplyChain(Command $command): void
    {
        $command->info('Supply Chain section 🚛');
        $command->call('search:agents');
        $command->call('search:suppliers');
        $command->call('search:supplier_products'); // not yet tested
    }

    protected function reindexProduction(Command $command): void
    {
        $command->info('Production section 🏭');

    }

    protected function reindexInventory(Command $command): void
    {
        $command->info('Inventory section 📦');
        $command->call('search:warehouses');
        $command->call('search:warehouse_areas');
        $command->call('search:locations');
        $command->call('search:org_stocks');
        $command->call('search:org_stock_families');
    }

    protected function reindexCrm(Command $command): void
    {
        $command->info('CRM section 👸🏻');
        $command->call('search:customers');
        $command->call('search:prospects');
    }

    protected function reindexFulfilment(Command $command): void
    {
        $command->info('Fulfillment section 🚛');
        $command->call('search:rentals');
        $command->call('search:fulfilment_customers');
        $command->call('search:stored_items'); // not yet tested
        $command->call('search:stored_item_audits'); // not yet tested
        $command->call('search:pallet_returns'); // not yet tested
        $command->call('search:pallet_deliveries'); // not yet tested
        $command->call('search:pallets');

        /** @var Shop $shop */
        foreach (Shop::where('type', ShopTypeEnum::FULFILMENT)->get() as $shop) {
            $command->call('search:invoices', [
                '-S' => $shop->slug
            ]);
        }
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
