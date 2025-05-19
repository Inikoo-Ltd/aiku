<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 20:54:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;
use Illuminate\Support\Arr;

class StoreWebBlock extends OrgAction
{
    use WithWebAuthorisation;


    public function handle(WebBlockType $webBlockType, array $modelData): WebBlock
    {
        $models = Arr::pull($modelData, 'models', []);
        data_set($modelData, 'group_id', $webBlockType->group_id);

        data_set(
            $modelData,
            'layout',
            ['data' => $webBlockType->data ?? null, 'blueprint' => $webBlockType->blueprint],
            overwrite: false
        );

        data_set($modelData, 'checksum', md5(json_encode($modelData['layout'])));

        /** @var WebBlock $webBlock */
        $webBlock = $webBlockType->webBlocks()->create($modelData);

        foreach ($models as $model) {
            if ($model instanceof Product) {
                $webBlock->products()->attach($model->id);
            } elseif ($model instanceof ProductCategory) {
                $webBlock->productCategories()->attach($model->id);
            } elseif ($model instanceof Collection) {
                $webBlock->collections()->attach($model->id);
            }
        }

        return $webBlock;
    }

    public function rules(): array
    {
        $rules = [
            'layout'     => ['sometimes', 'array'],
            'models'    =>  ['sometimes', 'array']
        ];

        if (!$this->strict) {
            $rules['migration_checksum'] = ['sometimes', 'string'];
        }

        return $rules;
    }

    public function action(WebBlockType $webBlockType, array $modelData, $strict = true): WebBlock
    {
        $this->strict   = $strict;
        $this->asAction = true;

        $this->initialisationFromGroup($webBlockType->group, $modelData);

        return $this->handle($webBlockType, $this->validatedData);
    }

}
