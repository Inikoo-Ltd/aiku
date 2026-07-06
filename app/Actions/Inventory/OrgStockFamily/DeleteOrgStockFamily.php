<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 Jan 2025 13:15:29 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockFamily;

use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrgStockFamilies;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStockFamilies;
use App\Models\Inventory\OrgStockFamily;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteOrgStockFamily extends OrgAction
{
    use AsController;
    use WithAttributes;

    /**
     * @throws \Throwable
     */
    public function handle(OrgStockFamily $orgStockFamily): void
    {
        DB::transaction(function () use ($orgStockFamily) {
            $orgStockFamily->stats()->forceDelete();
            $orgStockFamily->intervals()->forceDelete();
            $orgStockFamily->timeSeries()->forceDelete();


            DB::table('audits')->where('auditable_type', 'OrgStockFamily')->where('auditable_id', $orgStockFamily->id)->delete();


            DB::table('org_stock_movements')->where('org_stock_family_id', $orgStockFamily->id)->update(['org_stock_family_id' => null]);
            DB::table('delivery_note_items')->where('org_stock_family_id', $orgStockFamily->id)->update(['org_stock_family_id' => null]);

            foreach ($orgStockFamily->orgStocks as $orgStock) {
                UpdateOrgStock::make()->action($orgStock, ['org_stock_family_id' => null]);
            }

            $orgStockFamily->forceDelete();
        });

        OrganisationHydrateOrgStockFamilies::dispatch($this->organisation);
        GroupHydrateOrgStockFamilies::dispatch($this->organisation->group);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("inventory.{$this->organisation->id}.edit");
    }

    /**
     * @throws \Throwable
     */
    public function asController(Organisation $organisation, OrgStockFamily $orgStockFamily, ActionRequest $request): void
    {
        $this->initialisation($organisation, $request);

        $this->handle($orgStockFamily);
    }

    /**
     * @throws \Throwable
     */
    public function action(OrgStockFamily $orgStockFamily): void
    {
        $this->asAction = true;
        $this->initialisation($orgStockFamily->organisation, []);

        $this->handle($orgStockFamily);
    }


}
