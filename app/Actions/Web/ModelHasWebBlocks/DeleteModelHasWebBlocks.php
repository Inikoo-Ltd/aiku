<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 13:18:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Lorisleiva\Actions\ActionRequest;

class DeleteModelHasWebBlocks extends OrgAction
{
    use WithWebEditAuthorisation;


    public function handle(ModelHasWebBlocks $modelHasWebBlocks): ModelHasWebBlocks
    {
        $webpage = $modelHasWebBlocks->webpage;
        $modelHasWebBlocks->delete();

        $webBlockUsed = ModelHasWebBlocks::where('web_block_id', $modelHasWebBlocks->web_block_id)->count();
        if ($webBlockUsed === 0) {
            $modelHasWebBlocks->webBlock()->delete();
        }

        $webpage->refresh();

        $webBlocks = $webpage->modelHasWebBlocks()->orderBy('position')->get();

        if (!$webBlocks->isEmpty()) {
            $positions = [];
            foreach ($webBlocks as $index => $block) {
                $positions[$block->webBlock->id] = ['position' => $index];
            }

            ReorderWebBlocks::make()->action($webpage, ['positions' => $positions]);
        }
        UpdateWebpageContent::run($modelHasWebBlocks->webpage);

        return $modelHasWebBlocks;
    }

    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): void
    {
        $this->initialisationFromShop($modelHasWebBlocks->shop, $request);
        $this->handle($modelHasWebBlocks);
    }

    public function action(ModelHasWebBlocks $modelHasWebBlocks, array $modelData): ModelHasWebBlocks
    {
        $this->asAction = true;

        $this->initialisationFromShop($modelHasWebBlocks->shop, $modelData);

        return $this->handle($modelHasWebBlocks);
    }
}
