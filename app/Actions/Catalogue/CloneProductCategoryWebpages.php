<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Jul 2025 14:21:05 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue;

use App\Actions\Catalogue\ProductCategory\StoreProductCategoryWebpage;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\WebBlock\DeleteWebBlock;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class CloneProductCategoryWebpages
{
    use asAction;
    use WithStoreWebpage;

    /**
     * @throws \Throwable
     */
    public function handle(MasterShop|Shop $fromShop, Shop $shop): void
    {

        foreach (DB::table('product_categories')->where('shop_id', $fromShop->id)->pluck('id') as $fromCategoryID) {
            $fromCategory = ProductCategory::find($fromCategoryID);

            $fromWebpage = null;
            if ($fromCategory) {
                $fromWebpage = $fromCategory->webpage;
            }
            if (!$fromWebpage) {
                continue;
            }


            $code = $fromCategory->code;

            $foundCategoryData = DB::table('product_categories')
                ->where('shop_id', $shop->id)
                ->whereRaw("lower(code) = lower(?)", [$code])->first();

            if ($foundCategoryData) {
                $foundCategory = ProductCategory::find($foundCategoryData->id);

                $webpage = $foundCategory->webpage;

                if (!$webpage) {
                    $webpage = StoreProductCategoryWebpage::run($foundCategory);
                }



                print "Clone webpage for category: ".$foundCategory->code."  ".$foundCategory->type->value." ".$webpage->url."  \n";

                foreach ($webpage->webBlocks as $webBlock) {
                    DeleteWebBlock::run($webBlock);

                }
                foreach ($fromWebpage->webBlocks as $fromWebBlock) {
                    $modelHasWebBlocks = $this->createWebBlock($webpage, $fromWebBlock->webBlockType->code);

                    UpdateModelHasWebBlocks::run(
                        $modelHasWebBlocks,
                        [
                            'layout' => $fromWebBlock->layout,
                        ]
                    );

                }
                $webpage->refresh();
                if ($webpage->is_dirty) {
                    PublishWebpage::make()->action(
                        $webpage,
                        [
                            'comment' => 'publish after upgrade',
                        ]
                    );
                }
            }

        }


    }




    public function getCommandSignature(): string
    {
        return 'catalogue:clone_webpages {from_type} {from} {to}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('to'))->firstOrFail();
        if ($command->argument('from_type') == 'shop') {
            $fromShop = Shop::where('slug', $command->argument('from'))->firstOrFail();
        } else {
            $fromShop = MasterShop::where('slug', $command->argument('from'))->firstOrFail();
        }
        $this->handle($fromShop, $shop);

        return 0;
    }


}
