<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 09:47:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Web;

use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairMissingFixedWebBlocksInCollectionWebpages
{
    use WithActionUpdate;
    use WithRepairWebpages;


    protected function handle(Webpage $webpage, Command $command): void
    {
        $this->processDepartmentWebpages($webpage, $command);
    }


    protected function processDepartmentWebpages(Webpage $webpage, Command $command): void
    {
        /** @var \App\Models\Catalogue\Collection $collection */
        $collection = $webpage->model;


        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'families-1');

        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'families-1');
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'products-1');
        }

        $countCollectionDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'collection-description-1');
        if (count($countCollectionDescriptionBlock) == 0) {
            $this->createWebBlock($webpage, 'collection-description-1');
        }

        $webpage->refresh();
        $this->setDescriptionWebBlockOnTop($webpage);
        $webpage->refresh();

        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";


        if ($webpage->is_dirty) {
            if (in_array($collection->state, [
                CollectionStateEnum::ACTIVE,
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


    public function setDescriptionWebBlockOnTop(Webpage $webpage): void
    {
        $collectionDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'collection-description-1')->first()->model_has_web_blocks_id;
        $webBlocks                     = $webpage->webBlocks()->pluck('position', 'model_has_web_blocks.id')->toArray();


        $runningPosition = 2;
        foreach ($webBlocks as $key => $position) {
            if ($key == $collectionDescriptionWebBlock) {
                $webBlocks[$key] = 1;
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


    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_collections_webpages';

    public function asCommand(Command $command): void
    {
        $webpagesID = DB::table('webpages')->select('id')->where('model_type', 'Collection')->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}
