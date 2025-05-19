<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebAuthorisation;
use App\Actions\Web\WithUploadWebImage;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToModelHasWebBlocks extends OrgAction
{
    use WithUploadWebImage;
    use WithWebAuthorisation;


    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): Collection
    {

        $webpage = $modelHasWebBlocks->webpage;

        if ($webpage->shop->type == ShopTypeEnum::FULFILMENT) {
            $this->scope = $webpage->shop->fulfilment;
            $this->initialisationFromFulfilment($this->scope, $request);

        } else {
            $this->scope = $webpage->shop;
            $this->initialisationFromShop($this->scope, $request);

        }

        return $this->handle($modelHasWebBlocks->webBlock, 'image', $this->validatedData);
    }


}
