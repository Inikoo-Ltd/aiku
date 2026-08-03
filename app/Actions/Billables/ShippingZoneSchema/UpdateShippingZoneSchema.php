<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateShippingZoneSchemas;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateShippingZoneSchemas;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateShippingZoneSchemas;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateShippingZoneSchema extends OrgAction
{
    use WithActionUpdate;



    public function handle(ShippingZoneSchema $shippingZoneSchema, array $modelData): ShippingZoneSchema
    {
        $shippingZoneSchema = $this->update($shippingZoneSchema, $modelData);

        if ($shippingZoneSchema->wasChanged('state')) {
            $shop = $shippingZoneSchema->shop;
            ShopHydrateShippingZoneSchemas::dispatch($shop)->delay($this->hydratorsDelay);
            OrganisationHydrateShippingZoneSchemas::dispatch($shop->organisation)->delay($this->hydratorsDelay);
            GroupHydrateShippingZoneSchemas::dispatch($shop->group)->delay($this->hydratorsDelay);
        }

        return $shippingZoneSchema;
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
        $this->hydratorsDelay     = $hydratorsDelay;
        $this->initialisationFromShop($shippingZoneSchema->shop, $modelData);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }


    public function asController(ShippingZoneSchema $shippingZoneSchema, ActionRequest $request): ShippingZoneSchema
    {
        $this->initialisationFromShop($shippingZoneSchema->shop, $request);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }


}
