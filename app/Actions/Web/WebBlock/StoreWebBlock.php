<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 20:54:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Models\Web\WebBlock;
use App\Models\Web\WebBlockType;

class StoreWebBlock extends GrpAction
{
    use HasWebAuthorisation;


    public function handle(WebBlockType $webBlockType, array $modelData): WebBlock
    {

        data_set($modelData, 'group_id', $webBlockType->group_id);
        data_set($modelData, 'web_block_type_category_id', $webBlockType->web_block_type_category_id);
        data_set($modelData, 'layout', $webBlockType->toArray(), overwrite:false);
        data_set($modelData, 'checksum', md5(json_encode($modelData['layout'])));

        /** @var WebBlock $webBlock */
        $webBlock = $webBlockType->webBlocks()->create($modelData);


        return $webBlock;
    }

    public function rules(): array
    {
        $rules= [
            'layout'    => ['sometimes', 'array']
        ];

        if(!$this->strict){
            $rules['migration_checksum']=['sometimes','string'];
        }

        return $rules;

    }

    public function action(WebBlockType $webBlockType, array $modelData,$strict=true): WebBlock
    {
        $this->strict=$strict;
        $this->asAction = true;

        $this->initialisation($webBlockType->group, $modelData);

        return $this->handle($webBlockType, $this->validatedData);
    }

}
