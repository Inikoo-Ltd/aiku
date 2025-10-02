<?php

/*
 * author Arya Permana - Kirin
 * created on 19-06-2025-15h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use Lorisleiva\Actions\ActionRequest;

class UpdateBulkProduct extends OrgAction
{
    use WithActionUpdate;
    use WithCatalogueEditAuthorisation;

    public function handle(array $modelData): void
    {
        //
    }


    public function rules(): array
    {
        return [
            //
        ];
    }

    public function asController(ActionRequest $request): void
    {
        // $this->initialisationFromShop($family->shop, $request);
        $this->handle($this->validatedData);
    }
}
