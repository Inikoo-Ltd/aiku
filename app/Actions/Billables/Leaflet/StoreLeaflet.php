<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Leaflet;

use App\Actions\OrgAction;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Leaflet;
use App\Models\Catalogue\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreLeaflet extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Leaflet
    {
        if (!Arr::has($modelData, 'state')) {
            data_set($modelData, 'state', LeafletStateEnum::ACTIVE);
        }

        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'currency_id', $shop->currency_id);

        return Leaflet::create($modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function rules(): array
    {
        return [
            'name'         => ['required', 'max:250', 'string'],
            'type'         => ['required', Rule::enum(LeafletTypeEnum::class)],
            'price'        => ['required', 'numeric', 'min:0'],
            'family_codes'   => ['sometimes', 'nullable', 'array'],
            'family_codes.*' => [
                'string',
                Rule::exists('packagings', 'family_code')->where('shop_id', $this->shop->id),
            ],
            'state'        => ['sometimes', 'required', Rule::enum(LeafletStateEnum::class)],
            'data'         => ['sometimes', 'array'],
        ];
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Leaflet
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Shop $shop, ActionRequest $request): Leaflet
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Leaflet $leaflet): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.billables.packagings.index', [
            'organisation' => $leaflet->organisation->slug,
            'shop'         => $leaflet->shop->slug,
            'tab'          => 'leaflets',
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leaflet :name created successfully.', ['name' => $leaflet->name]),
        ]);
    }
}
