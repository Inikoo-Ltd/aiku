<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-10h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Fulfilment\StoredItem\UpdateStoredItem;
use App\Imports\WithImport;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Fulfilment\StoredItem;
use App\Models\Helpers\Upload;
use Exception;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class CustomerSalesChannelPortfoliosImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    public CustomerSalesChannel $customerSalesChannel;

    public function __construct(CustomerSalesChannel $customerSalesChannel, Upload $upload)
    {
        $this->customerSalesChannel = $customerSalesChannel;
        $this->upload = $upload;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $fields =
            array_merge(
                array_keys(
                    $this->rules()
                )
            );

        $rowData = $row->only($fields)->all();

        $modelData = [
            'sku' => $rowData['sku'],
            'customer_product_name' => $rowData['title']
        ];

        try {
            $product = Product::where('slug', $modelData['sku'])->first();

            if (! Portfolio::where('customer_sales_channel_id', $this->customerSalesChannel->id)
                ->where('item_id', $product->id)
                ->where('item_type', $product->getMorphClass())
                ->exists()) {

                StorePortfolio::make()->action($this->customerSalesChannel, $product, $modelData);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Exception|\Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                Rule::exists('products', 'slug')
            ],
            'title'                    => ['nullable']
        ];
    }
}
