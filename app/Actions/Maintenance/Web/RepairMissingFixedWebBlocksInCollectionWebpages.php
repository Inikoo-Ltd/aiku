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
        $website = $webpage->website;

        // FIX FOR DUPLICATED FAMILIES WEBBLOCK UNDER COLLECTION
        $liveFamiliesSnapshot = $website->liveFamilySnapshot;
        $unpublishedFamiliesSnapshot = $website->unpublishedFamilySnapshot;

        $usedFamiliesTemplate = data_get($liveFamiliesSnapshot?->layout, 'code', data_get($unpublishedFamiliesSnapshot?->layout, 'code', null)); // Get published Families layout code

        if ($usedFamiliesTemplate) {
            $unusedFamiliesTemplate = array_filter(
                ['families-1', 'families-2', 'families-3'],
                fn ($family) => $family != $usedFamiliesTemplate
            );

            $countFamiliesWebBlock = $this->getWebpageBlocksByType($webpage, $usedFamiliesTemplate);
            if (count($countFamiliesWebBlock) == 0) {
                $this->createWebBlock($webpage, $usedFamiliesTemplate);
            }

            // REMOVE DUPLICATED FAMILIES WEBBLOCK UNDER COLLECTION
            foreach ($unusedFamiliesTemplate as $unusedFamiliesCode) {
                $unusedFamiliesWebBlock = $this->getWebpageBlocksByType($webpage, $unusedFamiliesCode);
                if (count($unusedFamiliesWebBlock) > 0) {
                    $webpage
                        ->modelHasWebBlocks()
                        ->whereIn('id', $unusedFamiliesWebBlock->pluck('model_has_web_blocks_id'))
                        ->delete();

                    $webpage
                        ->webBlocks()
                        ->whereIn('web_blocks.id', $unusedFamiliesWebBlock->pluck('id'))
                        ->delete();

                }
            }
        }

        $countProductsWebBlock = $this->getWebpageBlocksByType($webpage, 'products-1');
        if (count($countProductsWebBlock) == 0) {
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


        if ($webpage->is_dirty && $collection) {
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
        $webpagesID = DB::table('webpages')
        ->select('id')
        ->where('model_type', 'Collection')
        ->where('slug', 'mothers-day-awd-1')
        ->get();


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}
