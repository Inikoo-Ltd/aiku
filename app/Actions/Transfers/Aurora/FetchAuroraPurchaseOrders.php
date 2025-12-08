<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Apr 2023 17:11:07 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrder\UpdatePurchaseOrder;
use App\Models\Procurement\PurchaseOrder;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class FetchAuroraPurchaseOrders extends FetchAuroraAction
{
    use WithAuroraAttachments;

    public string $commandSignature = 'fetch:purchase_orders {organisations?*} {--s|source_id=} {--d|db_suffix=} {--N|only_new : Fetch only new} {--r|reset} {--w|with=* : Accepted values: transactions}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?PurchaseOrder
    {
        if ($purchaseOrderData = $organisationSource->fetchPurchaseOrder($organisationSourceId)) {
            if (empty($purchaseOrderData['org_parent'])) {
                echo 'No parent found for purchase order with source id: '.$purchaseOrderData['purchase_order']['source_id']."\n";

                return null;
            }

            if ($purchaseOrder = PurchaseOrder::withTrashed()->where('source_id', $purchaseOrderData['purchase_order']['source_id'])->first()) {
                try {
                    $purchaseOrder = UpdatePurchaseOrder::make()->action(
                        purchaseOrder: $purchaseOrder,
                        modelData: $purchaseOrderData['purchase_order'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $purchaseOrder->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $purchaseOrderData['purchase_order'], 'PurchaseOrder', 'update');

                    return null;
                }
            } else {
                try {
                    $purchaseOrder = StorePurchaseOrder::make()->action(
                        parent: $purchaseOrderData['org_parent'],
                        modelData: $purchaseOrderData['purchase_order'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    PurchaseOrder::enableAuditing();
                    $this->saveMigrationHistory(
                        $purchaseOrder,
                        Arr::except($purchaseOrderData['purchase_order'], ['fetched_at', 'last_fetched_at', 'source_id'])
                    );
                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $purchaseOrder->source_id);

                    DB::connection('aurora')->table('Purchase Order Dimension')
                        ->where('Purchase Order Key', $sourceData[1])
                        ->update(['aiku_id' => $purchaseOrder->id]);
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $purchaseOrderData['purchase_order'], 'PurchaseOrder', 'store');

                    return null;
                }
            }

            if (in_array('transactions', $this->with)) {
                $this->fetchTransactions($organisationSource, $purchaseOrder);
            }

            $this->processFetchAttachments($purchaseOrder, 'Purchase Order', $purchaseOrderData['purchase_order']['source_id']);
        }

        return null;
    }

    private function fetchTransactions($organisationSource, PurchaseOrder $purchaseOrder): void
    {
        $transactionsToDelete = $purchaseOrder->purchaseOrderTransactions()->pluck('source_id', 'id')->all();

        $sourceData = explode(':', $purchaseOrder->source_id);

        foreach (
            DB::connection('aurora')
                ->table('Purchase Order Transaction Fact')
                ->select('Purchase Order Transaction Fact Key')
                ->whereIn('Purchase Order Transaction Type', ['Parcel', 'Container'])
                ->where('Purchase Order Key', $sourceData[1])
                ->get() as $auroraData
        ) {
            $transactionsToDelete = array_diff($transactionsToDelete, [
                $purchaseOrder->organisation_id.':'.$auroraData->{'Purchase Order Transaction Fact Key'},
            ]);

            FetchAuroraPurchaseOrderTransactions::run($organisationSource, $auroraData->{'Purchase Order Transaction Fact Key'}, $purchaseOrder);
        }
        $purchaseOrder->purchaseOrderTransactions()->whereIn('id', array_keys($transactionsToDelete))->delete();
    }

    public function getModelsQuery(): Builder
    {
        $query = DB::connection('aurora')
            ->table('Purchase Order Dimension')
            ->select('Purchase Order Key as source_id')
            ->whereIn('Purchase Order Type', ['Parcel', 'Container']);
        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        $query->orderBy('Purchase Order Date');

        return $query;
    }

    public function count(): ?int
    {
        $query = DB::connection('aurora')->table('Purchase Order Dimension')
            ->whereIn('Purchase Order Type', ['Parcel', 'Container']);

        if ($this->onlyNew) {
            $query->whereNull('aiku_id');
        }

        return $query->count();
    }

    public function reset(): void
    {
        DB::connection('aurora')->table('Purchase Order Dimension')->update(['aiku_id' => null]);
    }
}
