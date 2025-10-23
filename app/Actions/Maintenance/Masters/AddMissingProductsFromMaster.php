<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 23 Oct 2025 11:24:47 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Masters;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class AddMissingProductsFromMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop): void
    {
        /** @var MasterAsset $masterProduct */
        foreach ($masterShop->masterAssets()->where('type', MasterAssetTypeEnum::PRODUCT)->where('is_main', true)->get() as $masterProduct) {
            /** @var Shop $shop */
            foreach ($masterShop->shops()->where('is_aiku', true)->get() as $shop) {
                if (!$shop->products()->where('master_product_id', $masterProduct->id)->exists()) {
                    list($hasAllOrgStocks, $hasDiscontinuing, $hasDiscontinued) = $this->getOrgStocksData($shop->organisation, $masterProduct->tradeUnits);

                    if ($hasAllOrgStocks && !$hasDiscontinuing && !$hasDiscontinued) {
                        $anchorShop = $this->getSeederShop($masterShop, $shop->organisation);

                        $anchorProduct = $anchorShop->products()->where('master_product_id', $masterProduct->id)->first();
                        if (!$anchorProduct) {
                            print "Skipping Product $masterProduct->code has no anchor product in shop $anchorShop->slug \n";
                            continue;
                        }

                        $exchange = GetCurrencyExchange::run($anchorShop->currency, $shop->currency);
                        $price    = round($anchorProduct->price * $exchange, 2);
                        $rrp      = null;
                        if ($anchorProduct->rrp) {
                            $rrp = round($anchorProduct->rrp * $exchange, 2);
                        }
                        $createWebpage = false;
                        if ($anchorProduct->is_for_sale) {
                            $createWebpage = true;
                        }



                        if (!$masterProduct->masterFamily) {
                            print "Skipping Product $masterProduct->code master product do not have master family $shop->slug \n";

                        } else {


                            $productCategories = $masterProduct->masterFamily->productCategories;
                            if (!$productCategories) {
                                print "Skipping Product $masterProduct->code master family do not have local family $shop->slug \n";
                            } else {
                                print "Adding product $masterProduct->code to shop $shop->slug \n";

                                StoreProductFromMasterProduct::make()->action($masterProduct, [
                                    'shop_products' => [
                                        $shop->id => [
                                            'price'          => $price,
                                            'rrp'            => $rrp,
                                            'create_webpage' => $createWebpage,
                                        ]
                                    ]
                                ]);
                            }



                        }


                    } elseif (!$hasAllOrgStocks) {
                        print "Skipping Product $masterProduct->code has no org stocks in shop $shop->slug \n";
                    } elseif ($hasDiscontinued) {
                        print "Skipping Product $masterProduct->code has discontinued in shop $shop->slug \n";
                    } else {
                        print "Skipping Product $masterProduct->code has discontinuing org stocks in shop $shop->slug \n";
                    }
                }
            }
        }
    }

    public function getOrgStocksData(Organisation $organisation, $tradeUnits): array
    {
        $hasAnyOrgStocks  = false;
        $hasAllOrgStocks  = true;
        $hasDiscontinuing = false;
        $hasDiscontinued  = false;


        $orgStocksData = [];
        foreach ($tradeUnits as $tradeUnit) {
            $orgStocks = $tradeUnit->orgStocks()->where('organisation_id', $organisation->id)->get();
            if ($orgStocks->count() == 0) {
                $hasAllOrgStocks = false;
            } else {
                $hasAnyOrgStocks = true;
            }

            /** @var OrgStock $orgStock */
            foreach ($orgStocks as $orgStock) {
                if ($orgStock->state == OrgStockStateEnum::DISCONTINUING) {
                    $hasDiscontinuing = true;
                }
                if ($orgStock->state == OrgStockStateEnum::DISCONTINUED) {
                    $hasDiscontinued = true;
                }
            }
        }

        if (!$hasAnyOrgStocks) {
            $hasAllOrgStocks = false;
        }


        return [$hasAllOrgStocks, $hasDiscontinuing, $hasDiscontinued];
    }


    public function getEcomSeederShop(Organisation $organisation): Shop
    {
        $shopId = match ($organisation->slug) {
            'sk' => 18,
            'es' => 33,
            'aroma' => 40,
            default => 1
        };

        return Shop::find($shopId);
    }

    public function getSeederShop(MasterShop $masterShop, Organisation $organisation): Shop
    {
        $shopId = match ($masterShop->slug) {
            'aw' => $this->getEcomSeederShop($organisation)->id,
            'ds' => 13,
            'ac' => 9,
            'aroma' => 40,
            'ful' => 15
        };

        return Shop::find($shopId);
    }

    public function getCommandSignature(): string
    {
        return 'repair:add_missing_product_s_from_master {master}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master'))->firstOrFail();

        $this->handle($masterShop);

        return 0;
    }


}
