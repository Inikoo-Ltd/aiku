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


        $webpage->refresh();
        UpdateWebpageContent::run($webpage);


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

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_products_webpages';

    public function asCommand(Command $command): void
    {
        // Process webpages in chunks to save memory
        DB::table('webpages')
            ->select('id')
            ->where('model_type', 'Product')
            ->orderBy('id')
            ->chunk(100, function ($webpagesID) use ($command) {
                foreach ($webpagesID as $webpageID) {
                    $webpage = Webpage::find($webpageID->id);
                    if ($webpage) {
                        $this->handle($webpage, $command);
                    }
                }
            });
    }

}
