<?php

/*
 * author Arya Permana - Kirin
 * created on 15-10-2024-16h-24m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\CRM\BackInStockReminder;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoReminded;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoRemindedInCategories;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBackInStockReminders;
use App\Actions\OrgAction;
use App\Models\Catalogue\Product;
use App\Models\CRM\BackInStockReminder;
use App\Models\CRM\Customer;

class StoreBackInStockReminder extends OrgAction
{
    public function handle(Customer $customer, Product $product, array $modelData): BackInStockReminder
    {
        /** @noinspection DuplicatedCode */
        data_set($modelData, 'group_id', $customer->group_id);
        data_set($modelData, 'organisation_id', $customer->organisation_id);
        data_set($modelData, 'shop_id', $customer->shop_id);
        data_set($modelData, 'product_id', $product->id);
        data_set($modelData, 'department_id', $product->department_id);
        data_set($modelData, 'sub_department_id', $product->sub_department_id);
        data_set($modelData, 'family_id', $product->family_id);


        /** @var BackInStockReminder $reminder */
        $reminder = $customer->backInStockReminder()->create($modelData);

        CustomerHydrateBackInStockReminders::dispatch($customer->id)->delay($this->hydratorsDelay);
        ProductHydrateCustomersWhoReminded::dispatch($product)->delay($this->hydratorsDelay);
        ProductHydrateCustomersWhoRemindedInCategories::dispatch($product)->delay($this->hydratorsDelay);

        return $reminder;
    }


    public function rules(): array
    {
        $rules = [];
        if (!$this->strict) {
            $rules['source_id']  = ['sometimes', 'string', 'max:64'];
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }


    public function action(Customer $customer, Product $product, array $modelData, int $hydratorsDelay = 0, bool $strict = true): BackInStockReminder
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $product, $this->validatedData);
    }


}
