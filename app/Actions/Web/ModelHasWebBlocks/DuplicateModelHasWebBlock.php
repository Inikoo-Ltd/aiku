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


    public function handle(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlocks, array $modelData = []): ModelHasWebBlocks
    {
        $orderedBlocks = $webpage->modelHasWebBlocks()->orderBy('position')->orderBy('id')->get();
        $position      = min(Arr::get($modelData, 'position') ?? $orderedBlocks->count(), $orderedBlocks->count());

        /** @var ModelHasWebBlocks $block */
        foreach ($orderedBlocks->values() as $index => $block) {
            $newPosition = $index < $position ? $index : $index + 1;
            if ((int) $block->position !== $newPosition) {
                $block->update(['position' => $newPosition]);
            }
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
                'model_id'        => $webpage->id,
                'model_type'      => class_basename(Webpage::class),
                'web_block_id'    => $webBlock->id,
                'show'            => $modelHasWebBlocks->show,
                'show_logged_in'  => $modelHasWebBlocks->show_logged_in,
                'show_logged_out' => $modelHasWebBlocks->show_logged_out,
            ]
        );
        UpdateWebpageContent::run($webpage->refresh());

        return $modelHasWebBlockCopy;
    }

    public function rules(): array
    {
        return [
            'position' => ['sometimes', 'nullable', 'integer', 'min:0'],
        ];
    }

    public function asController(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlock, ActionRequest $request): void
    {
        abort_if($modelHasWebBlock->group_id !== $webpage->group_id, 404);

        $this->initialisationFromShop($webpage->shop, $request);
        $this->handle($webpage, $modelHasWebBlock, $this->validatedData);
    }

    public function action(Webpage $webpage, ModelHasWebBlocks $modelHasWebBlock, array $modelData = []): ModelHasWebBlocks
    {
        $this->asAction = true;

        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $modelHasWebBlock, $this->validatedData);
    }
}
