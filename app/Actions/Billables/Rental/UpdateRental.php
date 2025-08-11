<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 20:58:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Rental;

use App\Actions\Billables\Rental\Search\RentalRecordSearch;
use App\Actions\Catalogue\Asset\UpdateAssetFromModel;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRentals;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Models\Billables\Rental;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRental extends OrgAction
{
    use WithActionUpdate;

    private Rental $rental;

    public function handle(Rental $rental, array $modelData): Rental
    {
        if (Arr::exists($modelData, 'state')) {
            $status = false;
            if (Arr::get($modelData, 'state') == RentalStateEnum::ACTIVE) {
                $status = true;
            }
            data_set($modelData, 'status', $status);
        }

        $rental  = $this->update($rental, $modelData);
        $changed = $rental->getChanges();

        if (Arr::hasAny($changed, ['name', 'code', 'price', 'units', 'unit'])) {
            $historicAsset = StoreHistoricAsset::run($rental, [], $this->hydratorsDelay);
            $rental->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
        }

        UpdateAssetFromModel::run($rental->asset, [], $this->hydratorsDelay);


        if (Arr::hasAny($rental->getChanges(), ['state'])) {
            ShopHydrateRentals::dispatch($rental->shop)->delay($this->hydratorsDelay);
        }
        RentalRecordSearch::dispatch($rental);

        return $rental;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return true; //TODO: Fix Auth
    }

    public function rules(): array
    {
        return [
            'code'  => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'rentals',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                        ['column' => 'id', 'value' => $this->rental->id, 'operator' => '!=']
                    ]
                ),
            ],
            'name'  => ['sometimes', 'required', 'max:250', 'string'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'unit'  => ['sometimes', 'required', 'string'],

            'description'            => ['sometimes', 'required', 'max:1500'],
            'data'                   => ['sometimes', 'array'],
            'settings'               => ['sometimes', 'array'],
            'status'                 => ['sometimes', 'required', 'boolean'],
            'state'                  => ['sometimes', 'required', Rule::enum(RentalStateEnum::class)],
            'auto_assign_asset'      => ['sometimes', 'nullable', 'string', 'in:Pallet,StoredItem'],
            'auto_assign_asset_type' => ['sometimes', 'nullable', 'string', 'in:pallet,box,oversize'],
        ];
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, Rental $rental, ActionRequest $request): Rental
    {
        $this->rental = $rental;

        $this->initialisationFromFulfilment($fulfilment, $request);

        return $this->handle($rental, $this->validatedData);
    }

    public function asController(Rental $rental, ActionRequest $request): Rental
    {
        $this->rental = $rental;

        $this->initialisationFromShop($rental->shop, $request);

        return $this->handle($rental, $this->validatedData);
    }

    public function action(Rental $rental, array $modelData, int $hydratorsDelay = 0): Rental
    {
        $this->asAction = true;
        $this->rental   = $rental;

        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($rental->shop, $modelData);

        return $this->handle($rental, $this->validatedData);
    }


}
