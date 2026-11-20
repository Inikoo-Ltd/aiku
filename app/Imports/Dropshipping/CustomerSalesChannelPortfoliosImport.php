<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-10h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\Dropshipping;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\Dropshipping\Portfolio\UpdatePortfolio;
use App\Imports\WithImport;
use App\Models\Catalogue\Product;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Upload;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Throwable;

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

        $sku = Arr::get($rowData, 'sku');

        if (! $sku) {
            $this->setRecordAsFailed($uploadRecord, ['The row does not contain a valid sku.']);

            return;
        }

        $modelData = [
            'sku' => $sku
        ];

        try {
            $product = Product::where('shop_id', $this->customerSalesChannel->shop_id)
                ->whereRaw('lower(code) = lower(?)', [$modelData['sku']])
                ->orderByDesc('is_for_sale')
                ->orderByRaw('code = ? desc', [$modelData['sku']])
                ->orderByRaw("case state when 'active' then 0 when 'discontinuing' then 1 else 2 end")
                ->orderByDesc('id')
                ->first();

            if (! $product) {
                throw ValidationException::withMessages(['sku' => 'SKU not found in this shop.']);
            }

            if (! $product->is_for_sale) {
                throw ValidationException::withMessages(['sku' => 'Product is not for sale.']);
            }

            $portfolio = Portfolio::where('customer_sales_channel_id', $this->customerSalesChannel->id)
                ->where('item_id', $product->id)
                ->where('item_type', $product->getMorphClass())
                ->first();
            if (! $portfolio) {
                StorePortfolio::make()->action($this->customerSalesChannel, $product, Arr::except($rowData, ['sku', 'title']));
            } else {
                UpdatePortfolio::make()->action($portfolio, [
                    'customer_product_name' => $product->name,
                    'item_name' => $product->name
                ]);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes',
                'required',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
            ],
            'title'                    => ['nullable'],
            'platform_status'          => ['nullable'],
            'platform_product_id'      => ['nullable'],
            'platform_product_variant_id' => ['nullable'],
            'has_valid_platform_product_id' => ['nullable'],
            'exist_in_platform' => ['nullable']
        ];
    }
}
