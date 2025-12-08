<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 May 2023 14:50:49 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\StockDelivery\StoreStockDelivery;
use App\Actions\Procurement\StockDelivery\UpdateStockDelivery;
use App\Models\Procurement\StockDelivery;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraStockDeliveries extends FetchAuroraAction
{
    use WithAuroraAttachments;

    public string $commandSignature = 'fetch:stock_deliveries {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} {--w|with=* : Accepted values: transactions}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?StockDelivery
    {
        if ($stockDeliveryData = $organisationSource->fetchStockDelivery($organisationSourceId)) {
            if (empty($stockDeliveryData['org_parent'])) {
                echo "Warning Supplier Delivery $organisationSourceId do not have parent, skipping\n";

                return null;
            }

            if ($stockDelivery = StockDelivery::withTrashed()->where('source_id', $stockDeliveryData['stockDelivery']['source_id'])->first()) {
                try {
                    $stockDelivery = UpdateStockDelivery::make()->action(
                        $stockDelivery,
                        $stockDeliveryData['stockDelivery'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $stockDelivery->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $stockDeliveryData['stockDelivery'], 'StockDelivery', 'update');

                    return null;
                }
            } else {
                try {
                    $stockDelivery = StoreStockDelivery::make()->action(
                        $stockDeliveryData['org_parent'],
                        $stockDeliveryData['stockDelivery'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    StockDelivery::enableAuditing();
                    $this->saveMigrationHistory(
                        $stockDelivery,
                        Arr::except($stockDeliveryData['stockDelivery'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $stockDelivery->source_id);
                    DB::connection('aurora')->table('Supplier Delivery Dimension')
                        ->where('Supplier Delivery Key', $sourceData[1])
                        ->update(['aiku_id' => $stockDelivery->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $stockDeliveryData['stockDelivery'], 'StockDelivery', 'store');

                    return null;
                }
            }

            $this->processFetchAttachments($stockDelivery, 'Supplier Delivery', $stockDeliveryData['stockDelivery']['source_id']);

            if (in_array('transactions', $this->with) or in_array('full', $this->with)) {
                $this->fetchTransactions($organisationSource, $stockDelivery);
            }

            return $stockDelivery;
        }

        return null;
    }

    private function fetchTransactions($organisationSource, StockDelivery $stockDelivery): void
    {
        $transactionsToDelete = $stockDelivery->items()->pluck('source_id', 'id')->all();

        $sourceData = explode(':', $stockDelivery->source_id);

        foreach (
            DB::connection('aurora')
                ->table('Purchase Order Transaction Fact')
                ->select('Purchase Order Transaction Fact Key')
                ->whereIn('Purchase Order Transaction Type', ['Parcel', 'Container'])
                ->where('Supplier Delivery Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $transactionsToDelete = array_diff($transactionsToDelete, [
                $stockDelivery->organisation_id.':'.$auroraData->{'Purchase Order Transaction Fact Key'},
            ]);

            FetchAuroraStockDeliveryItems::run($organisationSource, $auroraData->{'Purchase Order Transaction Fact Key'}, $stockDelivery);
        }
        $stockDelivery->items()->whereIn('id', array_keys($transactionsToDelete))->delete();
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Supplier Delivery Dimension')
            ->select('Supplier Delivery Key as source_id');
        $query->orderBy('Supplier Delivery Date');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Supplier Delivery Dimension');

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }
}
