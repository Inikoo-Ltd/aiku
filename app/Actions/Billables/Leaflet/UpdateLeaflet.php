<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Leaflet;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use App\Models\Billables\Leaflet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateLeaflet extends OrgAction
{
    use WithActionUpdate;

    public function handle(Leaflet $leaflet, array $modelData): Leaflet
    {
        return $this->update($leaflet, $modelData);
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
            'name'         => ['sometimes', 'required', 'max:250', 'string'],
            'type'         => ['sometimes', 'required', Rule::enum(LeafletTypeEnum::class)],
            'price'        => ['sometimes', 'required', 'numeric', 'min:0'],
            'family_codes'   => ['sometimes', 'nullable', 'array'],
            'family_codes.*' => [
                'string',
                Rule::exists('packagings', 'family_code')->where('shop_id', $this->shop->id),
            ],
            'state'        => ['sometimes', 'required', Rule::enum(LeafletStateEnum::class)],
            'data'         => ['sometimes', 'array'],
        ];
    }

    public function action(Leaflet $leaflet, array $modelData, int $hydratorsDelay = 0, bool $strict = true): Leaflet
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;

        $this->initialisationFromShop($leaflet->shop, $modelData);

        return $this->handle($leaflet, $this->validatedData);
    }

    public function asController(Leaflet $leaflet, ActionRequest $request): Leaflet
    {
        $this->initialisationFromShop($leaflet->shop, $request);

        return $this->handle($leaflet, $this->validatedData);
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
            'description' => __('Leaflet :name updated successfully.', ['name' => $leaflet->name]),
        ]);
    }
}
