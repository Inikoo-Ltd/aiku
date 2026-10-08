<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use Lorisleiva\Actions\ActionRequest;

trait WithSeoEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "web.{$this->shop->id}.edit",
            "group-webmaster.edit",
        ]);
    }
}
