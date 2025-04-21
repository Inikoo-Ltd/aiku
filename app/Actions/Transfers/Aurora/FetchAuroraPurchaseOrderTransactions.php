<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Sept 2024 14:28:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Enums\Transfers\FetchRecord\FetchRecordTypeEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class FetchAuroraPurchaseOrderTransactions
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, PurchaseOrder $purchaseOrder): ?PurchaseOrderTransaction
    {
        $transactionData = $organisationSource->fetchPurchaseOrderTransaction(id: $organisationSourceId, purchaseOrder: $purchaseOrder);


        if ($transactionData) {
            if ($purchaseOrderTransaction = PurchaseOrderTransaction::where('source_id', $transactionData['purchase_order_transaction']['source_id'])->first()) {
                try {
                    $purchaseOrderTransaction = UpdatePurchaseOrderTransaction::make()->action(
                        purchaseOrderTransaction: $purchaseOrderTransaction,
                        modelData: $transactionData['purchase_order_transaction'],
                        hydratorsDelay: 5,
                        strict: false,
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $transactionData['purchase_order_transaction'], 'PurchaseOrderTransaction', 'update');

                    return null;
                }
            } else {
                try {
                    $purchaseOrderTransaction = StorePurchaseOrderTransaction::make()->action(
                        purchaseOrder: $purchaseOrder,
                        historicSupplierProduct: $transactionData['historic_supplier_product'],
                        orgStock: $transactionData['org_stock'],
                        modelData: $transactionData['purchase_order_transaction'],
                        hydratorsDelay: 5,
                        strict: false
                    );

                    $sourceData = explode(':', $purchaseOrderTransaction->source_id);
                    DB::connection('aurora')->table('Purchase Order Transaction Fact')
                        ->where('Purchase Order Transaction Fact Key', $sourceData[1])
                        ->update(['aiku_po_id' => $purchaseOrderTransaction->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $transactionData['historic_supplier_product'], 'PurchaseOrderTransaction', 'store');

                    return null;
                }
            }

            return $purchaseOrderTransaction;
        }

        return null;
    }

    protected function recordError(SourceOrganisationService $organisationSource, Exception $e, array $modelData, $modelType, $errorOn): void
    {
        $organisationSource->fetch->records()->create([
            'model_data' => $modelData,
            'data'       => $e->getMessage(),
            'type'       => FetchRecordTypeEnum::ERROR,
            'source_id'  => Arr::get($modelData, 'source_id'),
            'model_type' => $modelType,
            'error_on'   => $errorOn
        ]);
    }

}
