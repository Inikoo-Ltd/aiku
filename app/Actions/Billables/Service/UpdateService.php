<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:22:15 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Service;

use App\Actions\Catalogue\Asset\UpdateAssetFromModel;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateServices;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Billables\Service\ServiceEditTypeEnum;
use App\Enums\Billables\Service\ServiceStateEnum;
use App\Models\Billables\Service;
use App\Rules\AlphaDashDot;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class UpdateService extends OrgAction
{
    use WithActionUpdate;


    private Service $service;

    /**
     * @return array<string, string>
     */
    public static function autoAssignOptions(): array
    {
        return [
            'none'                           => __('No'),
            'PalletDelivery:Pallet:pallet'   => __('Delivery: per pallet'),
            'PalletDelivery:Pallet:box'      => __('Delivery: per box'),
            'PalletDelivery:Pallet:oversize' => __('Delivery: per oversize'),
            'PalletReturn:Pallet:pallet'     => __('Return: per pallet'),
            'PalletReturn:Pallet:box'        => __('Return: per box'),
            'PalletReturn:Pallet:oversize'   => __('Return: per oversize'),
            'PalletReturn:StoredItem:'       => __('Return: per SKO piece picked'),
            'PalletReturn:StoredItem:carton' => __('Return: per SKO full carton picked'),
        ];
    }

    public function handle(Service $service, array $modelData): Service
    {
        if (Arr::exists($modelData, 'auto_assign')) {
            $autoAssign = Arr::pull($modelData, 'auto_assign');
            [$trigger, $subject, $subjectType] = array_pad(explode(':', $autoAssign), 3, null);

            $modelData['is_auto_assign']           = $autoAssign != 'none';
            $modelData['auto_assign_status']       = $autoAssign != 'none';
            $modelData['auto_assign_trigger']      = $autoAssign == 'none' ? null : $trigger;
            $modelData['auto_assign_subject']      = $subject;
            $modelData['auto_assign_subject_type'] = $subjectType ?: null;
        }

        if (Arr::exists($modelData, 'fixed_price')) {
            $fixedPrice = Arr::pull($modelData, 'fixed_price');
            if ($fixedPrice) {
                data_set($modelData, 'edit_type', ServiceEditTypeEnum::QUANTITY);
            } else {
                data_set($modelData, 'edit_type', ServiceEditTypeEnum::NET);
            }
        }

        if (Arr::exists($modelData, 'active')) {
            $active = Arr::pull($modelData, 'active');
            if ($active) {
                data_set($modelData, 'status', true);
                data_set($modelData, 'state', ServiceStateEnum::ACTIVE);
            } else {
                data_set($modelData, 'status', false);
                data_set($modelData, 'state', ServiceStateEnum::DISCONTINUED);
            }
        }


        if (Arr::exists($modelData, 'state')) {
            $state = Arr::get($modelData, 'state');
            if (!$state instanceof ServiceStateEnum) {
                $state = ServiceStateEnum::from($state);
            }
            data_set($modelData, 'state', $state);
            data_set($modelData, 'status', $state == ServiceStateEnum::ACTIVE);
        }

        $service = $this->update($service, $modelData);
        $changed = $service->getChanges();

        if (Arr::hasAny($changed, ['name', 'code', 'price', 'units', 'unit'])) {
            $historicAsset = StoreHistoricAsset::run($service, [], $this->hydratorsDelay);
            $service->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
        }

        UpdateAssetFromModel::run($service->asset, [], $this->hydratorsDelay);


        if (Arr::hasAny($service->getChanges(), ['state'])) {
            ShopHydrateServices::dispatch($service->shop)->delay($this->hydratorsDelay);
        }


        return $service;
    }


    public function rules(): array
    {
        return [
            'code'                     => [
                'sometimes',
                'required',
                'max:32',
                new AlphaDashDot(),
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'services',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                        ['column' => 'id', 'value' => $this->service->id, 'operator' => '!=']
                    ]
                ),
            ],
            'name'                     => ['sometimes', 'required', 'max:250', 'string'],
            'price'                    => ['sometimes', 'required', 'numeric', 'min:0'],
            'description'              => ['sometimes', 'required', 'max:1500'],
            'data'                     => ['sometimes', 'array'],
            'settings'                 => ['sometimes', 'array'],
            'status'                   => ['sometimes', 'required', 'boolean'],
            'units'                    => ['sometimes', 'numeric', 'min:0'],
            'state'                    => ['sometimes', 'required', Rule::enum(ServiceStateEnum::class)],
            'is_auto_assign'           => ['sometimes', 'required', 'boolean'],
            'auto_assign_trigger'      => ['sometimes','nullable', 'string', 'in:PalletDelivery,PalletReturn'],
            'auto_assign_subject'      => ['sometimes','nullable', 'string', 'in:Pallet,StoredItem'],
            'auto_assign_subject_type' => ['sometimes','nullable', 'string', 'in:pallet,box,oversize,carton'],
            'auto_assign'              => ['sometimes', 'required', 'string', Rule::in(array_keys(self::autoAssignOptions()))],
            'auto_assign_status'       => ['sometimes', 'required', 'boolean'],
            'fixed_price'              => ['sometimes', 'boolean'],
            'active'                   => ['sometimes', 'boolean'],
            'is_public'                => ['sometimes', 'boolean'],
            'is_pinned'                => ['sometimes', 'boolean'],

        ];
    }


    public function action(Service $service, array $modelData, int $hydratorsDelay = 0): Service
    {
        $this->asAction       = true;
        $this->service        = $service;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($service->shop, $modelData);
        return $this->handle($service, $this->validatedData);
    }


}
