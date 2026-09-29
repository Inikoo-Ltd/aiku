<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 09:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithAccountingShopAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo("accounting.{$this->organisation->id}.edit");

        if (str_ends_with($request->route()->getName(), '.edit')) {
            return $this->canEdit;
        }

        $permissions = ["accounting.{$this->organisation->id}.view"];

        if (isset($this->fulfilment)) {
            $permissions[] = "fulfilment-shop.{$this->fulfilment->id}.view";
        } elseif (isset($this->shop)) {
            $permissions[] = "crm.{$this->shop->id}.view";
            $permissions[] = "orders.{$this->shop->id}.view";
        }

        return $request->user()->authTo($permissions);
    }
}
