<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithDiscountsEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo([
            "discounts.{$this->shop->id}.edit",
            "supervisor-discounts.{$this->shop->id}",
        ]);
    }
}
