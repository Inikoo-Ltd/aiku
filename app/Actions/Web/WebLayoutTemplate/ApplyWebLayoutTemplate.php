<?php

/*
 * author Louis Perez
 * created on 25-02-2026-09h-55m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Enums\Web\WebBlockType\WebBlockSystemEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlock;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class ApplyWebLayoutTemplate extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithStoreWebpage;

    public function handle(Webpage|WebBlock $parent, WebLayoutTemplate $template, array $modelData): Webpage|WebBlock
    {
        // TODO MOVE INTO TRAIT (?) OR JUST TIDY THIS UP
        if ($parent instanceof Webpage) {
            $this->hydrateMissingWebBlockFromTemplate($parent, $template);
            $this->updateWebBlockFromTemplate($parent, $template);
            $this->normalizeAndReorderWebBlock($parent, $template);
        }

        return $parent;
    }

    public function normalizeAndReorderWebBlock(Webpage $webpage, WebLayoutTemplate $template)
    {
        $positions = $webpage->webBlocks()->with('webBlockType:id,slug')->get()->mapWithKeys(function ($item) use ($template) {
            return [ $item->id => [
                'position' => data_get($template->data, "orders.{$item->webBlockType->slug}.position")
                ]
            ];
        })->toArray();
        ReorderWebBlocks::make()->action($webpage, ['positions' => $positions]);
    }

    public function updateWebBlockFromTemplate(Webpage $webpage, WebLayoutTemplate $template)
    {
        $currentWebBlockList = $webpage->webBlocks()->with('webBlockType:id,slug')->get()->keyBy('webBlockType.slug');

        foreach ($currentWebBlockList as $key => $webBlock) {
            if (in_array($key, WebBlockSystemEnum::listSystemWebBlock())) {
                continue;
            }
            $templateLayout = data_get($template->data, "web_blocks.{$key}.layout");
            if ($key == 'luigi-item-alternatives-1' || $key == 'luigi-last-seen-1') {
                // Handles luigi identity which differents on each webpages
                $identity = "$webpage->group_id:$webpage->organisation_id:$webpage->shop_id:{$webpage->website->id}:$webpage->id";
                data_set($templateLayout, 'data.fieldValue.product.luigi_identity', $identity);
            }
            UpdateModelHasWebBlocks::make()->action(ModelHasWebBlocks::find($webBlock->pivot->id), ['layout' => $templateLayout]);
        }

    }

    public function hydrateMissingWebBlockFromTemplate(Webpage $webpage, WebLayoutTemplate $template): void
    {
        $currentWebBlockList = $webpage->webBlocks()->with('webBlockType:id,slug')->get()->keyBy('webBlockType.slug');

        // Check & hydrate missing web blocks
        $missingWebBlock = array_diff(array_keys(data_get($template->data, 'web_blocks', [])), array_keys($currentWebBlockList->toArray()));
        foreach ($missingWebBlock as $code) {
            $this->createWebBlock($webpage, $code);
        }
    }

    public function asController(Webpage $webpage, WebLayoutTemplate $template, ActionRequest $request): Webpage|WebBlock
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $template, $this->validatedData);
    }
}
