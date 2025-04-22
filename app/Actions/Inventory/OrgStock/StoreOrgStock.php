<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 08:52:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Search\OrgStockRecordSearch;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Enums\Goods\Stock\StockStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Goods\Stock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgStock extends OrgAction
{
    use WithNoStrictRules;

    private Stock $stock;

    /**
     * @throws \Throwable
     */
    public function handle(Organisation|OrgStockFamily $parent, Stock $stock, $modelData): OrgStock
    {
        if ($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
            data_set($modelData, 'org_stock_family_id', $parent->id);
        }

        data_set($modelData, 'group_id', $organisation->group_id);
        data_set($modelData, 'organisation_id', $organisation->id);
        data_set($modelData, 'code', $stock->code);
        data_set($modelData, 'name', $stock->name);
        data_set($modelData, 'unit_value', $stock->unit_value);

        $orgStock = DB::transaction(function () use ($stock, $modelData, $parent) {
            /** @var OrgStock $orgStock */
            $orgStock = $stock->orgStocks()->create($modelData);
            $orgStock->stats()->create();
            $orgStock->intervals()->create();
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                $orgStock->timeSeries()->create(['frequency' => $frequency]);
            }


            if ($parent instanceof OrgStockFamily) {
                $orgStock->orgStockFamily()->associate($parent);
                $orgStock->save();
            }

            $orgStock->refresh();

            return $orgStock;
        });


        OrganisationHydrateOrgStocks::dispatch($organisation)->delay($this->hydratorsDelay);
        if ($orgStock->orgStockFamily) {
            OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily)->delay($this->hydratorsDelay);
        }

        OrgStockRecordSearch::dispatch($orgStock);


        return $orgStock;
    }


    public function rules(ActionRequest $request): array
    {
        $rules = [
            'state'           => ['sometimes', Rule::enum(OrgStockStateEnum::class)],
            'quantity_status' => ['sometimes', 'nullable', Rule::enum(OrgStockQuantityStatusEnum::class)],
        ];

        if (!$this->strict) {
            $rules['discontinued_in_organisation_at'] = ['sometimes', 'nullable', 'date'];
            $rules                                    = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    public function prepareForValidation(): void
    {
        $state = match ($this->stock->state) {
            StockStateEnum::ACTIVE => OrgStockStateEnum::ACTIVE,
            StockStateEnum::DISCONTINUING => OrgStockStateEnum::DISCONTINUING,
            StockStateEnum::DISCONTINUED => OrgStockStateEnum::DISCONTINUED,
            StockStateEnum::SUSPENDED => OrgStockStateEnum::SUSPENDED,

            default => null
        };

        $this->set('state', $state);
    }

    /**
     * @throws \Throwable
     */
    public function action(Organisation|OrgStockFamily $parent, Stock $stock, $modelData = [], int $hydratorsDelay = 0, bool $strict = true, $audit = true): OrgStock
    {
        if (!$audit) {
            OrgStock::disableAuditing();
        }

        if ($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
        }

        $this->asAction       = true;
        $this->stock          = $stock;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisation($organisation, $modelData);

        return $this->handle($parent, $stock, $this->validatedData);
    }


    public function htmlResponse(Stock $stock): RedirectResponse
    {
        if (!$stock->stock_family_id) {
            return Redirect::route('grp.org.warehouses.show.inventory.org_stock_families.show.stocks.show', [
                $stock->stockFamily->slug,
                $stock->slug
            ]);
        } else {
            return Redirect::route('grp.org.warehouses.show.inventory.org-stocks.show', [
                $stock->slug
            ]);
        }
    }
}
