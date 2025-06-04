<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
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
        $webBlocksData  = $this->getWebpageBlocksByType($webpage, 'product');
        $WebBlocksCount = count($webBlocksData);

        /** @var Product $product */
        $product = $webpage->model;

        if (!$product->is_main) {
            // Delete Webpage
            return;
        }


        if ($WebBlocksCount == 0) {
            $webBlocksDataNew = $this->getWebpageBlocksByType($webpage, 'product-1');
            if (count($webBlocksDataNew) == 0) {
                $command->error('Webpage '.$webpage->code.' Product Web Block not found');

                $this->createWebBlock($webpage, 'product-1', $product);
            }
        } elseif ($WebBlocksCount > 1) {
            $command->error('Webpage '.$webpage->code.' More than oneProduct Web Block  found');
        } else {
            $layout      = json_decode($webBlocksData[0]->layout, true);
            $description = Arr::get($layout, 'data.fieldValue.value.text');

            if ($description) {
                $product->update(['description' => $description]);
            }

            $webBlockType = WebBlockType::where('code', 'product-1')->first();

            $webBlock = WebBlock::find($webBlocksData[0]->id);

            $newLayout = [];
            data_set($newLayout, 'data.fieldValue', Arr::get($webBlockType->data, 'fieldValue', []));
            $webBlockUpdateData = [
                'web_block_type_id' => $webBlockType->id,
                'layout'            => $newLayout
            ];


            $webBlock->update(
                $webBlockUpdateData
            );
        }

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
                $command->line('Webpage '.$webpage->code.' is dirty, publishing after upgrade');
                PublishWebpage::make()->action(
                    $webpage,
                    [
                        'comment' => 'publish after upgrade',
                    ]
                );
            }
        }

    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_products_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('model_type', 'Product')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}
