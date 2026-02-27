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
        // TODO 1. MOVE INTO TRAIT (?) OR JUST TIDY THIS UP
        // TODO 2. NEED TO ADD TEMPLATE FOR WEBBLOCK NEXT (?)
        if ($parent instanceof Webpage) {
            $this->applyWebpageLayoutTemplate($parent, $template);
        }

        return $parent;
    }

    public function applyWebpageLayoutTemplate(Webpage $webpage, WebLayoutTemplate $template): void
    {
        UpdateSnapshot::run($webpage->liveSnapshot, [
            'label'           => "Before template update | {$template->label}",
            'state'           => SnapshotStateEnum::HISTORIC,
            'published_until' => now()
        ]);

        foreach ($webpage->modelHasWebBlocks as $webBlock) {
            $webBlock->delete();
        }

        // To help understand 
        // 1. Fetch all webBlockType since it will be used for looping
        // 2. Fetch all webBlockType that is marked as system type (generated using template) chosen on Website
        $webBlockTypes = WebBlockType::whereIn('slug', data_get($template->data, 'web_blocks.*.type', []))->get()->keyBy('slug')->toArray();
        $listSystemWebBlock = WebBlockSystemEnum::listSystemWebBlock();

        foreach (data_get($template->data, 'web_blocks', []) as $index => $webBlockFromLayout) {
            // 3. Check if its a system-type webBlock, createWebBlock using WithStoreWebpage trait
            if (!in_array(data_get($webBlockFromLayout, 'type'), $listSystemWebBlock)) {
                $this->createWebBlock($webpage, data_get($webBlockFromLayout, 'type'));
            // 4. If it isn't, well, use same logic as we have on SetSnapshotAsLive
            } else {
                StoreModelHasWebBlock::make()->action($webpage, [
                    'web_block_type_id' => data_get($webBlockTypes, "{$webBlockFromLayout['type']}.id"),
                    'layout' => Arr::get($webBlockFromLayout, 'layout', []),
                    'position' => $index
                ]);
            }
        }

        // TODO consider publishing after apply template or just make new snapshot w/o publish. Idk. This is honestly the dangerous part
        // But without PublishWebpage, current webpage would probably crash since we deleted their webblock previously
        PublishWebpage::make()->action($webpage, [
            'comment' => "Made snapshot after applying template {$template->label}"
        ]);
    }

    public function asController(Webpage $webpage, WebLayoutTemplate $template, ActionRequest $request): Webpage|WebBlock
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $template, $this->validatedData);
    }
}
