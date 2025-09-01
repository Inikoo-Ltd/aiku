<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 30 Jun 2025 11:11:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class WebpageWorkshopCheckWebBlock extends OrgAction
{
    use WithActionUpdate;


    public function handle(Webpage $webpage, array $modelData): array
    {
        $webBlocks = Arr::get($modelData, 'layout.web_blocks');
        $webBlocksChanged = false;

        if (empty($webBlocks)) {
            $webpage->modelHasWebBlocks()->delete();
            $webBlocksChanged = true;
        } else {
            $frontendIds = $this->collectFrontendIds($webBlocks);
            $webBlocksChanged |= $this->removeObsoleteBlocks($webpage, $frontendIds);
            
            foreach ($webBlocks as $index => $webBlockData) {
                $webBlocksChanged |= $this->processWebBlock($webpage, $webBlockData, $index);
            }
        }

        $webpage->refresh();
        if ($webBlocksChanged) {
            UpdateWebpageContent::run($webpage);
        }
        $webpage->refresh();
        
        return $webpage->unpublishedSnapshot->layout;
    }

    private function collectFrontendIds(array $webBlocks): array
    {
        $ids = [];
        foreach ($webBlocks as $webBlockData) {
            $modelHasWebBlockId = Arr::get($webBlockData, 'id');
            if ($modelHasWebBlockId) {
                $ids[] = $modelHasWebBlockId;
            }
        }
        return $ids;
    }

    private function removeObsoleteBlocks(Webpage $webpage, array $frontendIds): bool
    {
        if (empty($frontendIds)) {
            return false;
        }
        
        $deletedCount = $webpage->modelHasWebBlocks()
            ->whereNotIn('id', $frontendIds)
            ->delete();
            
        return $deletedCount > 0;
    }

    private function processWebBlock(Webpage $webpage, array $webBlockData, int $index): bool
    {
        $modelHasWebBlockId = Arr::get($webBlockData, 'id');
        $webBlockId = Arr::get($webBlockData, 'web_block.id');
        
        $existingWebBlock = WebBlock::where('id', $webBlockId)->first();
        $existingModelHasWebBlock = ModelHasWebBlocks::where('id', $modelHasWebBlockId)->first();

        // Create new block if neither exists
        if (!$existingWebBlock && !$existingModelHasWebBlock) {
            return $this->createNewWebBlock($webpage, $webBlockData, $index);
        }
        
        // Update existing block layout if needed
        return $this->updateWebBlockLayout($existingWebBlock, $webBlockData);
    }

    private function createNewWebBlock(Webpage $webpage, array $webBlockData, int $index): bool
    {
        $webBlockType = WebBlockType::where('code', $webBlockData['type'])->first();
        
        if (!$webBlockType) {
            return false;
        }
        
        StoreModelHasWebBlock::make()->action($webpage, [
            'web_block_type_id' => $webBlockType->id,
            'layout' => Arr::get($webBlockData, 'web_block.layout', []),
            'position' => $index
        ]);
        
        return true;
    }

    private function updateWebBlockLayout(?WebBlock $existingWebBlock, array $webBlockData): bool
    {
        if (!$existingWebBlock) {
            return false;
        }
        
        $newLayout = Arr::get($webBlockData, 'web_block.layout', []);
        
        if ($existingWebBlock->layout !== $newLayout) {
            $existingWebBlock->update(['layout' => $newLayout]);
            return true;
        }
        
        return false;
    }


    public function rules()
    {
        return [
            'layout' => ['sometimes', 'array'],
        ];
    }

    public function action(Webpage $webpage, array $modelData): array
    {
        return $this->handle($webpage, $modelData);
    }

    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->initialisation($webpage->organisation, $request);

        return $this->handle($webpage, $this->validatedData);
    }

}
