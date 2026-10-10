<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use Lorisleiva\Actions\ActionRequest;

trait WithSeoEditAuthorisation
{
    use WithShopPpcPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditSeo($request->user(), $this->shop);
    }
}
