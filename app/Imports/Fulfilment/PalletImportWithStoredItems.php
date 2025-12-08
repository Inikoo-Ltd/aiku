<?php

/*
 * author Arya Permana - Kirin
 * created on 13-02-2025-10h-00m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Imports\Fulfilment;

use App\Actions\Fulfilment\Pallet\StorePalletCreatedInPalletDelivery;
use App\Actions\Fulfilment\StoredItem\AttachStoredItemToPallet;
use App\Actions\Fulfilment\StoredItem\StoreStoredItem;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Imports\WithImport;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Helpers\Upload;
use App\Rules\IUnique;
use Exception;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;

class PalletImportWithStoredItems implements SkipsOnFailure, ToCollection, WithEvents, WithHeadingRow, WithValidation
{
    use WithImport;

    protected PalletDelivery $scope;

    public function __construct(PalletDelivery $palletDelivery, Upload $upload)
    {
        $this->upload = $upload;
        $this->scope = $palletDelivery;
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
        $convertedData = [
            'customer_reference' => $rowData['pallet_customer_reference'],
            'notes' => $rowData['pallet_notes'],
            'type' => $rowData['pallet_type'],
            'stored_item_reference' => $rowData['sku_reference'],
            'quantity' => $rowData['sku_quantity'],
            'stored_item_name' => $rowData['sku_name'],
        ];

        $currentCustomerRef = $convertedData['customer_reference'] ?? null;
        $prevPallet = $this->scope->pallets()->where('customer_reference', $currentCustomerRef)->first();
        $existingStoredItem = $this->scope->fulfilmentCustomer
            ->storedItems()
            ->where('reference', $convertedData['stored_item_reference'])
            ->first();

        if ($prevPallet) {
            $existingStoredItemInPallet = $existingStoredItem
                ? $prevPallet->storedItems()->where('stored_item_id', $existingStoredItem->id)->first()
                : null;

            if ($existingStoredItem && ! $existingStoredItemInPallet) {
                AttachStoredItemToPallet::run($prevPallet, $existingStoredItem, $convertedData['quantity']);
            } elseif (! $existingStoredItem) {
                $storedItemData = [
                    'reference' => $convertedData['stored_item_reference'],
                    'name' => $convertedData['stored_item_name'],
                ];

                $storedItem = StoreStoredItem::run($prevPallet, $storedItemData);
                AttachStoredItemToPallet::run($prevPallet, $storedItem, $convertedData['quantity']);
            }
        } else {
            $type = strtolower(str_replace(' ', '', trim($convertedData['type'])));

            $type = match ($type) {
                'oversize' => PalletTypeEnum::OVERSIZE->value,
                'box', 'carton' => PalletTypeEnum::BOX->value,
                default => PalletTypeEnum::PALLET->value,
            };

            data_set($modelData, 'data.bulk_import', [
                'id' => $this->upload->id,
                'type' => 'Upload',
            ]);

            $modelData = [
                'customer_reference' => $convertedData['customer_reference'],
                'notes' => $convertedData['notes'],
                'type' => $type,
            ];

            try {
                $pallet = StorePalletCreatedInPalletDelivery::run($this->scope, $modelData);

                $existingStoredItemInPallet = $existingStoredItem
                    ? $pallet->storedItems()->where('stored_item_id', $existingStoredItem->id)->first()
                    : null;

                if ($existingStoredItem && ! $existingStoredItemInPallet) {
                    AttachStoredItemToPallet::run($pallet, $existingStoredItem, $convertedData['quantity']);
                } elseif (! $existingStoredItem) {
                    $storedItemData = [
                        'reference' => $convertedData['stored_item_reference'],
                        'name' => $convertedData['stored_item_name'],
                    ];

                    $storedItem = StoreStoredItem::run($pallet, $storedItemData);
                    AttachStoredItemToPallet::run($pallet, $storedItem, $convertedData['quantity']);
                }

                $this->setRecordAsCompleted($uploadRecord);
            } catch (Exception|\Throwable $e) {
                $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'pallet_customer_reference' => [
                'sometimes',
                'nullable',
                'max:64',
                'string',
                Rule::notIn(['export', 'create', 'upload']),
                new IUnique(
                    table: 'pallets',
                    column: 'customer_reference',
                    extraConditions: [
                        ['column' => 'fulfilment_customer_id', 'value' => $this->scope->fulfilment_customer_id],
                    ]
                ),

            ],
            'pallet_notes' => ['nullable'],
            'pallet_type' => ['nullable'],
            'sku_reference' => ['nullable'],
            'sku_quantity' => ['nullable'],
            'sku_name' => ['nullable'],
        ];
    }
}
