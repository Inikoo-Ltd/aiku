<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\Catalogue\Product\DeleteProduct;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Bundle;
use App\Models\CRM\Customer;
use Lorisleiva\Actions\ActionRequest;

class DeleteBundle extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;

    public function handle(Bundle $bundle): void
    {
        // TODO: Delete bundle in each sales channel

        DeleteProduct::run($bundle->bundleable);
        $bundle->items()->delete();
        $bundle->delete();
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function asController(Bundle $bundle, ActionRequest $request): void
    {
        $this->initialisationFromShop($this->customer->shop, $request);

        $this->handle($bundle);
    }
}
