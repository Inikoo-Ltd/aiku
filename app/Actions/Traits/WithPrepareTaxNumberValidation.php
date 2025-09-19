<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 17:52:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

/**
 * Shared prepareForValidation implementation for handling tax_number array input.
 * Extracted to avoid duplication across actions.
 */
trait WithPrepareTaxNumberValidation
{
    public function prepareForValidation(ActionRequest $request): void
    {
        if ($request->has('tax_number')) {
            $taxNumberValue = (string) Arr::get($request->get('tax_number'), 'value');
            if ($taxNumberValue) {
                $countryCode   = Arr::get($request->get('tax_number'), 'country.isoCode.short');
                $country       = Country::where('code', $countryCode)->first();
                $taxNumberData = [
                    'number'     => (string) Arr::get($request->get('tax_number'), 'value'),
                    'country_id' => $country?->id,
                ];
            } else {
                $taxNumberData = null;
            }

            $this->set('tax_number', $taxNumberData);
        }
    }
}
