<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlock;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInProductsWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'Product') {
            $this->processProductWebpages($webpage, $command);
        }
    }

    protected function processProductWebpages(Webpage $webpage, Command $command): void
    {
        $webBlocksDataOldComponent = $this->getWebpageBlocksByType($webpage, 'product');
        $oldWebBlocksCount         = count($webBlocksDataOldComponent);

        /** @var Product $product */
        $product = $webpage->model;

        if (!$product->is_main) {
            print "Product is not main product, skipping\n";
            // Delete Webpage
            return;
        }


        if ($oldWebBlocksCount > 0) {
            if ($webpage->allow_fetch) {
                $layout = json_decode($webBlocksDataOldComponent[0]->layout, true);
                $description = Arr::get($layout, 'data.fieldValue.value.text');

                if ($description) {
                    $product->update(['description' => $description]);
                }
            }
        }

        foreach ($webBlocksDataOldComponent as $webBlockData) {
            $command->error('Webpage '.$webpage->code.' Deleting Old Product Web Blocks');

            DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
            DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();
            DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
            DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();
            DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
        }

        $webBlocksDataNew = $this->getWebpageBlocksByType($webpage, 'product-1');
        if (count($webBlocksDataNew) == 0) {
            $command->error('Webpage '.$webpage->code.' Product Web Block not found');

            $this->createWebBlock($webpage, 'product-1');
        }



        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'see-also-1');
        if (count($countFamilyWebBlock) > 0) {
            foreach ($countFamilyWebBlock as $webBlockData) {
                DB::table('model_has_web_blocks')->where('id', $webBlockData->model_has_web_blocks_id)->delete();
                DB::table('model_has_web_blocks')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('model_has_media')->where('model_type', 'WebBlock')->where('model_id', $webBlockData->id)->delete();
                DB::table('web_block_has_models')->where('web_block_id', $webBlockData->id)->delete();

                DB::table('web_blocks')->where('id', $webBlockData->id)->delete();
            };
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'luigi-trends-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'luigi-last-seen-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-item-alternatives-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'luigi-item-alternatives-1');
        }



        $webpage->refresh();

        $this->setProductWebBlockOnTop($webpage);
        $webpage->refresh();

        UpdateWebpageContent::run($webpage);


        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";

        if ($webpage->is_dirty) {
            if (in_array($product->state, [
                ProductStateEnum::ACTIVE,
                ProductStateEnum::DISCONTINUING
            ])) {
                $command->line($webpage->website->domain.' '.'Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'publish after upgrade',
                    ]
                );
            }
        }
    }

    public function setProductWebBlockOnTop(Webpage $webpage): void
    {
        $familyWebBlock = $this->getWebpageBlocksByType($webpage, 'product-1')->first()->model_has_web_blocks_id;


        $trendsWebBlock   = $this->getWebpageBlocksByType($webpage, 'luigi-trends-1')->first()->model_has_web_blocks_id;
        $lastSeenWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-last-seen-1')->first()->model_has_web_blocks_id;

        /** @var WebBlock $alternativesWebBlock */
        $alternativesWebBlock = $this->getWebpageBlocksByType($webpage, 'luigi-item-alternatives-1')->first();
        if($alternativesWebBlock){
            $alternativesWebBlock=$alternativesWebBlock->model_has_web_blocks_id;
        }




        $webBlocks = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();

        $count = $webpage->webBlocks()->count();

        $trendsWebBlockPosition = $count + 101;
        $lastSeenWebBlockPosition       = $count + 102;
        $alternativesWebBlockPosition= $count + 103;


        $runningPosition = 2;
        foreach ($webBlocks as $key => $position) {
            if ($key == $familyWebBlock) {
                $webBlocks[$key] = 1;
            } elseif ($key == $trendsWebBlock) {
                $webBlocks[$key] = $trendsWebBlockPosition;
            } elseif ($key == $lastSeenWebBlock) {
                $webBlocks[$key] = $lastSeenWebBlockPosition;
            } elseif ($key == $alternativesWebBlock) {
                $webBlocks[$key] = $alternativesWebBlockPosition;
            } else {
                $webBlocks[$key] = $runningPosition;
                $runningPosition++;
            }
        }


        foreach ($webBlocks as $key => $position) {
            DB::table('model_has_web_blocks')
                ->where('id', $key)
                ->update(['position' => $position]);
        }
        UpdateWebpageContent::run($webpage);
    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_products_webpages {--website_id=} {--webpage_id=}';

    public function asCommand(Command $command): void
    {
        $websiteId = $command->option('website_id');
        $singleWebpageId = $command->option('webpage_id');

        $query = DB::table('webpages')
            ->select('id')
            ->where('model_type', 'Product')
            ->orderBy('id');

        if ($singleWebpageId) {
            $query->where('id', $singleWebpageId);
        }

        if ($websiteId) {
            $query->where('website_id', $websiteId);
        }

        $total = $query->count();
        $current = 0;

        $query->chunk(100, function ($webpagesID) use ($command, &$current, $total) {
            foreach ($webpagesID as $webpageID) {
                $current++;
                print "[{$current}/{$total}] Webpage id: {$webpageID->id}\n";
                $webpage = Webpage::find($webpageID->id);
                if ($webpage) {
                    $this->handle($webpage, $command);
                }
            }
        });
    }

}
