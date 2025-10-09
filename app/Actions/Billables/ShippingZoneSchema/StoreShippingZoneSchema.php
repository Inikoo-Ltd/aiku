<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Sept 2025 11:59:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\ShippingZoneSchema;

use App\Actions\OrgAction;
use App\Enums\Ordering\ShippingZoneSchema\ShippingZoneSchemaStateEnum;
use App\Models\Billables\ShippingZoneSchema;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreShippingZoneSchema extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): ShippingZoneSchema
    {
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);

        return DB::transaction(function () use ($shop, $modelData) {
            /** @var $shippingZoneSchema ShippingZoneSchema */
            $shippingZoneSchema = $shop->shippingZoneSchemas()->create($modelData);
            $shippingZoneSchema->stats()->create();

            return $shippingZoneSchema;
        });
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'max:255', 'string'],
        ];

        if (!$this->strict) {
            $rules['is_current']          = ['sometimes', 'boolean'];
            $rules['is_current_discount'] = ['sometimes', 'boolean'];
            $rules['fetched_at']          = ['sometimes', 'date'];
            $rules['created_at']          = ['sometimes', 'date'];
            $rules['source_id']           = ['sometimes', 'nullable', 'string'];
            $rules['state']               = ['sometimes', Rule::enum(ShippingZoneSchemaStateEnum::class)];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): ShippingZoneSchema
    {
        if (!$audit) {
            ShippingZoneSchema::disableAuditing();
        }
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): ShippingZoneSchema
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(ShippingZoneSchema $shippingZoneSchema): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.billables.shipping.show', [
            'organisation'       => $shippingZoneSchema->organisation->slug,
            'shop'               => $shippingZoneSchema->shop->slug,
            'shippingZoneSchema' => $shippingZoneSchema->slug
        ]);
    }
}
