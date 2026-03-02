<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Mar 2026 01:49:45 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Catalogue;

use App\Actions\Catalogue\Product\SyncProductTradeUnits;
use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydratePackedIn;
use App\Actions\Inventory\OrgStock\SyncOrgStockTradeUnits;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\Shop;
use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Organisation;
use DB;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductsTradeUnitsFromAurora
{
    use AsAction;
    use WithOrganisationSource;

    public function handle(Product $product, Command $command): void
    {
        if (!$product->is_single_trade_unit) {
            return;
        }


        $sources = explode(':', $product->source_id);


        $auData = DB::connection('aurora')->table('Product Dimension')->where('Product ID', $sources[1])->first();

        $orgStocksData = [];

        foreach (
            DB::connection('aurora')
                ->table('Product Part Bridge')
                ->where('Product Part Product ID', $auData->{'Product ID'})->get() as $auroraProductsData
        ) {
            $orgStock = OrgStock::where('source_id', $product->organisation_id.':'.$auroraProductsData->{'Product Part Part SKU'})->first();

            if (!$orgStock) {
                $command->error('No org stock found for ('.$product->source_id.') '.$product->state->value.' '.$product->slug);
                return;
            } else {
                OrgStockHydratePackedIn::run($orgStock);
                if(!$orgStock->is_single_trade_unit){
                    $command->error('No org stock no single trade unit ('.$product->source_id.') '.$product->state->value.' '.$product->slug);
                }
                $orgStock->refresh();
                $orgStocksData[] = [
                    'trade_unit_id' => $product->tradeUnits->first()->id,
                    'org_stock_id'  => $orgStock->id,
                    'packed_in'     => $orgStock->packed_in,
                    'quantity'      => $auroraProductsData->{'Product Part Ratio'},
                ];
            }
        }

        if (count($orgStocksData) != 1) {
            $command->error('No orgStock diff 1   found for ('.$product->source_id.') '.$product->state->value.' '.$product->slug);
            print_r($orgStocksData);
            return;
        }


        $numberTradeUnits = null;
        $tradeUnitsId     = null;

        foreach ($orgStocksData as $orgStockData) {
            $numberTradeUnits = $orgStockData['quantity'] * $orgStockData['packed_in'];
            $tradeUnitsId     =$orgStockData['trade_unit_id'];
        }


        if($numberTradeUnits!=$product->units || $product->units!=$auData->{'Product Units Per Case'}){
            $command->error('Units diff ('.$product->source_id.') '.$product->state->value.' '.$product->slug.' '.$numberTradeUnits.':'.$product->units.':'.$auData->{'Product Units Per Case'});
            return;
        }



        $tradeUnits = [
            [
                'id'       => $tradeUnitsId,
                'quantity' => $numberTradeUnits,
            ]
        ];

        SyncProductTradeUnits::run($product, $tradeUnits);




    }

    /**
     * @throws \Exception
     */
    private function setSource(Organisation $organisation): void
    {
        $this->organisationSource = $this->getOrganisationSource($organisation);
        $this->organisationSource->initialisation($organisation);
    }


    public function getCommandSignature(): string
    {
        return 'maintenance:update_products_trade_units_from_aurora {organisation}';
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();

        $shops = Shop::where('organisation_id', $organisation->id)->where('state', ShopStateEnum::OPEN)
            ->where('type', ShopTypeEnum::B2B)->where('is_aiku', false)->pluck('id')->toArray();

        $this->setSource($organisation);
        Product::whereNotNull('source_id')
            ->whereIn('shop_id', $shops)
            ->where('state', '!=', ProductStateEnum::DISCONTINUED)
            ->whereNull('exclusive_for_customer_id')
            ->where('is_for_sale', true)
           // ->where('slug', 'awbp-01-uk')
            ->chunk(100, function ($products) use ($command) {
                foreach ($products as $product) {
                    $this->handle($product, $command);
                }
            });

        return 0;
    }

}
