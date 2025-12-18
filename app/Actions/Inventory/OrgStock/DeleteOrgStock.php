<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 17 Dec 2025 21:07:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Actions\Inventory\OrgStockFamily\Hydrators\OrgStockFamilyHydrateOrgStocks;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateOrgStocks;
use App\Models\Inventory\OrgStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\ActionRequest;

class DeleteOrgStock extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock): OrgStock
    {
        $deletionCheck = $this->canDelete($orgStock);
        if ($deletionCheck['canDelete'] === false) {
            abort(409, 'Cannot delete Org Stock, it is used in: '.implode(', ', $deletionCheck['used_in']));
        }

        DB::transaction(function () use ($orgStock) {
            $orgStock->stats()?->delete();
            $orgStock->intervals()?->delete();
            $orgStock->salesIntervals()?->delete();
            $orgStock->timeSeries()->delete();

            // Detach pivots
            $orgStock->products()->detach();
            $orgStock->tradeUnits()->detach();
            $orgStock->orgSupplierProducts()->detach();

            // Remove location links and movements
            $orgStock->locationOrgStocks()->delete();
            DB::table('org_stock_movements')->where('org_stock_id', $orgStock->id)->delete();

            // Null external references where applicable
            if (Schema::hasTable('delivery_note_items')) {
                DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->update(['org_stock_id' => null]);
            }
            if (Schema::hasTable('pickings')) {
                DB::table('pickings')->where('org_stock_id', $orgStock->id)->update(['org_stock_id' => null]);
            }

            // Delete audits for this model
            DB::table('audits')->where('auditable_type', 'OrgStock')->where('auditable_id', $orgStock->id)->delete();

            $orgStock->forceDelete();
        });

        OrganisationHydrateOrgStocks::dispatch($orgStock->organisation);
        if ($orgStock->orgStockFamily) {
            OrgStockFamilyHydrateOrgStocks::dispatch($orgStock->orgStockFamily);
        }

        return $orgStock;
    }

    /**
     * @throws \Throwable
     */
    public function asController(OrgStock $orgStock, ActionRequest $request): OrgStock
    {
        $this->initialisation($orgStock->organisation, $request);

        return $this->handle($orgStock);
    }

    /**
     * @throws \Throwable
     */
    public function action(OrgStock $orgStock): OrgStock
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, []);

        return $this->handle($orgStock);
    }

    public function getCommandSignature(): string
    {
        return 'delete:org_stock {id}';
    }

    public function asCommand(Command $command): int
    {
        if (is_numeric($command->argument('id'))) {
            $orgStock = OrgStock::withTrashed()->where('id', $command->argument('id'))->first();
        } else {
            $orgStock = OrgStock::withTrashed()->where('slug', $command->argument('id'))->first();
        }


        if (!$orgStock) {
            $command->error('Org Stock not found');

            return 1;
        }

        $deletionCheck = $this->canDelete($orgStock);
        if ($deletionCheck['canDelete'] === false) {
            $command->error('Cannot delete Org Stock, it is used in: '.implode(', ', $deletionCheck['used_in']));

            return 1;
        }

        $code           = $orgStock->code;
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, []);

        try {
            $this->handle($orgStock);
        } catch (\Throwable $e) {
            $command->error('Error deleting Org Stock '.$e->getMessage());

            return 1;
        }

        $command->info("Org Stock $code deleted");

        return 0;
    }

    protected function canDelete(OrgStock $orgStock): array
    {
        $usedIn = [];

        if ($orgStock->locationOrgStocks()->exists()) {
            $usedIn[] = 'locationOrgStocks';
        }

        if ($orgStock->products()->exists()) {
            $usedIn[] = 'products';
        }

        $exists = DB::table('delivery_note_items')->where('org_stock_id', $orgStock->id)->exists();
        if ($exists) {
            $usedIn[] = 'delivery_note_items';
        }


        $exists = DB::table('pickings')->where('org_stock_id', $orgStock->id)->exists();
        if ($exists) {
            $usedIn[] = 'pickings';
        }


        return [
            'canDelete' => count($usedIn) === 0,
            'used_in'   => $usedIn,
        ];
    }
}
