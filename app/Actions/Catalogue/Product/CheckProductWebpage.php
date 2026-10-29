<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\WithSeoEditAuthorisation;
use App\Actions\Web\Webpage\WithWebpageCheck;
use App\Models\Catalogue\Product;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class CheckProductWebpage extends OrgAction
{
    use WithSeoEditAuthorisation;
    use WithWebpageCheck;

    /**
     * @throws ValidationException
     */
    public function asController(Product $product, ActionRequest $request): array
    {
        abort_unless($product->webpage, 404);

        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product->webpage, $request->user()->id);
    }
}
