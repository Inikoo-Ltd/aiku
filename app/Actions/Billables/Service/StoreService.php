<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service;

use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateServices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateServices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateServices;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Billables\Service;
use App\Models\Catalogue\Shop;
use App\Models\Fulfilment\Fulfilment;
use App\Models\SysAdmin\Organisation;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreService extends OrgAction
{
    public function handle(Shop $shop, array $modelData): Service
    {
        $status = false;
        if (Arr::get($modelData, 'state') == ServiceStateEnum::ACTIVE) {
            $status = true;
        }
        data_set($modelData, 'status', $status);

        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'currency_id', $shop->currency_id);


        /** @var Service $service */
        $service = $shop->services()->create($modelData);
        $service->refresh();

        $asset = StoreAsset::run(
            $service,
            [
                'type'  => AssetTypeEnum::SERVICE,
                'state' => match ($service->state) {
                    ServiceStateEnum::IN_PROCESS   => AssetStateEnum::IN_PROCESS,
                    ServiceStateEnum::ACTIVE       => AssetStateEnum::ACTIVE,
                    ServiceStateEnum::DISCONTINUED => AssetStateEnum::DISCONTINUED,
                }
            ],
            $this->hydratorsDelay
        );

        $service->updateQuietly(
            [
                'asset_id' => $asset->id
            ]
        );

        $historicAsset = StoreHistoricAsset::run(
            $service,
            [
                'source_id' => $service->historic_source_id
            ],
            $this->hydratorsDelay
        );
        $asset->update(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );
        $service->updateQuietly(
            [
                'current_historic_asset_id' => $historicAsset->id,
            ]
        );

        ShopHydrateServices::dispatch($shop)->delay($this->hydratorsDelay);
        OrganisationHydrateServices::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        GroupHydrateServices::dispatch($shop->group)->delay($this->hydratorsDelay);


        return $service;
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
                    table: 'services',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => RentalStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'name'                     => ['required', 'max:250', 'string'],
            'price'                    => ['required', 'numeric', 'min:0'],
            'unit'                     => ['required', 'string'],
            'state'                    => ['sometimes', 'required', Rule::enum(ServiceStateEnum::class)],
            'data'                     => ['sometimes', 'array'],
            'created_at'               => ['sometimes', 'date'],
            'source_id'                => ['sometimes', 'string', 'max:63'],
            'is_auto_assign'           => ['sometimes', 'required', 'boolean'],
            'auto_assign_trigger'      => ['sometimes','nullable', 'string', 'in:PalletDelivery,PalletReturn'],
            'auto_assign_subject'      => ['sometimes','nullable', 'string', 'in:Pallet,StoredItem'],
            'auto_assign_subject_type' => ['sometimes','nullable', 'string', 'in:pallet,box,oversize'],
            'auto_assign_status'       => ['sometimes', 'required', 'boolean'],
            'is_pallet_handling'       => ['sometimes', 'required', 'boolean'],
        ];
    }

    public function htmlResponse(Service $service)
    {
        return Redirect::route('grp.org.fulfilments.show.catalogue.services.show', [
            'organisation' => $service->organisation->slug,
            'fulfilment'   => $service->shop->fulfilment->slug,
            'service'      => $service->slug
        ]);
    }

    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0): Service
    {
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;


        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    public function inFulfilment(Organisation $organisation, Fulfilment $fulfilment, ActionRequest $request)
    {
        $this->initialisationFromShop($fulfilment->shop, $request);

        return $this->handle($fulfilment->shop, $this->validatedData);
    }


}
