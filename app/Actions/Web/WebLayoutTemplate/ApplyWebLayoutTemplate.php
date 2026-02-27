<?php

/*
 * author Louis Perez
 * created on 25-02-2026-09h-55m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\Helpers\Snapshot\UpdateSnapshot;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\ModelHasWebBlocks\UpdateModelHasWebBlocks;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\WebBlockType\WebBlockSystemEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ApplyWebLayoutTemplate extends OrgAction
{
    use WithWebEditAuthorisation;
    use WithStoreWebpage;

    public function handle(Webpage|WebBlock $parent, WebLayoutTemplate $template, array $modelData): Webpage|WebBlock
    {
        // TODO MOVE INTO TRAIT (?) OR JUST TIDY THIS UP
        if ($parent instanceof Webpage) {
            $this->labeledSaveCurrentLiveSnapshot($parent, $template);
            $this->reHydrateMissingWebBlock($parent, $template);
        }

        return $parent;
    }

    public function reHydrateMissingWebBlock(Webpage $webpage, WebLayoutTemplate $template): void
    {
        $webBlockTypes = WebBlockType::whereIn('slug', data_get($template->data, 'web_blocks.*.type', []))->get()->keyBy('slug')->toArray();
        $listSystemWebBlock = WebBlockSystemEnum::listSystemWebBlock();
        // dd(data_get($template->data, 'web_blocks', []));

        foreach (data_get($template->data, 'web_blocks', []) as $index => $webBlockFromLayout) {
            if (!in_array(data_get($webBlockFromLayout, 'type'), $listSystemWebBlock)) {
                $this->createWebBlock($webpage, data_get($webBlockFromLayout, 'type'));
            } else {
                StoreModelHasWebBlock::make()->action($webpage, [
                    'web_block_type_id' => data_get($webBlockTypes, "{$webBlockFromLayout['type']}.id"),
                    'layout' => Arr::get($webBlockFromLayout, 'layout', []),
                    'position' => $index
                ]);
            }
        }

        PublishWebpage::make()->action($webpage, [
            'comment' => 'Snapshot published'
        ]);
    }

    public function labeledSaveCurrentLiveSnapshot(Webpage $webpage, WebLayoutTemplate $template): void
    {
        UpdateSnapshot::run($webpage->liveSnapshot, [
            'label'           => "Before template update | {$template->label}",
            'state'           => SnapshotStateEnum::HISTORIC,
            'published_until' => now()
        ]);

        foreach ($webpage->modelHasWebBlocks as $webBlock) {
            $webBlock->delete();
        }
    }

    public function asController(Webpage $webpage, WebLayoutTemplate $template, ActionRequest $request): Webpage|WebBlock
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $template, $this->validatedData);
    }
}
