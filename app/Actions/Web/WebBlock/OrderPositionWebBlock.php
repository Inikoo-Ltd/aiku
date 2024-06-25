<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 20 Jun 2024 20:54:23 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock;

use App\Actions\GrpAction;
use App\Actions\Traits\Authorisations\HasWebAuthorisation;
use App\Models\Catalogue\Shop;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\ActionRequest;

class OrderPositionWebBlock extends GrpAction
{
    use HasWebAuthorisation;

    protected Shop $shop;

    public function handle(Webpage $webpage, array $modelData): Webpage
    {
        $webpage->webBlocks()->syncWithoutDetaching($modelData);

        return $webpage;
    }

    public function asController(Webpage $webpage, ActionRequest $request): Webpage
    {
        return $this->handle($webpage, $request->input('positions'));
    }

    public function action(Webpage $webpage, array $modelData): Webpage
    {
        $this->asAction = true;

        $this->initialisation($webpage->group, $modelData);

        return $this->handle($webpage, $this->validatedData);
    }

}
