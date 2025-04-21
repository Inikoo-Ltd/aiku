<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 12:29:28 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Procurement\StockDeliveryItem\StoreStockDeliveryItem;
use App\Actions\Procurement\StockDeliveryItem\UpdateStockDeliveryItem;
use App\Enums\Transfers\FetchRecord\FetchRecordTypeEnum;
use App\Models\Procurement\StockDelivery;
use App\Models\Procurement\StockDeliveryItem;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class FetchAuroraStockDeliveryItems
{
    use AsAction;

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId, StockDelivery $stockDelivery): ?StockDeliveryItem
    {
        $transactionData = $organisationSource->fetchStockDeliveryItem(id: $organisationSourceId, stockDelivery: $stockDelivery);


        if ($transactionData) {
            if ($stockDeliveryItem = StockDeliveryItem::where('source_id', $transactionData['stock_delivery_item']['source_id'])->first()) {
                try {
                    $stockDeliveryItem = UpdateStockDeliveryItem::make()->action(
                        stockDeliveryItem: $stockDeliveryItem,
                        modelData: $transactionData['stock_delivery_item'],
                        hydratorsDelay: 5,
                        strict: false,
                    );
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $transactionData['stock_delivery_item'], 'PurchaseOrderTransaction', 'update');

                    return null;
                }
            } else {
                try {
                    $stockDeliveryItem = StoreStockDeliveryItem::make()->action(
                        stockDelivery: $stockDelivery,
                        historicSupplierProduct: $transactionData['historic_supplier_product'],
                        orgStock: $transactionData['org_stock'],
                        modelData: $transactionData['stock_delivery_item'],
                        hydratorsDelay: 5,
                        strict: false
                    );

                    $sourceData = explode(':', $stockDeliveryItem->source_id);
                    DB::connection('aurora')->table('Purchase Order Transaction Fact')
                        ->where('Purchase Order Transaction Fact Key', $sourceData[1])
                        ->update(['aiku_sd_id' => $stockDeliveryItem->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $transactionData['historic_supplier_product'], 'PurchaseOrderTransaction', 'store');

                    return null;
                }
            }

            return $stockDeliveryItem;
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
