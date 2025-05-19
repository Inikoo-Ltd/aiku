<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 15:50:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\ModelHasWebBlocks;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\WithUploadWebImage;
use App\Models\Dropshipping\ModelHasWebBlocks;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToModelHasWebBlocks extends OrgAction
{
    use WithUploadWebImage;
    use WithWebEditAuthorisation;


    public function asController(ModelHasWebBlocks $modelHasWebBlocks, ActionRequest $request): Collection
    {

        $this->initialisationFromShop($modelHasWebBlocks->shop, $request);
        return $this->handle($modelHasWebBlocks->webBlock, 'image', $this->validatedData);
    }


}
