<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 Jul 2026 13:46:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */


/** @noinspection DuplicatedCode */

namespace App\Actions\Maintenance\Inventory\OrgStockMovement;

use App\Actions\Inventory\OrgStock\Stock\Concerns\CalculatesOrgStockHistories;
use App\Actions\Inventory\OrgStockMovement\StoreOrgStockMovement;
use App\Actions\Traits\WithOrganisationSource;
use App\Enums\Inventory\OrgStockMovement\OrgStockMovementTypeEnum;
use App\Models\Inventory\LocationOrgStock;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockMovement;
use App\Models\SysAdmin\Organisation;
use App\Transfers\Aurora\WithAuroraParsers;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMigrationOrgStockMovementAuditFromAurora
{
    use AsAction;
    use WithOrganisationSource;
    use WithAuroraParsers;
    use CalculatesOrgStockHistories;


    /**
     * @throws \Exception
     * @throws \Throwable
     */
    public function handle(LocationOrgStock $locationOrgStock, string $date, Command $command): int
    {
        $organisation       = Organisation::where('slug', $locationOrgStock->organisation->slug)->firstOrFail();
        $organisationSource = $this->getOrganisationSource($organisation);
        $organisationSource->initialisation($organisation);


        $locationAuroraKey        = null;
        $auroraLocationSourceData = $locationOrgStock->location->source_id;
        if ($auroraLocationSourceData) {
            $sourceData        = explode(':', $auroraLocationSourceData);
            $locationAuroraKey = $sourceData[1];
        }

        $orgStockAuroraKey        = null;
        $auroraOrgStockSourceData = $locationOrgStock->orgStock->source_id;
        if ($auroraLocationSourceData) {
            $sourceData        = explode(':', $auroraOrgStockSourceData);
            $orgStockAuroraKey = $sourceData[1];
        }


        if ($locationAuroraKey && $orgStockAuroraKey) {
            $auroraData = DB::connection('aurora')
                ->table('Part Location Dimension')
                ->where('Location Key', $locationAuroraKey)
                ->where('Part SKU', $orgStockAuroraKey)
                ->first();

            if ($auroraData) {
                $quantity = $auroraData->{'Quantity On Hand'};


                $afterDateMovements = DB::connection('aurora')->table('Inventory Transaction Fact')
                    ->where('Location Key', $locationAuroraKey)
                    ->where('Part SKU', $orgStockAuroraKey)
                    ->where('Date', '>=', $date)
                    ->count();
                $delta              = 0;
                if ($afterDateMovements > 0) {
                    // $command->info(" *** Stock {$locationOrgStock->orgStock->slug} {$locationOrgStock->location->code}  : num movements: ".$afterDateMovements);


                    foreach (
                        DB::connection('aurora')->table('Inventory Transaction Fact')
                            ->where('Location Key', $locationAuroraKey)
                            ->where('Part SKU', $orgStockAuroraKey)
                            ->where('Date', '>=', $date)
                            ->get() as $movement
                    ) {
                        //   $command->info(" >>> Stock {$locationOrgStock->orgStock->slug} {$locationOrgStock->location->code}  : movement:   {$movement->{'Inventory Transaction Key'}}  {$movement->{'Date'}}  {$movement->{'Inventory Transaction Record Type'}}  ".$movement->{'Inventory Transaction Type'});

                        if ($movement->{'Inventory Transaction Type'} == 'Restock' || $movement->{'Inventory Transaction Type'} == 'AikuPick' || $movement->{'Inventory Transaction Type'} == 'Sale') {
                            $delta -= $movement->{'Inventory Transaction Quantity'};
                        } elseif ($movement->{'Inventory Transaction Type'} == 'In') {
                            $delta -= $movement->{'Inventory Transaction Quantity'};
                        } elseif ($movement->{'Inventory Transaction Type'} == 'No Dispatched' || $movement->{'Inventory Transaction Type'} == 'FailSale' ||  $movement->{'Inventory Transaction Type'} == 'Order In Process' || $movement->{'Inventory Transaction Type'} == 'Audit') {
                            //
                        } else {
                            dd($movement);
                        }
                    }
                    //    $command->info(" >>> Stock    {$locationOrgStock->orgStock->slug} {$locationOrgStock->location->code}  : ".$delta);


                }

                $fixedQuantity = $quantity + $delta;
                $command->info("Stock {$locationOrgStock->orgStock->slug} {$locationOrgStock->location->code}  : $quantity $delta -> $fixedQuantity ");


                $this->saveAudit($locationOrgStock, $date, $fixedQuantity);
            }
        }


        return 0;
    }


    public function saveAudit(LocationOrgStock $locationOrgStock, $date, $newQuantity)
    {
        if (OrgStockMovement::where('is_migration_point', true)
            ->where('location_id', $locationOrgStock->location_id)
            ->where('org_stock_id', $locationOrgStock->org_stock_id)
            ->exists()) {
            return;
        }


        StoreOrgStockMovement::make()->action(
            orgStock: $locationOrgStock->orgStock,
            location: $locationOrgStock->location,
            modelData: [
                'audited_quantity'   => $newQuantity,
                'date'               => Carbon::parse($date),
                'type'               => OrgStockMovementTypeEnum::AUDIT,
                'is_migration_point' => true,
                'org_amount'         => 0
            ],
            strict: false
        );
    }

    public string $commandSignature = 'migration_point {--s|org_stock_slug=} {--o|organisation=} ';

    public function asCommand(Command $command): int
    {
        $orgStockSlug     = $command->option('org_stock_slug');
        $organisationSlug = $command->option('organisation');
        $organisation     = null;

        if ($organisationSlug) {
            $organisation = Organisation::where('slug', $organisationSlug)->first();
        }

        $orgStocks = OrgStock::query();

        if ($orgStockSlug) {
            $orgStocks->where('slug', $orgStockSlug);
        }

        if ($organisation) {
            $orgStocks->where('organisation_id', $organisation->id);
        }


        $orgStocks
            ->chunkById(250, function ($orgStockChunk) use ($command) {
                foreach ($orgStockChunk as $orgStock) {
                    foreach ($orgStock->locations as $location) {
                        $locationOrgStock = LocationOrgStock::where('org_stock_id', $orgStock->id)->where('location_id', $location->id)->first();


                        if ($locationOrgStock) {
                            $organisation = $locationOrgStock->organisation;
                            if ($organisation->slug == 'es') {
                                $date = '2026-07-10 04:00:00';
                            } elseif ($organisation->slug == 'sk') {
                                $date = '2026-07-10 10:50:00';
                            } else {
                                abort('422');
                            }

                            if ($this->wasLocationValid($locationOrgStock->orgStock, $locationOrgStock->location, Carbon::parse($date), $command)) {
                                $this->handle($locationOrgStock, $date, $command);
                            }
                        } else {
                            dd('error 1');
                        }
                    }
                }
            });

        return 0;
    }

}
