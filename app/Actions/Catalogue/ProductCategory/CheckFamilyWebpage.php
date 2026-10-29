<?php

/*
 * Created: Tue, 20 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Actions\Web\Webpage\WithWebpageCheck;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CheckFamilyWebpage extends OrgAction
{
    use WithSeoEditAuthorisation;
    use WithWebpageCheck;

    /**
     * @throws ValidationException
     */
    public function asController(ProductCategory $family, ActionRequest $request): array
    {
        abort_unless($family->webpage, 404);

        $this->initialisationFromShop($family->shop, $request);

        return $this->handle($family->webpage, $request->user()->id);
    }
}
