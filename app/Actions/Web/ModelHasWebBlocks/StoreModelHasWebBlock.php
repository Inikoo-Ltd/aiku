<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 12:45:05 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Actions\Web\Webpage\ReorderWebBlocks;
use App\Actions\Web\Webpage\UpdateWebpageContent;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Events\BroadcastPreviewWebpage;
use App\Http\Resources\Web\WebpageResource;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreModelHasWebBlock extends OrgAction
{
    use WithWebAuthorisation;


    private Webpage $webpage;

    public function handle(Webpage $webpage, array $modelData): ModelHasWebBlocks
    {
        $position = Arr::pull($modelData, 'position', $webpage->modelHasWebBlocks()->max('position') + 1);
        $webBlocks = $webpage->modelHasWebBlocks()->orderBy('position')->get();

        if (!$webBlocks->isEmpty()) {
            $positions = [];

            foreach ($webBlocks as $block) {
                if ($block->position >= $position) {
                    $positions[$block->webBlock->id] = ['position' => $block->position + 1];
                }
            }

            ReorderWebBlocks::make()->action($webpage, ['positions' => $positions]);
        }

        $webBlockType = WebBlockType::find($modelData['web_block_type_id']);

        $webBlock = StoreWebBlock::run($webBlockType, $modelData);
        /** @var ModelHasWebBlocks $modelHasWebBlock */
        $modelHasWebBlock = $webpage->modelHasWebBlocks()->create(
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
            ]
        );
        UpdateWebpageContent::run($webpage->refresh());
        /*   BroadcastPreviewWebpage::dispatch($webpage); */

        return $modelHasWebBlock;
    }

    public function rules(): array
    {
        return [
            'web_block_type_id' => [
                'required',
                Rule::Exists('web_block_types', 'id')->where('group_id', $this->organisation->group_id)
            ],
            'position' => [
                'sometimes'
            ]
        ];
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->webpage = $webpage;
        if ($webpage->shop->type == ShopTypeEnum::FULFILMENT) {
            $this->scope = $webpage->shop->fulfilment;
            $this->initialisationFromFulfilment($this->scope, $request);
        } else {
            $this->scope = $webpage->shop;
            $this->initialisationFromShop($this->scope, $request);
        }


        $this->handle($webpage, $this->validatedData);
    }

    public function action(Webpage $webpage, array $modelData): ModelHasWebBlocks
    {
        $this->asAction = true;

        $this->initialisationFromShop($webpage->shop, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }

    public function jsonResponse(ModelHasWebBlocks $modelHasWebBlock): WebpageResource
    {
        $this->webpage->refresh();
        return new WebpageResource($this->webpage);
    }

}
