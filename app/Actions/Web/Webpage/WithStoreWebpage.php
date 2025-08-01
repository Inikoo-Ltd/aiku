<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Jun 2025 10:10:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Actions\Web\WebBlock\StoreWebBlock;
use App\Models\Dropshipping\ModelHasWebBlocks;
use App\Models\Web\WebBlockType;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;

trait WithStoreWebpage
{
    protected function createWebBlock(Webpage $webpage, string $webBlockCode): ?ModelHasWebBlocks
    {
        $webBlockType = WebBlockType::where('code', $webBlockCode)->first();

        if (!$webBlockType) {
            return null;
        }

        $newLayout = [];
        data_set($newLayout, 'data.fieldValue', Arr::get($webBlockType->data, 'fieldValue', []));

        $models = [];


        $webBlock = StoreWebBlock::make()->action(
            $webBlockType,
            [
                "layout" => $newLayout,
                "models" => $models,
            ],
            strict: false
        );


        $modelHasWebBlocksData = [
            'show_logged_in'  => true,
            'show_logged_out' => true,
            "group_id"        => $webpage->group_id,
            "organisation_id" => $webpage->organisation_id,
            "shop_id"         => $webpage->shop_id,
            "website_id"      => $webpage->website_id,
            "webpage_id"      => $webpage->id,
            "position"        => 1,
            "model_id"        => $webpage->id,
            "model_type"      => class_basename(Webpage::class),
            "web_block_id"    => $webBlock->id,
            'show'            => true
        ];
        $modelHasWebBlock      = $webpage->modelHasWebBlocks()->create($modelHasWebBlocksData);


        $webpage->refresh();
        UpdateWebpageContent::run($webpage);

        return $modelHasWebBlock;
    }
}
