<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInFamiliesWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        if ($webpage->model_type == 'ProductCategory') {
            /** @var ProductCategory $model */
            $model = $webpage->model;
            if ($model->type == ProductCategoryTypeEnum::FAMILY) {
                $this->processFamilyWebpages($webpage, $command);
            }
        }
    }

    protected function getWebpageBlocksByType(Webpage $webpage, string $type): \Illuminate\Support\Collection
    {
        return DB::table('model_has_web_blocks')
            ->select(['web_blocks.layout', 'web_blocks.id', 'web_block_types.code as type'])
            ->leftJoin('web_blocks', 'web_blocks.id', '=', 'model_has_web_blocks.web_block_id')
            ->leftJoin('web_block_types', 'web_block_types.id', '=', 'web_blocks.web_block_type_id')
            ->where('web_block_types.code', $type)
            ->where('model_has_web_blocks.model_type', 'Webpage')
            ->where('model_has_web_blocks.model_id', $webpage->id)->get();
    }


    protected function processFamilyWebpages(Webpage $webpage, Command $command): void
    {
        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'family');

        if ($countFamilyWebBlock == 0) {
            $command->error('Webpage '.$webpage->code.' Family Web Block not found');
        } elseif ($countFamilyWebBlock > 1) {
            $command->v('Webpage '.$webpage->code.' MORE than 1 Family Web Block found');
        } else {
            $command->info('Webpage '.$webpage->code.' Family Web Block found');
        }
    }

    protected function processProductWebpages(Webpage $webpage, Command $command): void
    {
        $webBlocksData  = $this->getWebpageBlocksByType($webpage, 'products-1');
        $WebBlocksCount = count($webBlocksData);

        /** @var ProductCategory $family */
        $family = $webpage->model;



        if ($WebBlocksCount == 0) {
            $webBlocksDataNew = $this->getWebpageBlocksByType($webpage, 'products-1');
            if (count($webBlocksDataNew) == 0) {
                $command->error('Webpage '.$webpage->code.' Product Web Block not found');

                $this->createWebBlock($webpage, 'product-1',$family);

            }
        } elseif ($WebBlocksCount > 1) {
            $command->error('Webpage '.$webpage->code.' More than one products-1 Web Block  found');
        } else {
            $layout      = json_decode($webBlocksData[0]->layout, true);
            $description = Arr::get($layout, 'data.fieldValue.value.text');

            if ($description) {
                //$command->line('P: '.$product->id.' Product description updated');
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
    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_catalogue_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->whereIn('model_type', ['Product', 'ProductCategory', 'Collection'])->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            $this->handle($webpage, $command);
        }
    }

}
