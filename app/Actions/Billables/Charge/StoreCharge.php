<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 20 Nov 2024 15:21:28 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Billables\Charge;

use App\Actions\Billables\Charge\Search\ChargeRecordSearch;
use App\Actions\Catalogue\Asset\StoreAsset;
use App\Actions\Catalogue\HistoricAsset\StoreHistoricAsset;
use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateCharges;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateCharges;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateCharges;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Catalogue\Asset\AssetStateEnum;
use App\Enums\Catalogue\Asset\AssetTypeEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTriggerEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Billables\Charge;
use App\Models\Catalogue\Shop;
use App\Rules\IUnique;
use Illuminate\Console\Command;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreCharge extends OrgAction
{
    use WithNoStrictRules;

    /**
     * @throws \Throwable
     */
    public function handle(Shop $shop, array $modelData): Charge
    {
        $type = Arr::get($modelData, 'type');
        if (!Arr::has($modelData, 'state')
            && in_array($type, [
                ChargeTypeEnum::OTHER,
                ChargeTypeEnum::OTHER->value,
                ChargeTypeEnum::MARKETPLACE_FEE,
                ChargeTypeEnum::MARKETPLACE_FEE->value
            ])) {
            $modelData['state'] = ChargeStateEnum::ACTIVE;
        }

        $status = false;
        if (Arr::get($modelData, 'state') == ChargeStateEnum::ACTIVE) {
            $status = true;
        }


        if (in_array($type, [ChargeTypeEnum::HANGING, ChargeTypeEnum::HANGING->value])) {
            data_set($modelData, 'trigger', ChargeTriggerEnum::ORDER);
        } elseif (in_array($type, [
            ChargeTypeEnum::COD,
            ChargeTypeEnum::COD->value,
            ChargeTypeEnum::PAYMENT,
            ChargeTypeEnum::PAYMENT->value
        ])) {
            data_set($modelData, 'trigger', ChargeTriggerEnum::PAYMENT_ACCOUNT);
        } elseif (in_array($type, [
            ChargeTypeEnum::PACKING,
            ChargeTypeEnum::PACKING->value,
            ChargeTypeEnum::PREMIUM,
            ChargeTypeEnum::PREMIUM->value,
            ChargeTypeEnum::INSURANCE,
            ChargeTypeEnum::INSURANCE->value
        ])) {
            data_set($modelData, 'trigger', ChargeTriggerEnum::SELECTED_BY_CUSTOMER);
        } elseif (in_array($type, [
            ChargeTypeEnum::OTHER,
            ChargeTypeEnum::OTHER->value,
            ChargeTypeEnum::MARKETPLACE_FEE,
            ChargeTypeEnum::MARKETPLACE_FEE->value,
        ])) {
            data_set($modelData, 'trigger', ChargeTriggerEnum::SELECTED_BY_USER);
        }

        data_set($modelData, 'status', $status);
        data_set($modelData, 'organisation_id', $shop->organisation_id);
        data_set($modelData, 'group_id', $shop->group_id);
        data_set($modelData, 'shop_id', $shop->id);
        data_set($modelData, 'currency_id', $shop->currency_id);

        $charge = DB::transaction(function () use ($shop, $modelData) {
            /** @var Charge $charge */
            $charge = $shop->charges()->create($modelData);
            $charge->stats()->create();
            $charge->refresh();

            $asset = StoreAsset::run(
                $charge,
                [
                    'units' => 1,
                    'unit'  => 'charge',
                    'price' => null,
                    'type'  => AssetTypeEnum::CHARGE,
                    'state' => match ($charge->state) {
                        ChargeStateEnum::IN_PROCESS => AssetStateEnum::IN_PROCESS,
                        ChargeStateEnum::ACTIVE => AssetStateEnum::ACTIVE,
                        ChargeStateEnum::DISCONTINUED => AssetStateEnum::DISCONTINUED,
                    }
                ],
                $this->hydratorsDelay
            );

            $charge->updateQuietly(
                [
                    'asset_id' => $asset->id
                ]
            );

            $historicAsset = StoreHistoricAsset::run(
                $charge,
                [],
                $this->hydratorsDelay
            );
            $asset->update(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );
            $charge->updateQuietly(
                [
                    'current_historic_asset_id' => $historicAsset->id,
                ]
            );

            return $charge;
        });

        ShopHydrateCharges::dispatch($shop)->delay($this->hydratorsDelay);
        OrganisationHydrateCharges::dispatch($shop->organisation)->delay($this->hydratorsDelay);
        GroupHydrateCharges::dispatch($shop->group)->delay($this->hydratorsDelay);
        ChargeRecordSearch::dispatch($charge);


        return $charge;
    }

    public function rules(): array
    {
        $rules = [
            'code'        => [
                'required',
                'max:32',
                'alpha_dash',
                new IUnique(
                    table: 'charges',
                    extraConditions: [
                        ['column' => 'shop_id', 'value' => $this->shop->id],
                        ['column' => 'state', 'operator' => '!=', 'value' => ChargeStateEnum::DISCONTINUED->value],
                        ['column' => 'deleted_at', 'operator' => 'null'],
                    ]
                ),
            ],
            'name'        => ['required', 'max:250', 'string'],
            'description' => ['required', 'nullable', 'max:1024', 'string'],
            'state'       => ['sometimes', 'required', Rule::enum(ChargeStateEnum::class)],
            'data'        => ['sometimes', 'array'],
            'settings'    => ['sometimes', 'array'],
            'trigger'     => ['sometimes', 'required', Rule::enum(ChargeTriggerEnum::class)],
            'type'        => ['sometimes', 'required', Rule::enum(ChargeTypeEnum::class)],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    /**
     * @throws \Throwable
     */
    public function action(Shop $shop, array $modelData, int $hydratorsDelay = 0, bool $strict = true, $audit = true): Charge
    {
        if (!$audit) {
            Charge::disableAuditing();
        }
        $this->hydratorsDelay = $hydratorsDelay;
        $this->asAction       = true;
        $this->strict         = $strict;


        $this->initialisationFromShop($shop, $modelData);

        return $this->handle($shop, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Shop $shop, ActionRequest $request): Charge
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(Charge $charge): RedirectResponse
    {
        return Redirect::route('grp.org.shops.show.billables.charges.show', [
            'organisation' => $charge->organisation->slug,
            'shop'         => $charge->shop->slug,
            'charge'       => $charge->slug
        ]);
    }

    public function getCommandSignature(): string
    {
        return 'charge:create {shop} {type} {code} {name} {description}';
    }

    /**
     * @throws \Throwable
     */
    public function asCommand(Command $command): int
    {
        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $this->handle(
            $shop,
            [
                'code'        => $command->argument('code'),
                'name'        => $command->argument('name'),
                'description' => $command->argument('description'),
                'type'        => $command->argument('type'),
            ]
        );

        return 0;
    }


}
