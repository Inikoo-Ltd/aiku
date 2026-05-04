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
use App\Enums\Web\WebBlockType\WebBlockTemplateEnum;
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

        // FIX FOR DUPLICATED FAMILIES WEBBLOCK UNDER COLLECTION
        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::FAMILIES->templateCodes(), WebBlockTemplateEnum::FAMILIES);

        // FIX FOR DUPLICATED PRODUCTS WEBBLOCK UNDER COLLECTION
        $this->normalizeWebBlockByType($webpage, WebBlockTemplateEnum::LIST_PRODUCTS->templateCodes(), WebBlockTemplateEnum::LIST_PRODUCTS);

        $this->deleteWebBlocksByType($webpage, WebBlockTemplateEnum::SUB_DEPARTMENTS);

        $countCollectionDescriptionBlock = $this->getWebpageBlocksByType($webpage, 'collection-description-1');
        if (count($countCollectionDescriptionBlock) == 0) {
            $this->createWebBlock($webpage, 'collection-description-1');
        }

        $webpage->refresh();
        if ($command->option('set-description-top')) {
            $this->setDescriptionWebBlockOnTop($webpage);
        }

        if ($command->option('hide-description')) {
            $this->setDescriptionWebBlockHidden($webpage);
        }
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

    public function setDescriptionWebBlockHidden(Webpage $webpage): void
    {
        $collectionDescriptionWebBlock = $this->getWebpageBlocksByType($webpage, 'collection-description-1')->first();

        if ($collectionDescriptionWebBlock) {
            DB::table('model_has_web_blocks')
                ->where('id', $collectionDescriptionWebBlock->model_has_web_blocks_id)
                ->update(['show' => false]);
        }

        UpdateWebpageContent::run($webpage);
    }

    public string $commandSignature = 'repair:missing_fixed_web_blocks_in_collections_webpages {--website_id=} {--webpage_slug=} {--set-description-top} {--hide-description}';

    public function asCommand(Command $command): void
    {
        $websiteId      = $command->option('website_id');
        $webpageSlug      = $command->option('webpage_slug');
        if ($webpageSlug) {
            $webpagesID     = DB::table('webpages')
                ->where('slug', $webpageSlug)
                ->select('id')
                ->where('model_type', 'Collection')
                ->get();
        } else {
            $webpagesID     = DB::table('webpages')
                ->when(
                    !empty($websiteId),
                    fn ($q) => $q->where('website_id', $websiteId)
                )
                ->select('id')
                ->where('model_type', 'Collection')
                ->get();
        }


        foreach ($webpagesID as $webpageID) {
            $webpage = Webpage::find($webpageID->id);
            if ($webpage) {
                $this->handle($webpage, $command);
            }
        }
    }

}
