<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 12 Oct 2024 11:26:56 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Favourite;

use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavouritedInCategories;
use App\Actions\Catalogue\Product\Hydrators\ProductHydrateCustomersWhoFavourited;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateFavourites;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\CRM\Favourite;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateFavourite extends OrgAction
{
    use WithActionUpdate;

    private Favourite $favourite;

    public function handle(Favourite $favourite, array $modelData): Favourite
    {
        $favourite = $this->update($favourite, $modelData, ['data']);

        $changes = Arr::except($favourite->getChanges(), ['updated_at', 'last_fetched_at']);

        if (count($changes) > 0) {
            CustomerHydrateFavourites::run($favourite->customer);
            ProductHydrateCustomersWhoFavourited::dispatch($favourite->product)->delay($this->hydratorsDelay);
            ProductHydrateCustomersWhoFavouritedInCategories::dispatch($favourite->product)->delay($this->hydratorsDelay);
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
            $rules['last_fetched_at'] = ['sometimes', 'date'];
            $rules['unfavourited_at'] = ['sometimes', 'nullable', 'date'];
        }

        return $rules;
    }

    public function action(Favourite $favourite, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Favourite
    {
        $this->strict = $strict;

        $this->asAction       = true;
        $this->favourite      = $favourite;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($favourite->organisation, $modelData);

        return $this->handle($favourite, $this->validatedData);
    }


}
