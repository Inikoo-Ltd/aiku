<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesCustomersStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Catalogue\CollectionTimeSeries\PreprocessCollectionTimeSeries;
use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Actions\Masters\MasterAssetTimeSeries\ProcessMasterAssetTimeSeriesRecords;
use App\Actions\Masters\MasterProductCategoryTimeSeries\ProcessMasterProductCategoryTimeSeriesRecords;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Billables\Service;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;

class StoreInvoiceTransaction extends OrgAction
{
    use WithOrderExchanges;
    use WithNoStrictRules;

    public function handle(Invoice $invoice, Transaction|HistoricAsset $model, array $modelData): InvoiceTransaction
    {
        data_set($modelData, 'date', now(), overwrite: false);

        if ($model instanceof Transaction) {
            data_set($modelData, 'model_type', $model->model_type);
            data_set($modelData, 'model_id', $model->model_id);
        } else {
            data_set($modelData, 'model_type', $model->asset->model_type);
            data_set($modelData, 'model_id', $model->asset->model_id);
        }


        $modelData['shop_id']         = $invoice->shop_id;
        $modelData['customer_id']     = $invoice->customer_id;
        $modelData['group_id']        = $invoice->group_id;
        $modelData['organisation_id'] = $invoice->organisation_id;
        $modelData['is_refund']       = $invoice->type === InvoiceTypeEnum::REFUND;


        $modelData = $this->processExchanges($modelData, $invoice->shop);


        if ($model instanceof Transaction) {
            $modelData['transaction_id']    = $model->id;
            $modelData['order_id']          = $model->order_id;
            $modelData['asset_id']          = $model->asset_id;
            $modelData['historic_asset_id'] = $model->historic_asset_id;
            if ($this->strict) {
                $historicAsset = $model->historicAsset;
            } else {
                $historicAsset = $model->getHistoricAssetWithTrashed();
            }
        } else {
            $historicAsset                  = $model;
            $modelData['asset_id']          = $historicAsset->asset_id;
            $modelData['historic_asset_id'] = $historicAsset->id;
        }

        if ($historicAsset) {
            if ($historicAsset->model_type == 'Product') {
                /** @var Product $product */
                $product = $historicAsset->model;

                $modelData['family_id']         = $product->family_id;
                $modelData['department_id']     = $product->department_id;
                $modelData['sub_department_id'] = $product->sub_department_id;

                if ($masterProduct = $product->masterProduct) {
                    $modelData['master_shop_id']           = $masterProduct->master_shop_id;
                    $modelData['master_department_id']     = $masterProduct->master_department_id;
                    $modelData['master_sub_department_id'] = $masterProduct->master_sub_department_id;
                    $modelData['master_family_id']         = $masterProduct->master_family_id;
                }
            } elseif ($historicAsset->model_type == 'Service' && $invoice->shop->type == ShopTypeEnum::FULFILMENT) {
                $modelData = $this->processFulfilmentService($historicAsset->model, $modelData);
            }
        }

        /** @var InvoiceTransaction $invoiceTransaction */
        $invoiceTransaction = $invoice->invoiceTransactions()->create($modelData);

        if ($invoiceTransaction->order_id && $invoiceTransaction->transaction_id) {
            $invoiceTransaction->transaction->update([
                'invoice_id' => $invoice->id
            ]);
        }

        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        if ($invoiceTransaction->asset_id) {
            AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay(1800);
            AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay(1800);
            AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay(1800);
            AssetHydrateInvoicesCustomersStats::dispatch($invoiceTransaction->asset_id)->delay(1800);
            AssetHydrateInvoicesStats::dispatch($invoiceTransaction->asset_id)->delay(1800);
        }


        if ($invoiceTransaction->asset_id) {
            PreprocessCollectionTimeSeries::dispatch($invoiceTransaction->asset_id)->delay(30);

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->asset_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->family_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->family_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->sub_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->sub_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->master_asset_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_asset_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->master_family_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_family_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->master_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }

        if ($invoiceTransaction->master_sub_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_sub_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => now()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => now()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => now()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => now()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => now()->toDateString()
                    },
                    now()->toDateString()
                )->delay(1800);
            }
        }


        return $invoiceTransaction;
    }

    protected function processFulfilmentService(Service $service, array $modelData): array
    {
        if (Arr::exists($modelData, 'pallet_id')) {
            $palletId = Arr::pull($modelData, 'pallet_id');
        } else {
            $palletId = Arr::pull($modelData, 'data.handling_service_pallet_id');
        }

        if (Arr::exists($modelData, 'handle_date')) {
            $handlingDate = Arr::pull($modelData, 'handle_date');
        } else {
            $handlingDate = Arr::pull($modelData, 'data.handling_service_date');
        }

        if ($service->is_pallet_handling) {
            if ($palletId) {
                data_set($modelData, 'data.handling_service_pallet_id', $palletId);
            }
            if ($handlingDate) {
                data_set($modelData, 'data.handling_service_date', $handlingDate);
            }
        }

        return $modelData;
    }

    public function rules(): array
    {
        $rules = [
            'date'                          => ['sometimes', 'required', 'date'],
            'tax_category_id'               => ['required', 'exists:tax_categories,id'],
            'quantity'                      => ['required', 'numeric'],
            'gross_amount'                  => ['required', 'numeric'],
            'net_amount'                    => ['required', 'numeric'],
            'org_exchange'                  => ['sometimes', 'numeric'],
            'grp_exchange'                  => ['sometimes', 'numeric'],
            'in_process'                    => ['sometimes', 'boolean'],
            'pallet_id'                     => ['sometimes'],
            'handle_date'                   => ['sometimes'],
            'data'                          => ['sometimes', 'array'],
            'recurring_bill_transaction_id' => ['sometimes'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }


    public function action(Invoice $invoice, Transaction|HistoricAsset $model, array $modelData, int $hydratorsDelay = 0, bool $strict = true): InvoiceTransaction
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoice->shop, $modelData);

        return $this->handle($invoice, $model, $this->validatedData);
    }


}
