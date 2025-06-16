<?php

/*
 * author Arya Permana - Kirin
 * created on 02-06-2025-16h-33m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class DuplicateModelHasWebBlock extends OrgAction
{
    use WithWebAuthorisation;
    use WithActionUpdate;


    public function handle(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlocks): ModelHasWebBlocks
    {
        $position = null;
        if ($modelHasWebBlocks->webpage_id == $webpage->id) {
            $position = $modelHasWebBlocks->position + 1;
        } else {
            $position = Arr::pull($modelData, 'position', $webpage->modelHasWebBlocks()->max('position') + 1);
        }
        $webBlocks = $webpage->modelHasWebBlocks()->orderBy('position')->get();

        if (!$webBlocks->isEmpty()) {
            $positions = [];

            /** @var ModelHasWebBlocks $block */
            foreach ($webBlocks as $block) {
                if ($block->position >= $position) {
                    $positions[$block->webBlock->id] = ['position' => $block->position + 1];
                }
            }

            ReorderWebBlocks::make()->action($webpage, ['positions' => $positions]);
        }

        $webBlockType = WebBlockType::find($modelHasWebBlocks->webBlock->web_block_type_id);

        $webBlock = StoreWebBlock::run($webBlockType, [
            'layout' => $modelHasWebBlocks->webBlock->layout,
        ]);
        /** @var ModelHasWebBlocks $modelHasWebBlockCopy */
        $modelHasWebBlockCopy = $webpage->modelHasWebBlocks()->create(
            [
                'group_id'        => $webpage->group_id,
                'organisation_id' => $webpage->organisation_id,
                'shop_id'         => $webpage->shop_id,
                'website_id'      => $webpage->website_id,
                'webpage_id'      => $webpage->id,
                'position'        => $position,
                'model_id'        => $modelHasWebBlocks->model_id,
                'model_type'      => $modelHasWebBlocks->model_type,
                'web_block_id'    => $webBlock->id,
                'show'            => $modelHasWebBlocks->show,
                'show_logged_in'  => $modelHasWebBlocks->show_logged_in,
                'show_logged_out'  => $modelHasWebBlocks->show_logged_out,
            ]
        );
        UpdateWebpageContent::run($webpage->refresh());

        return $modelHasWebBlockCopy;
    }

    public function asController(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): void
    {
        $this->initialisationFromShop($webpage->shop, $request);
        $this->handle($webpage, $modelHasWebBlocks);
    }
}
