<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 19:36:35 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Bundle;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\CustomerSalesChannel;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class StoreOrUpdateBundle extends OrgAction
{
    use WithNoStrictRules;

    private Customer $customer;
    public bool $isUpdate = false;

    /**
     * @throws \Throwable
     */
    public function handle(CustomerSalesChannel $customerSalesChannel, array $modelData): Bundle
    {
        if($this->isUpdate) {
            $bundle = Bundle::find(Arr::get($modelData, 'id'));

            return UpdateBundle::make()->action($bundle, $modelData);
        } else {
            return StoreBundle::make()->action($customerSalesChannel, $modelData);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("crm.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return $this->isUpdate ? UpdateBundle::make()->rules() : StoreBundle::make()->rules();
    }

    public function action(CustomerSalesChannel $customerSalesChannel, array $modelData): Bundle
    {
        $id = Arr::get($modelData, 'id');
        $this->isUpdate = (bool) $id;

        $this->initialisationFromShop($this->customer->shop, $modelData);

        return $this->handle($customerSalesChannel, $this->validatedData);
    }
}
