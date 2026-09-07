<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 17:52:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Helpers\Country;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * The tax number is an explicit country plus number combo: the country comes from the field itself,
 * falling back to the contact address country. Nothing guesses the country from the number.
 */
trait WithPrepareTaxNumberValidation
{
    protected bool $taxNumberCountryIsMissing = false;

    public function prepareForValidation(ActionRequest $request): void
    {
        $this->prepareTaxNumberInput($request);
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->taxNumberCountryIsMissing) {
            $validator->after(function (Validator $validator) {
                $validator->errors()->add('tax_number', __('Tax number needs its country'));
            });
        }
    }

    protected function prepareTaxNumberInput(ActionRequest $request): void
    {
        if (!$request->has('tax_number')) {
            return;
        }

        $taxNumberInput = $request->input('tax_number');

        $number = strip_tags((string) (Arr::get($taxNumberInput, 'number') ?: Arr::get($taxNumberInput, 'value')));
        if (!$number) {
            $this->taxNumberCountryIsMissing = false;
            $this->set('tax_number', null);

            return;
        }

        $countryId = Arr::get($taxNumberInput, 'country_id');

        if (!$countryId && Arr::get($taxNumberInput, 'country_code')) {
            $countryId = Country::where('code', Arr::get($taxNumberInput, 'country_code'))->value('id');
        }

        if (!$countryId) {
            $countryId = Arr::get($request->input('contact_address'), 'country_id')
                ?? Arr::get($request->input('address'), 'country_id');
        }

        $this->taxNumberCountryIsMissing = !$countryId;

        $this->set('tax_number', [
            'number'     => $number,
            'country_id' => $countryId,
        ]);
    }
}
