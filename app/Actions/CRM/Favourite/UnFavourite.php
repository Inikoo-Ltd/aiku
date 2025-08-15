<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Feb 2025 19:47:12 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Favourite;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavouritedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavourited;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateFavourites;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Favourite;
use Lorisleiva\Actions\ActionRequest;

class UnFavourite extends OrgAction
{
    use WithActionUpdate;

    private Favourite $favourite;

    public function handle(Favourite $favourite, array $modelData): Favourite
    {

        $customer = $favourite->customer;
        $product = $favourite->product;


        data_set($modelData, 'unfavourited_at', now(), false);

        $favourite = $this->update($favourite, $modelData, ['data']);

        $customer->favourites()->where('current_favourite_id', $favourite->id)->update(
            [
                'current_favourite_id' => null
            ]
        );


        CustomerHydrateFavourites::run($customer);
        ProductHydrateCustomersWhoFavourited::dispatch($product)->delay($this->hydratorsDelay);
        ProductHydrateCustomersWhoFavouritedInCategories::dispatch($product)->delay($this->hydratorsDelay);

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
        return [
            'unfavourited_at' => ['sometimes','required', 'date']
        ];

    }

    public function action(Favourite $favourite, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Favourite
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->favourite       = $favourite;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($favourite->organisation, $modelData);

        return $this->handle($favourite, $this->validatedData);
    }


}
