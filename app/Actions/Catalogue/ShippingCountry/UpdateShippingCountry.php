<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Dec 2025 15:39:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ShippingCountry;

use App\Actions\OrgAction;
use App\Models\Ordering\ShippingCountry;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateShippingCountry extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(ShippingCountry $shippingCountry, array $modelData): ShippingCountry
    {
        $shippingCountry->update($modelData);

        return $shippingCountry;
    }

    public function rules(): array
    {
        return [
            'territories' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function action(ShippingCountry $shippingCountry, array $modelData, int $hydratorsDelay = 0, bool $audit = true): ShippingCountry
    {
        if (!$audit) {
            ShippingCountry::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        // Initialise context from the owning shop for validation consistency
        $this->initialisationFromShop($shippingCountry->shop, $modelData);

        return $this->handle($shippingCountry, $this->validatedData);
    }
}
