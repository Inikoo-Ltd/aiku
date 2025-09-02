<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 11:16:11 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Favourite;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavouritedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavourited;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateFavourites;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Favourite;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreFavourite extends OrgAction
{
    use WithActionUpdate;

    public function handle(Customer $customer, Product $product, array $modelData): Favourite
    {
        $favourite = $customer->favourites()->where('product_id', $product->id)->first();
        if ($favourite)
        {
            if(!$favourite->unfavourited_at) {
                throw ValidationException::withMessages(
                    [
                        'message' => [
                            'favourite' => 'Product already favourited',
                        ]
                    ]
                );
            } else {
                $this->update($favourite, ['unfavourited_at' => null, 'current_favourite_id' => $favourite->id]);
            }
            
        } else {
            data_set($modelData, 'group_id', $customer->group_id);
            data_set($modelData, 'organisation_id', $customer->organisation_id);
            data_set($modelData, 'shop_id', $customer->shop_id);
            data_set($modelData, 'product_id', $product->id);
            data_set($modelData, 'department_id', $product->department_id);
            data_set($modelData, 'sub_department_id', $product->sub_department_id);
            data_set($modelData, 'family_id', $product->family_id);
    
    
            /** @var Favourite $favourite */
            $favourite = $customer->favourites()->create($modelData);
    
            CustomerHydrateFavourites::run($customer);
            ProductHydrateCustomersWhoFavourited::dispatch($product);
            ProductHydrateCustomersWhoFavouritedInCategories::dispatch($product);
        }


        return $favourite;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }


        return false;
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


    public function action(Customer $customer, Product $product, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Favourite
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->strict         = $strict;
        $this->initialisationFromShop($customer->shop, $modelData);

        return $this->handle($customer, $product, $this->validatedData);
    }


}
