<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 23 Mar 2024 12:24:25 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\StockFamily;

use App\Actions\Goods\Stock\UpdateStock;
use App\Actions\Inventory\OrgStockFamily\DeleteOrgStockFamily;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateStockFamilies;
use App\Actions\Traits\Authorisations\WithGoodsEditAuthorisation;
use App\Models\Goods\StockFamily;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Throwable;

class DeleteStockFamily extends OrgAction
{
    use AsController;
    use WithAttributes;
    use WithGoodsEditAuthorisation;

    /**
     * @throws \Throwable
     */
    public function handle(StockFamily $stockFamily): StockFamily
    {
        DB::transaction(function () use ($stockFamily) {
            $stockFamily->stats()->delete();
            $stockFamily->intervals()->delete();
            $stockFamily->salesIntervals()->delete();
            $stockFamily->timeSeries()->delete();

            DB::table('audits')->where('auditable_type', 'StockFamily')->where('auditable_id', $stockFamily->id)->delete();

            DB::table('org_stock_movements')->where('stock_family_id', $stockFamily->id)->update(['stock_family_id' => null]);
            DB::table('delivery_note_items')->where('stock_family_id', $stockFamily->id)->update(['stock_family_id' => null]);
            DB::table('org_stock_families')->where('stock_family_id', $stockFamily->id)->update(['stock_family_id' => null]);

            foreach ($stockFamily->orgStockFamilies as $orgStockFamily) {
                DeleteOrgStockFamily::make()->action($orgStockFamily);
            }

            foreach ($stockFamily->stocks as $stock) {
                UpdateStock::make()->action($stock, ['stock_family_id' => null]);
            }

            $stockFamily->forceDelete();

        });

        GroupHydrateStockFamilies::dispatch($this->group);


        return $stockFamily;
    }

    /**
     * @throws \Throwable
     */
    public function asController(StockFamily $stockFamily, ActionRequest $request): StockFamily
    {
        $this->initialisationFromGroup($stockFamily->group, $request);

        return $this->handle($stockFamily);
    }

    /**
     * @throws \Throwable
     */
    public function action(StockFamily $stockFamily): StockFamily
    {
        $this->asAction = true;
        $this->initialisationFromGroup($stockFamily->group, []);

        return $this->handle($stockFamily);
    }

    public function getCommandSignature(): string
    {
        return 'delete:stock_family {id}';
    }

    public function asCommand(Command $command): int
    {

        $stockFamily = StockFamily::withTrashed()->where('id', $command->argument('id'))->first();

        if (!$stockFamily) {
            $command->error("Stock Family not found");

            return 1;
        }

        $stockFamilyCode = $stockFamily->code;
        $this->asAction = true;
        $this->initialisationFromGroup($stockFamily->group, []);
        try {
            $this->handle($stockFamily);
        } catch (Throwable $exception) {
            $command->error("Error deleting Stock Family ".$exception->getMessage());

            return 1;
        }

        $command->info("Stock Family $stockFamilyCode deleted");

        return 0;
    }


}
