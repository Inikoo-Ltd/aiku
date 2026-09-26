<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 14:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Imports\Procurement;

use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Imports\WithImport;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Throwable;

class PurchaseOrderTransactionImport implements ToCollection, WithHeadingRow, SkipsOnFailure, WithValidation, WithEvents
{
    use WithImport;

    public function __construct(protected PurchaseOrder $purchaseOrder, Upload $upload)
    {
        $this->upload = $upload;
    }

    public function storeModel($row, $uploadRecord): void
    {
        $code     = trim((string) $row->get('code'));
        $quantity = (float) $row->get('quantity');

        $orgSupplierProduct = $this->findOrgSupplierProduct($code);

        if (!$orgSupplierProduct) {
            $this->setRecordAsFailed($uploadRecord, [__('Product :code is not supplied by :parent', ['code' => $code, 'parent' => $this->purchaseOrder->parent_name])]);

            return;
        }

        try {
            $transaction = $this->purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $orgSupplierProduct->supplier_product_id)->first();

            if ($transaction) {
                UpdatePurchaseOrderTransaction::make()->action($transaction, ['quantity_ordered' => $quantity]);
            } else {
                StorePurchaseOrderTransaction::make()->addOrgSupplierProduct($this->purchaseOrder, $orgSupplierProduct, ['quantity_ordered' => $quantity]);
            }

            $this->setRecordAsCompleted($uploadRecord);
        } catch (ValidationException $e) {
            $this->setRecordAsFailed($uploadRecord, collect($e->errors())->flatten()->all());
        } catch (Throwable $e) {
            $this->setRecordAsFailed($uploadRecord, [$e->getMessage()]);
        }
    }

    private function findOrgSupplierProduct(string $code): ?OrgSupplierProduct
    {
        $parentColumn = match ($this->purchaseOrder->parent_type) {
            'OrgSupplier' => 'org_supplier_id',
            'OrgAgent'    => 'org_agent_id',
            default       => null,
        };

        if (!$parentColumn || $code === '') {
            return null;
        }

        return OrgSupplierProduct::where('org_supplier_products.organisation_id', $this->purchaseOrder->organisation_id)
            ->where('org_supplier_products.'.$parentColumn, $this->purchaseOrder->parent_id)
            ->join('supplier_products', 'supplier_products.id', 'org_supplier_products.supplier_product_id')
            ->whereRaw('lower(supplier_products.code) = lower(?)', [$code])
            ->orderByRaw("org_supplier_products.state = 'active' desc")
            ->select('org_supplier_products.*')
            ->first();
    }

    public function rules(): array
    {
        return [
            'code'     => ['required', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
