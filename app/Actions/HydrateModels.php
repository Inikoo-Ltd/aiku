<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Dec 2024 22:44:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Traits\WithOrganisationsArgument;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;

class HydrateModels extends HydrateModel
{
    use WithOrganisationsArgument;

    public string $commandSignature = 'hydrate {--s|sections=*}';

    public function asCommand(Command $command): int
    {
        if ($this->checkIfCanHydrate(['sysadmin', 'sys'], $command)) {
            $this->hydrateSysadmin($command);
        }

        if ($this->checkIfCanHydrate(['crm'], $command)) {
            $this->hydrateCrm($command);
        }

        if ($this->checkIfCanHydrate(['fulfilment', 'ful'], $command)) {
            $this->hydrateFulfilment($command);
        }

        if ($this->checkIfCanHydrate(['inventory', 'inv'], $command)) {
            $this->hydrateInventory($command);
        }

        if ($this->checkIfCanHydrate(['masters', 'mas'], $command)) {
            $this->hydrateMasters($command);
        }

        if ($this->checkIfCanHydrate(['goods'], $command)) {
            $this->hydrateGoods($command);
        }

        if ($this->checkIfCanHydrate(['catalogue', 'cat'], $command)) {
            $this->hydrateCatalogue($command);
        }

        if ($this->checkIfCanHydrate(['billables', 'bil'], $command)) {
            $this->hydrateBillables($command);
        }

        if ($this->checkIfCanHydrate(['discount', 'disc'], $command)) {
            $this->hydrateDiscount($command);
        }

        if ($this->checkIfCanHydrate(['website', 'web'], $command)) {
            $this->hydrateWebsite($command);
        }

        if ($this->checkIfCanHydrate(['comms'], $command)) {
            $this->hydrateComms($command);
        }

        if ($this->checkIfCanHydrate(['ordering'], $command)) {
            $this->hydrateOrdering($command);
        }

        if ($this->checkIfCanHydrate(['hr'], $command)) {
            $this->hydrateHr($command);
        }

        if ($this->checkIfCanHydrate(['dispatching', 'dis'], $command)) {
            $this->hydrateDispatching($command);
        }

        if ($this->checkIfCanHydrate(['accounting'], $command)) {
            $this->hydrateAccounting($command);
        }

        if ($this->checkIfCanHydrate(['procurement'], $command)) {
            $this->hydrateProcurement($command);
        }

        if ($this->checkIfCanHydrate(['supply_chain'], $command)) {
            $this->hydrateSupplyChain($command);
        }

        if ($this->checkIfCanHydrate(['production'], $command)) {
            $this->hydrateProduction($command);
        }

        if ($this->checkIfCanHydrate(['dropshipping', 'drop'], $command)) {
            $this->hydrateDropshipping($command);
        }

        return 0;
    }

    protected function hydrateDropshipping(Command $command): void
    {
        $command->info('Dropshipping ✊🏼');
        $command->call('hydrate:platforms');
        $command->call('hydrate:portfolios');
        $command->call('hydrate:customer_clients');
    }

    protected function hydrateDispatching(Command $command): void
    {
        $command->info('Dispatching 🚛️');
        $command->call('hydrate:delivery_notes');
    }

    protected function hydrateMasters(Command $command): void
    {
        $command->info('Master section 📐️');
        $command->call('hydrate:master_shops');
        $command->call('hydrate:master_product_categories');
    }

    protected function hydrateGoods(Command $command): void
    {
        $command->info('Goods section ⛅️');
        $command->call('hydrate:stocks');
        $command->call('hydrate:stock_families');
    }

    protected function hydrateCatalogue(Command $command): void
    {
        $command->info('Catalogue section 📚');
        $command->call('hydrate:shops');
        $command->call('hydrate:departments');
        $command->call('hydrate:sub_departments');
        $command->call('hydrate:families');
        $command->call('hydrate:products');
        $command->call('hydrate:assets');
    }

    protected function hydrateBillables(Command $command): void
    {
        $command->info('Billables section 💸');
        $command->call('hydrate:charges');
    }

    protected function hydrateDiscount(Command $command): void
    {
        $command->info('Discount section 💲');
        $command->call('hydrate:offers');
        $command->call('hydrate:offer_campaigns');
    }

    protected function hydrateWebsite(Command $command): void
    {
        $command->info('Website section 🌐');
        $command->call('hydrate:websites');
        $command->call('hydrate:webpages');
    }

    protected function hydrateComms(Command $command): void
    {
        $command->info('Comms section 📧');
        $command->call('hydrate:post_rooms');
        $command->call('hydrate:org_post_rooms');
        $command->call('hydrate:outboxes');
        $command->call('hydrate:mailshots');
    }

    protected function hydrateSysadmin(Command $command): void
    {
        $command->info('Sysadmin section 🛠');
        $command->call('hydrate:groups');
        $command->call('hydrate:organisations');
        $command->call('hydrate:users');
        $command->call('hydrate:guests');
    }

    protected function hydrateOrdering(Command $command): void
    {
        $command->info('Ordering section 🛒');
        $command->call('hydrate:orders');
        $command->call('hydrate:invoices');
        //todo $command->call('hydrate:purges');
        $command->call('hydrate:delivery_notes');
    }

    protected function hydrateHr(Command $command): void
    {
        $command->info('HR section 👩🏻‍💼');
        $command->call('hydrate:employees');
        $command->call('hydrate:workplaces');
        $command->call('hydrate:job_positions');
        $command->call('hydrate:clocking_machines');
    }

    protected function hydrateAccounting(Command $command): void
    {
        $command->info('Accounting section 💰');
        $command->call('hydrate:payments');
        $command->call('hydrate:payment_accounts');
        $command->call('hydrate:org_payment_service_provider');
        $command->call('hydrate:invoices');
        //todo $command->call('hydrate:customer_balances');
    }

    protected function hydrateProcurement(Command $command): void
    {
        $command->info('Procurement section 🚚');
        $command->call('hydrate:org_suppliers');
        $command->call('hydrate:org_agents');
        $command->call('hydrate:org_partners');
        $command->call('hydrate:purchase_orders');
    }

    protected function hydrateSupplyChain(Command $command): void
    {
        $command->info('Supply Chain section 🚛');
        //todo $command->call('hydrate:agents');
        //todo $command->call('hydrate:suppliers');
        $command->call('hydrate:supplier_products'); // not yet tested
    }

    protected function hydrateProduction(Command $command): void
    {
        $command->info('Production section 🏭');
        //todo $command->call('hydrate:job_orders');
        //todo $command->call('hydrate:raw_materials');
        //todo $command->call('hydrate:artefacts');
        //todo $command->call('hydrate:manufacture_tasks');
        //todo $command->call('hydrate:artisans');
    }

    protected function hydrateInventory(Command $command): void
    {
        $command->info('Inventory section 📦');
        $command->call('hydrate:warehouses');
        $command->call('hydrate:warehouse_areas');
        $command->call('hydrate:locations');
        $command->call('hydrate:org_stocks');
        $command->call('hydrate:org_stock_families');
    }

    protected function hydrateCrm(Command $command): void
    {
        $command->info('CRM section 👸🏻');
        $command->call('hydrate:customers');
        $command->call('hydrate:web_users');
    }

    protected function hydrateFulfilment(Command $command): void
    {
        $command->info('Fulfillment section 🚛');
        $command->call('hydrate:fulfilments');
        $command->call('hydrate:fulfilment_customers_status');
        $command->call('hydrate:recurring_bills');
        $command->call('hydrate:fulfilment_customers');
        $command->call('hydrate:rental_agreements');
        $command->call('hydrate:pallet_deliveries');
        $command->call('hydrate:recurring_bills');
        $command->call('hydrate:stored_items');
        $command->call('hydrate:pallet_stored_items');
        $command->call('hydrate:pallet_returns'); // not yet tested
        $command->call('stored_item:run_quantity');

        /** @var Shop $shop */
        foreach (Shop::where('type', ShopTypeEnum::FULFILMENT)->get() as $shop) {
            $command->call('hydrate:shops', [
                '-s' => $shop->slug
            ]);

            $command->call('hydrate:customers', [
                '-S' => $shop->slug
            ]);
        }
    }

    private function checkIfCanHydrate(array $keys, $command): bool
    {
        $result = array_intersect($keys, $command->option('sections'));
        if (count($command->option('sections')) == 0 || count($result)) {
            return true;
        }

        return false;
    }

}
