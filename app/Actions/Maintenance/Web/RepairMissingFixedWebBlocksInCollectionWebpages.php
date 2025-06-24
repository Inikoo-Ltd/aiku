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
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
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
            $this->createWebBlock($webpage, 'families-1', $collection);
        }

        $countFamilyWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');
        if (count($countFamilyWebBlock) == 0) {
            $this->createWebBlock($webpage, 'products-1', $collection);
        }


        $webpage->refresh();
        UpdateWebpageContent::run($webpage);
        foreach ($webpage->webBlocks as $webBlock) {
            print $webBlock->webBlockType->code."\n";
        }
        print "=========\n";


        if ($webpage->is_dirty) {
            if (in_array($collection->state, [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
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
