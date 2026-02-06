<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jan 2026 15:51:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters;

use App\Actions\Catalogue\Product\StoreProductFromMasterProduct;
use App\Actions\Catalogue\Product\StoreProductWebpage;
use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\Masters\MasterAsset\MasterAssetTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\OrgStock;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterShop;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductsFromMaster
{
    use asAction;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop $masterShop, Shop $shop): void
    {
        /** @var MasterAsset $masterProduct */
        foreach ($masterShop->masterAssets()->where('type', MasterAssetTypeEnum::PRODUCT)->get() as $masterProduct) {
            $this->upsertProduct($shop, $masterProduct);
        }
    }


    /**
     * @throws \Throwable
     */
    public function upsertProduct(Shop $shop, MasterAsset $masterProduct): void
    {
        $masterShop = $masterProduct->masterShop;

        if (!$shop->products()->where('master_product_id', $masterProduct->id)->exists()) {
            list($hasAllOrgStocks, $hasDiscontinuing, $hasDiscontinued) = $this->getOrgStocksData($shop->organisation, $masterProduct->tradeUnits);

            if ($hasAllOrgStocks && !$hasDiscontinuing && !$hasDiscontinued) {
                $anchorShop = $this->getSeederShop($masterShop, $shop->organisation);

                $anchorProduct = $anchorShop->products()->where('master_product_id', $masterProduct->id)->first();
                if (!$anchorProduct) {
                    print "Skipping Product $masterProduct->code has no anchor product in shop $anchorShop->slug \n";

                    return;
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

                        try {
                            StoreProductFromMasterProduct::make()->action(
                                $masterProduct,
                                [
                                    'shop_products' => [
                                        $shop->id => [
                                            'price' => $price,
                                            'rrp' => $rrp,
                                            'create_webpage' => $createWebpage,
                                            'create_in_shop' => 'Yes'
                                        ]
                                    ],
                                ]
                            );
                        }catch (\Throwable $e) {
                            print $masterProduct->code.' '.$e->getMessage()." can not create product\n";
                        }

                        $product = $shop->products()->where('master_product_id', $masterProduct->id)->first();
                        if ($product) {
                            try {
                                $webpage = StoreProductWebpage::make()->action($product);
                                PublishWebpage::make()->action(
                                    $webpage,
                                    [
                                        'comment' => 'Published after cloning',
                                    ]
                                );
                            } catch (\Throwable $e) {
                                print $product->slug.' '.$e->getMessage()." can not create product webpage\n";
                            }
                        }


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


    public function getOrgStocksData(Organisation $organisation, $tradeUnits): array
    {
        $hasAnyOrgStocks  = false;
        $hasAllOrgStocks  = true;
        $hasDiscontinuing = false;
        $hasDiscontinued  = false;


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
        return 'clone:products_from_master {master} {shop}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $masterShop = MasterShop::where('slug', $command->argument('master'))->firstOrFail();
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();

        $this->handle($masterShop, $shop);

        return 0;
    }


}
