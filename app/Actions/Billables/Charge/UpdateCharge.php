<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge;

use App\Actions\Billables\Charge\Search\ChargeRecordSearch;
use App\Actions\Catalogue\Asset\UpdateAssetFromModel;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Rules\IUnique;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateCharge extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;

    private Charge $charge;

    public function handle(Charge $charge, array $modelData): Charge
    {
        if (Arr::exists($modelData, 'state')) {
            $status = false;
            if (Arr::get($modelData, 'state') == ChargeStateEnum::ACTIVE) {
                $status = true;
            }
            data_set($modelData, 'status', $status);
        }

        $charge  = $this->update($charge, $modelData);
        $changed = $charge->getChanges();

        ChargeRecordSearch::dispatch($charge);
        if (Arr::hasAny($changed, ['name', 'code'])) {
            $historicAsset = StoreHistoricAsset::run($charge, [], $this->hydratorsDelay);
            $charge->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
        }

        UpdateAssetFromModel::run(
            $charge->asset,
            [
                'price' => null,
                'unit'  => 'charge',
                'units' => 1
            ],
            $this->hydratorsDelay
        );

        return $charge;
    }


public function prepareForValidation()
{
    $settings = $this->charge->settings ?? [];
    if($this->has('amount')) {
        $amount = (string) $this->get('amount');
        $settings['amount'] = $amount;
    }

    if ($this->has('min_order')) {
        $minOrder = (string) $this->get('min_order');

        $settings['rules'] = '<;' . $minOrder;
    }
    $this->set('settings', $settings);
}

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'sometimes',
                'max:32',
                'alpha_dash',
                new IUnique(
                    table: 'charges',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => ChargeStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'notNull'],
                    ]
                ),
            ],
            'name'        => ['sometimes', 'required', 'max:250', 'string'],
            'label'        => ['sometimes', 'string'],
            'description' => ['sometimes', 'max:1024', 'string'],

            'data'     => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],

            'state'   => ['sometimes', 'required', Rule::enum(ChargeStateEnum::class)],
            'trigger' => ['sometimes', 'required', Rule::enum(ChargeTriggerEnum::class)],
            'type'    => ['sometimes', 'required', Rule::enum(ChargeTypeEnum::class)],


        ];

        if (!$this->strict) {
            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function asController(Charge $charge, ActionRequest $request): Charge
    {
        $this->charge = $charge;
        $this->initialisationFromShop($charge->shop, $request);

        return $this->handle($charge, $this->validatedData);
    }

    public function action(Charge $charge, array $modelData, int $hydratorsDelay = 0, bool $strict = true, bool $audit = true): Charge
    {
        if (!$audit) {
            Charge::disableAuditing();
        }

        $this->strict         = $strict;
        $this->asAction       = true;
        $this->charge         = $charge;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($charge->shop, $modelData);

        return $this->handle($charge, $this->validatedData);
    }


}
