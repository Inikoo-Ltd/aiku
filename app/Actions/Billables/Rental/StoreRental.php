<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Nov 2024 20:58:56 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Rental;

use App\Actions\Billables\Rental\Search\RentalRecordSearch;
use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateRentals;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRentals;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateRentals;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Rental\RentalTypeEnum;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Billables\Rental;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRental extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Rental
    {

        $status = false;
        if (Arr::get($modelData, 'state') == RentalStateEnum::ACTIVE) {
            $status = true;
        }
        data_set($modelData, 'status', $status);

        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'fulfilment_id', $shop->fulfilment->id);

        data_set($modelData, 'currency_id', $shop->currency_id);

        /** @var Rental $rental */
        $rental = $shop->rentals()->create($modelData);
        $rental->refresh();

        $asset = StoreAsset::run(
            $rental,
            [
                'type'  => AssetTypeEnum::RENTAL,
                'state' => match ($rental->state) {
                    RentalStateEnum::IN_PROCESS   => AssetStateEnum::IN_PROCESS,
                    RentalStateEnum::ACTIVE       => AssetStateEnum::ACTIVE,
                    RentalStateEnum::DISCONTINUED => AssetStateEnum::DISCONTINUED,
                }
            ],
            $this->hydratorsDelay
        );

        $rental->updateQuietly(
            [
                'asset_id' => $asset->id
            ]
        );

        $historicAsset = StoreHistoricAsset::run(
            $rental,
            [
                'source_id' => $rental->historic_source_id
            ],
            $this->hydratorsDelay
        );
        $asset->update(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );
        $rental->updateQuietly(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );

        ShopHydrateRentals::dispatch($shop)->delay($this->hydratorsDelay);
        OrganisationHydrateRentals::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        GroupHydrateRentals::dispatch($shop->group)->delay($this->hydratorsDelay);
        RentalRecordSearch::dispatch($rental);

        return $rental;
    }

    public function rules(): array
    {
        return [
            'code'               => [
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'rentals',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => RentalStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'name'                   => ['required', 'max:250', 'string'],
            'price'                  => ['required', 'numeric', 'min:0'],
            'unit'                   => ['required', 'string'],

            'state'                    => ['sometimes', 'required', Rule::enum(RentalStateEnum::class)],
            'data'                     => ['sometimes', 'array'],
            'created_at'               => ['sometimes', 'date'],
            'source_id'                => ['sometimes', 'string', 'max:63'],
            'type'                     => ['sometimes', Rule::enum(RentalTypeEnum::class)],
            'auto_assign_asset'      => ['sometimes','nullable', 'string', 'in:Pallet,StoredItem'],
            'auto_assign_asset_type' => ['sometimes','nullable', 'string', 'in:pallet,box,oversize'],

        ];
    }

    public function htmlResponse(Rental $rental)
    {
        return Redirect::route('grp.org.fulfilments.show.catalogue.rentals.show', [
            'organisation' => $rental->organisation->slug,
            'fulfilment'   => $rental->fulfilment->slug,
            'rental'       => $rental->slug
        ]);
    }


    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0): Rental
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;

        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function asController(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request)
    {
        $this->initialisationFromFulfilment($fulfilment, $request);
        return $this->handle($fulfilment->shop, $this->validatedData);
    }
}
