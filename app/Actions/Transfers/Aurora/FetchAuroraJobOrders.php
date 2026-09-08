<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 08 Aug 2026 20:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Production\JobOrder\StoreJobOrder;
use App\Actions\Production\JobOrder\UpdateJobOrder;
use App\Actions\Production\JobOrderItem\StoreJobOrderItem;
use App\Actions\Production\JobOrderItem\UpdateJobOrderItem;
use App\Models\Production\Artefact;
use App\Models\Production\JobOrder;
use App\Models\Production\JobOrderItem;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FetchAuroraJobOrders extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:job_orders {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} {--r|reset}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?JobOrder
    {
        $data = $organisationSource->fetchJobOrder($organisationSourceId);
        if (!$data || empty($data['job_order'])) {
            return null;
        }

        $modelData = $data['job_order'];

        if ($jobOrder = JobOrder::withTrashed()->where('source_id', $modelData['source_id'])->first()) {
            try {
                $jobOrder = UpdateJobOrder::make()->action(
                    organisation: $jobOrder->organisation,
                    jobOrder: $jobOrder,
                    modelData: $modelData
                );
                $this->recordChange($organisationSource, $jobOrder->wasChanged());
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'JobOrder', 'update');

                return null;
            }
        } else {
            try {
                $jobOrder = StoreJobOrder::make()->action(
                    production: $data['production'],
                    modelData: $modelData
                );
                $this->recordNew($organisationSource);

                $sourceData = explode(':', $jobOrder->source_id);
                DB::connection('aurora')->table('Purchase Order Dimension')
                    ->where('Purchase Order Key', $sourceData[1])
                    ->update(['aiku_job_order_id' => $jobOrder->id]);
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $modelData, 'JobOrder', 'store');

                return null;
            }
        }

        $this->fetchJobOrderItems($organisationSource, $jobOrder);

        return $jobOrder;
    }

    private function fetchJobOrderItems(SourceOrganisationService $organisationSource, JobOrder $jobOrder): void
    {
        $organisation = $organisationSource->organisation;
        $sourceData   = explode(':', $jobOrder->source_id);

        foreach (
            DB::connection('aurora')
                ->table('Purchase Order Transaction Fact')
                ->where('Purchase Order Key', $sourceData[1])
                ->get() as $auroraItem
        ) {
            $artefact = Artefact::withTrashed()
                ->where('source_id', $organisation->id.':'.$auroraItem->{'Supplier Part Key'})
                ->first();
            if (!$artefact) {
                print "Error No artefact found for job order item ".$auroraItem->{'Purchase Order Transaction Fact Key'}." (fetch artefacts first)\n";
                continue;
            }

            $quantity = (int)round($auroraItem->{'Purchase Order Ordering Units'} ?? 0);
            if ($quantity < 1) {
                continue;
            }

            $itemData = [
                'quantity'  => $quantity,
                'state'     => $jobOrder->state->value,
                'source_id' => $organisation->id.':'.$auroraItem->{'Purchase Order Transaction Fact Key'},
            ];

            try {
                if ($jobOrderItem = JobOrderItem::withTrashed()->where('source_id', $itemData['source_id'])->first()) {
                    UpdateJobOrderItem::make()->action(
                        jobOrderItem: $jobOrderItem,
                        modelData: $itemData
                    );
                } else {
                    StoreJobOrderItem::make()->action(
                        jobOrder: $jobOrder,
                        modelData: array_merge($itemData, ['artefact_id' => $artefact->id])
                    );
                }
            } catch (Exception $e) {
                $this->recordError($organisationSource, $e, $itemData, 'JobOrderItem', 'store');
            }
        }
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->select('Purchase Order Key as source_id')
            ->where('Purchase Order Type', 'Production');
        if ($this->onlyNew) {
            $query->whereNull('aiku_job_order_id');
        }

        $query->orderBy('Purchase Order Creation Date');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->where('Purchase Order Type', 'Production');
        if ($this->onlyNew) {
            $query->whereNull('aiku_job_order_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Purchase Order Dimension')
            ->where('Purchase Order Type', 'Production')
            ->update(['aiku_job_order_id' => null]);
    }
}
