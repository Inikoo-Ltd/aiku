<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 05 Mar 2026 09:36:43 UTC+08:00, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithMarketingAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo([
            "crm.{$this->shop->id}.edit",
            "marketing.{$this->shop->id}.edit",
            "supervisor-marketing.{$this->shop->id}"
        ]);

        return $request->user()->authTo([
            "crm.{$this->shop->id}.view",
            "marketing.{$this->shop->id}.view",
            "supervisor-marketing.{$this->shop->id}"
        ]);
    }
}
