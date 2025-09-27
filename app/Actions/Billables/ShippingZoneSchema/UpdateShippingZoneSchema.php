<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateShippingZoneSchema extends OrgAction
{
    use WithActionUpdate;


    private ShippingZoneSchema $shippingZoneSchema;

    public function handle(ShippingZoneSchema $shippingZoneSchema, array $modelData): ShippingZoneSchema
    {
        return $this->update($shippingZoneSchema, $modelData);
    }


    public function rules(): array
    {
        $rules = [
            'name' => ['sometimes', 'max:255', 'string'],
        ];
        if (!$this->strict) {
            $rules['state']            = ['sometimes', Rule::enum(ShippingZoneSchemaStateEnum::class)];
            $rules['last_fetched_at'] = ['sometimes', 'date'];
        }

        return $rules;
    }

    public function action(ShippingZoneSchema $shippingZoneSchema, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): ShippingZoneSchema
    {
        if (!$audit) {
            ShippingZoneSchema::disableAuditing();
        }
        $this->strict             = $strict;
        $this->shippingZoneSchema = $shippingZoneSchema;
        $this->hydratorsDelay     = $hydratorsDelay;
        $this->initialisationFromShop($shippingZoneSchema->shop, $modelData);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }


    public function asController(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ShippingZoneSchema
    {
        $this->shippingZoneSchema = $shippingZoneSchema;
        $this->initialisationFromShop($shippingZoneSchema->shop, $request);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }


}
