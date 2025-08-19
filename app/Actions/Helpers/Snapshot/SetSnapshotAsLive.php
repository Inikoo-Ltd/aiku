<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Oct 2023 08:55:05 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Snapshot;

use App\Actions\Helpers\Deployment\StoreDeployment;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\ModelHasContent\DeleteModelHasContent;
use App\Actions\Web\ModelHasWebBlocks\DeleteModelHasWebBlocks;
use App\Actions\Web\ModelHasWebBlocks\StoreModelHasWebBlock;
use App\Actions\Web\Slide\StoreSlide;
use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Actions\Web\Webpage\BreakWebpageCache;
use App\Actions\Web\Webpage\PublishWebpage;
use App\Actions\Web\Webpage\ReindexWebpageLuigiData;
use App\Enums\Helpers\Snapshot\SnapshotBuilderEnum;
use App\Enums\Helpers\Snapshot\SnapshotScopeEnum;
use App\Enums\Helpers\Snapshot\SnapshotStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Helpers\Snapshot;
use App\Models\Web\Banner;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class SetSnapshotAsLive extends OrgAction
{
    use WithWebEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(Webpage $webpage, Snapshot $snapshot)
    {
        foreach ($webpage->modelHasWebBlocks as $webBlock) {
            $webBlock->delete();
        }

        $layout = $snapshot->layout;

        if (isset($layout['web_blocks']) && is_array($layout['web_blocks'])) {
            foreach ($layout['web_blocks'] as $index => $webBlockData) {
                $webBlockType = WebBlockType::where('code', Arr::get($webBlockData, 'type'))->first();
                StoreModelHasWebBlock::make()->action($webpage, [
                    'web_block_type_id' => $webBlockType->id,
                    'layout' => Arr::get($webBlockData, 'web_block.layout', []),
                    'position' => $index
                ]);
            }
        }

        PublishWebpage::make()->action($webpage, [
            'comment' => 'Snapshot published'
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function action(Webpage $webpage, Snapshot $snapshot)
    {
        $this->asAction       = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage, $snapshot);
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->set('publisher_id', $request->user()->id);
        $this->set('publisher_type', class_basename($request->user()));
    }

    public function asController(Webpage $webpage, Snapshot $snapshot, ActionRequest $request)
    {
        $this->initialisationFromShop($webpage->shop, $request);
        return $this->handle($webpage, $snapshot);
    }
}
