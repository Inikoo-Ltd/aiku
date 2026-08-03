<?php

/** @noinspection PhpUnused */

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 00:01:17 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers;

use App\Actions\Accounting\Invoice\Search\ReindexInvoicesSearch;
use App\Actions\Accounting\Payment\Search\ReindexPaymentsSearch;
use App\Actions\Billables\Charge\Search\ReindexChargesSearch;
use App\Actions\Billables\Service\Search\ReindexServicesSearch;
use App\Actions\Billables\ShippingZone\Search\ReindexShippingZonesSearch;
use App\Actions\Billables\ShippingZoneSchema\Search\ReindexShippingZoneSchemasSearch;
use App\Actions\Chat\ChatMessage\Search\ReindexChatMessagesSearch;
use App\Actions\Comms\Mailshot\Search\ReindexMailshotsSearch;
use App\Actions\Discounts\Offer\Search\ReindexOffersSearch;
use App\Actions\Discounts\OfferCampaign\Search\ReindexOfferCampaignsSearch;
use App\Actions\Helpers\Barcode\Search\ReindexBarcodesSearch;
use App\Actions\Helpers\Brand\Search\ReindexBrandsSearch;
use App\Actions\Helpers\Tag\Search\ReindexTagsSearch;
use App\Actions\Masters\MasterAsset\Search\ReindexMasterAssetsSearch;
use App\Actions\Masters\MasterCollection\Search\ReindexMasterCollectionsSearch;
use App\Actions\Masters\MasterProductCategory\Search\ReindexMasterProductCategoriesSearch;
use App\Actions\Web\Webpage\Search\ReindexWebpagesSearch;
use App\Actions\Catalogue\Collection\Search\ReindexCollectionSearch;
use App\Actions\Catalogue\Product\Search\ReindexProductSearch;
use App\Actions\Catalogue\ProductCategory\Search\ReindexProductCategorySearch;
use App\Actions\CRM\Customer\Search\ReindexCustomerSearch;
use App\Actions\CRM\Prospect\Search\ReindexProspectSearch;
use App\Actions\Dispatching\DeliveryNote\Search\ReindexDeliveryNotesSearch;
use App\Actions\Goods\Stock\Search\ReindexStockSearch;
use App\Actions\Goods\StockFamily\Search\ReindexStockFamilySearch;
use App\Actions\Goods\TradeUnit\Search\ReindexTradeUnitsSearch;
use App\Actions\Goods\TradeUnitFamily\Search\ReindexTradeUnitFamiliesSearch;
use App\Actions\HumanResources\Employee\Search\ReindexEmployeesSearch;
use App\Actions\HydrateModel;
use App\Actions\Inventory\Location\Search\ReindexLocationsSearch;
use App\Actions\Inventory\OrgStock\Search\ReindexOrgStockSearch;
use App\Actions\Inventory\OrgStockFamily\Search\ReindexOrgStockFamilySearch;
use App\Actions\Inventory\WarehouseArea\Search\ReindexWarehouseAreaSearch;
use App\Actions\Ordering\Order\Search\ReindexOrdersSearch;
use App\Actions\Reviews\Search\ReindexReviewsSearch;
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

        if ($this->checkIfCanReindex(['locations', 'warehouse', 'loc', 'w'], $command)) {
            $this->reindexWarehouse($command);
        }

        if ($this->checkIfCanReindex(['goods'], $command)) {
            $this->reindexGoods($command);
        }

        if ($this->checkIfCanReindex(['catalogue', 'cat'], $command)) {
            $this->reindexCatalogue($command);
        }

        if ($this->checkIfCanReindex(['reviews', 'rev'], $command)) {
            $this->reindexReviews($command);
        }

        if ($this->checkIfCanReindex(['billables', 'bil'], $command)) {
            $this->reindexBillables($command);
        }

        if ($this->checkIfCanReindex(['masters', 'mas'], $command)) {
            $this->reindexMasters($command);
        }

        if ($this->checkIfCanReindex(['trade_units', 'tu'], $command)) {
            $this->reindexTradeUnits($command);
        }

        if ($this->checkIfCanReindex(['chat'], $command)) {
            $this->reindexChat($command);
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

        if ($this->checkIfCanReindex(['accounting', 'acc'], $command)) {
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

    protected function reindexReviews(Command $command): void
    {
        $command->info('Reviews section ⭐️');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexReviewsSearch::run(reset: $command->option('reset'));
    }

    protected function reindexBillables(Command $command): void
    {
        $command->info('Billables section 🧾');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexChargesSearch::run(reset: $command->option('reset'));
        ReindexServicesSearch::run(reset: $command->option('reset'));
        ReindexShippingZoneSchemasSearch::run(reset: $command->option('reset'));
        ReindexShippingZonesSearch::run(reset: $command->option('reset'));
    }

    protected function reindexMasters(Command $command): void
    {
        $command->info('Masters section 🏛️');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexMasterAssetsSearch::run(reset: $command->option('reset'));
        ReindexMasterProductCategoriesSearch::run(reset: $command->option('reset'));
        ReindexMasterCollectionsSearch::run(reset: $command->option('reset'));
    }

    protected function reindexChat(Command $command): void
    {
        $command->info('Chat section 💬');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexChatMessagesSearch::run(reset: $command->option('reset'));
    }

    protected function reindexTradeUnits(Command $command): void
    {
        $command->info('Trade units section 📦');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexBrandsSearch::run(reset: $command->option('reset'));
        ReindexTagsSearch::run(reset: $command->option('reset'));
        ReindexBarcodesSearch::run(reset: $command->option('reset'));
    }


    protected function reindexDiscount(Command $command): void
    {
        $command->info('Discount section💲');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexOffersSearch::run(reset: $command->option('reset'));
        ReindexOfferCampaignsSearch::run(reset: $command->option('reset'));
    }

    protected function reindexWebsite(Command $command): void
    {
        $command->info('Website section 🌐');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexWebpagesSearch::run(reset: $command->option('reset'));
    }

    protected function reindexComms(Command $command): void
    {
        $command->info('Comms section 📧');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexMailshotsSearch::run(reset: $command->option('reset'));
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
        ReindexInvoicesSearch::run(reset: $command->option('reset'));
        ReindexDeliveryNotesSearch::run(reset: $command->option('reset'));
    }

    protected function reindexHr(Command $command): void
    {
        $command->info('HR section 👩🏻‍💼');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexEmployeesSearch::run(reset: $command->option('reset'));

    }

    protected function reindexAccounting(Command $command): void
    {
        $command->info('Accounting section 💰');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexInvoicesSearch::run(reset: $command->option('reset'));
        ReindexPaymentsSearch::run(reset: $command->option('reset'));
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

    protected function reindexWarehouse(Command $command): void
    {
        $command->info('Warehouse section 📦');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexWarehouseAreaSearch::run(reset: $command->option('reset'));
        ReindexLocationsSearch::run(reset: $command->option('reset'));
    }

    protected function reindexInventory(Command $command): void
    {
        $command->info('Inventory section 📦');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexOrgStockSearch::run(reset: $command->option('reset'));
        ReindexOrgStockFamilySearch::run(reset: $command->option('reset'));
    }

    protected function reindexCrm(Command $command): void
    {
        $command->info('CRM section 👸🏻');
        if ($command->option('reset')) {
            $command->warn('Resetting search indexes');
        }
        ReindexCustomerSearch::run(reset: $command->option('reset'));
        ReindexProspectSearch::run(reset: $command->option('reset'));
    }

    protected function reindexFulfilment(Command $command): void
    {
        $command->info('Fulfillment section 🚛');
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
