<?php

/*
 * Author: ekayudinata <dev@aw-advantage.com>
 * Created: Mon, 20 Jul 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\PreferredShipping;

use App\Actions\OrgAction;
use App\Models\Catalogue\PreferredShipping;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdatePreferredShipping extends OrgAction
{
    use AsAction;
    use WithAttributes;

    public function handle(PreferredShipping $preferredShipping, array $modelData): PreferredShipping
    {
        $preferredShipping->update($modelData);

        return $preferredShipping;
    }

    public function rules(): array
    {
        return [
            'shipper_id' => [
                'sometimes',
                'integer',
                Rule::exists('shippers', 'id')->where('organisation_id', $this->shop->organisation_id),
            ],
            'country_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('countries', 'id')->where('status', true),
            ],
            'postcode' => ['sometimes', 'nullable', 'string', 'max:255'],
            'important' => ['sometimes', 'boolean'],
        ];
    }

    public function action(PreferredShipping $preferredShipping, array $modelData, int $hydratorsDelay = 0, bool $audit = true): PreferredShipping
    {
        if (!$audit) {
            PreferredShipping::disableAuditing();
        }

        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($preferredShipping->shop, $modelData);

        return $this->handle($preferredShipping, $this->validatedData);
    }
}
