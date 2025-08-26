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
        $webBlocksChanged = false;
        
        $webBlocks = Arr::get($modelData, 'layout.web_blocks');

        if ($webBlocks) {
            foreach ($webBlocks as $index => $webBlockData) {
                $frontendId = Arr::get($webBlockData, 'web_block.id');
                
                $existingWebBlock = WebBlock::where('id', $frontendId)->first();
                
                if (!$existingWebBlock) {
                    $webBlockType = WebBlockType::where('code', $webBlockData['type'])->first();
                    
                    if ($webBlockType) {
                        StoreModelHasWebBlock::make()->action($webpage, [
                            'web_block_type_id' => $webBlockType->id,
                            'layout' => Arr::get($webBlockData, 'web_block.layout', []),
                            'position' => $index
                        ]);
                        $webBlocksChanged = true;
                    }
                } else {
                    $newLayout = Arr::get($webBlockData, 'web_block.layout', []);
                    
                    if ($existingWebBlock->layout !== $newLayout) {
                        $existingWebBlock->update(['layout' => $newLayout]);
                        $webBlocksChanged = true;
                    }   
                }
            }
        }
        $webpage->refresh();

        if ($webBlocksChanged) {
            UpdateWebpageContent::run($webpage);
        }
        $webpage->refresh();
        return $webpage->unpublishedSnapshot->layout;
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
