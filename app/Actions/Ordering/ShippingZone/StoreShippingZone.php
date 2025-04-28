<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 18 Apr 2023 15:08:02 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\ShippingZone;

use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\OrgAction;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Models\Ordering\ShippingZone;
use App\Models\Ordering\ShippingZoneSchema;
use App\Rules\IUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreShippingZone extends OrgAction
{
    use AsAction;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(ShippingZoneSchema $shippingZoneSchema, array $modelData): ShippingZone
    {
        data_set($modelData, 'group_id', $shippingZoneSchema->group_id);
        data_set($modelData, 'organisation_id', $shippingZoneSchema->organisation_id);
        data_set($modelData, 'shop_id', $shippingZoneSchema->shop_id);
        data_set($modelData, 'currency_id', $shippingZoneSchema->shop->currency_id);


        return DB::transaction(function () use ($shippingZoneSchema, $modelData) {
            /** @var $shippingZone ShippingZone */
            $shippingZone = $shippingZoneSchema->shippingZones()->create($modelData);
            $shippingZone->stats()->create();
            $shippingZone->refresh();

            $asset = StoreAsset::run(
                $shippingZone,
                [
                    'units' => 1,
                    'unit'  => 'charge',
                    'price' => null,
                    'type'  => AssetTypeEnum::CHARGE,
                    'state' => $shippingZone->status ? AssetStateEnum::ACTIVE : AssetStateEnum::DISCONTINUED,

                ],
                $this->hydratorsDelay
            );

            $shippingZone->updateQuietly(
                [
                    'asset_id' => $asset->id
                ]
            );

            $historicAsset = StoreHistoricAsset::run(
                $shippingZone
            );
            $asset->update(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
            $shippingZone->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );

            return $shippingZone;
        });
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'required',
                new IUnique(
                    table: 'shipping_zones',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
                'between:2,16',
                'alpha_dash'
            ],
            'name'        => ['required', 'max:255', 'string'],
            'status'      => ['required', 'boolean'],
            'price'       => ['required', 'array'],
            'territories' => ['sometimes', 'array'],
            'position'    => ['required', 'integer'],
            'is_failover' => ['sometimes', 'boolean'],

        ];

        if (!$this->strict) {
            $rules['fetched_at'] = ['sometimes', 'date'];
            $rules['created_at'] = ['sometimes', 'date'];
            $rules['source_id']  = ['sometimes', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(ShippingZoneSchema $shippingZoneSchema, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): ShippingZone
    {
        if (!$audit) {
            ShippingZone::disableAuditing();
        }
        $this->strict         = $strict;
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($shippingZoneSchema->shop, $modelData);

        return $this->handle($shippingZoneSchema, $this->validatedData);
    }
}
