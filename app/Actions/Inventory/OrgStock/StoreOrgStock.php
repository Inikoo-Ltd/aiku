<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 23 Jan 2024 08:52:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStock\Hydrators\OrgStockHydrateUniversalSearch;
use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Enums\SupplyChain\Stock\StockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SupplyChain\Stock;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreOrgStock extends OrgAction
{
    private Stock $stock;

    public function handle(Organisation|OrgStockFamily $parent, Stock $stock, $modelData): OrgStock
    {

        if($parent instanceof Organisation) {
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


        /** @var OrgStock $orgStock */
        $orgStock = $stock->orgStocks()->create($modelData);
        $orgStock->stats()->create(
            [
                'group_id'        => $organisation->group_id,
                'organisation_id' => $organisation->id,
            ]
        );


        if($parent instanceof OrgStockFamily) {
            $orgStock->orgStockFamily()->associate($parent);
            $orgStock->save();
        }


        OrgStockHydrateUniversalSearch::dispatch($orgStock);
        OrganisationHydrateOrgStocks::dispatch($organisation);
        if($orgStock->orgStockFamily) {
            OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily);
        }

        $orgStock->refresh();

        return $orgStock;
    }


    public function rules(ActionRequest $request): array
    {
        return [
            'state'           => ['required', Rule::enum(OrgStockStateEnum::class)],
            'quantity_status' => ['sometimes', 'nullable', Rule::enum(OrgStockQuantityStatusEnum::class)],
            'source_id'       => ['sometimes', 'nullable', 'string'],
        ];
    }


    public function prepareForValidation(): void
    {
        $state = match ($this->stock->state) {
            StockStateEnum::ACTIVE        => OrgStockStateEnum::ACTIVE,
            StockStateEnum::DISCONTINUING => OrgStockStateEnum::DISCONTINUING,
            StockStateEnum::DISCONTINUED  => OrgStockStateEnum::DISCONTINUED,
            StockStateEnum::SUSPENDED     => OrgStockStateEnum::SUSPENDED,
            default                       => null
        };


        $this->set('state', $state);
    }

    public function action(Organisation|OrgStockFamily $parent, Stock $stock, $modelData=[], $hydratorDelay = 0): OrgStock
    {

        if($parent instanceof Organisation) {
            $organisation = $parent;
        } else {
            $organisation = $parent->organisation;
        }

        $this->asAction       = true;
        $this->stock          = $stock;
        $this->hydratorsDelay = $hydratorDelay;
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
