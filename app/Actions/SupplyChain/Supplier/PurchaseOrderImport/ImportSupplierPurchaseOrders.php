<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-29 10:57
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\Supplier\PurchaseOrderImport;

use App\Actions\Helpers\Upload\StoreUpload;
use App\Actions\OrgAction;
use App\Actions\Procurement\PurchaseOrder\CalculatePurchaseOrderTotalAmounts;
use App\Actions\Procurement\PurchaseOrder\Hydrators\PurchaseOrderHydrateTransactions;
use App\Actions\Procurement\PurchaseOrder\StorePurchaseOrder;
use App\Actions\Procurement\PurchaseOrderTransaction\StorePurchaseOrderTransaction;
use App\Actions\Procurement\PurchaseOrderTransaction\UpdatePurchaseOrderTransaction;
use App\Enums\Helpers\Import\UploadRecordStatusEnum;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Helpers\Upload;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SysAdmin\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Throwable;

/**
 * Places the sheet's cartons on one draft purchase order per organisation: the organisation's open
 * draft with this supplier, or a new one. Drafts are not submitted, the buyer checks and sends
 * each one from its page. The file is kept as an upload so importing it again is noticed.
 */
class ImportSupplierPurchaseOrders extends OrgAction
{
    use WithSupplierPurchaseOrderImportAuthorisation;

    /**
     * @return array{upload_id: int, purchase_orders: list<array<string, mixed>>, skipped: int}
     * @throws ValidationException
     */
    public function handle(Supplier $supplier, UploadedFile $file, User $user, bool $importAgain): array
    {
        $plan = PlanSupplierPurchaseOrderImport::make()->handle($supplier, $file, $user);

        if ($plan['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $plan['errors']]);
        }
        if ($plan['organisations'] === []) {
            throw ValidationException::withMessages(['file' => __('Nothing to import: no row has cartons for a product this supplier sells.')]);
        }
        if ($plan['previous_imports'] !== [] && !$importAgain) {
            throw ValidationException::withMessages(['file' => __('This file was imported before. Confirm to import it again.')]);
        }

        $upload = StoreUpload::make()->fromFile($supplier->group, $file, [
            'model'       => PlanSupplierPurchaseOrderImport::UPLOAD_MODEL,
            'parent_type' => $supplier->getMorphClass(),
            'parent_id'   => $supplier->id,
        ]);
        $upload->update(['state' => UploadStateEnum::IMPORTING, 'uploaded_at' => now()]);

        $summary = [];
        $result  = [];
        $success = 0;
        $fails   = 0;
        foreach ($plan['organisations'] as $organisationPlan) {
            $outcome = $this->placeOrganisationLines($organisationPlan, $upload, $user);

            $success += $outcome['lines'];
            $fails   += count($outcome['errors']);

            $summary[$organisationPlan['key']] = [
                'organisation'      => $organisationPlan['organisation']['code'],
                'purchase_order'    => $outcome['purchase_order']?->reference,
                'purchase_order_id' => $outcome['purchase_order']?->id,
                'lines'             => $outcome['lines'],
                'errors'            => $outcome['errors'],
            ];
            $result[] = [
                'organisation'   => $organisationPlan['organisation']['code'],
                'reference'      => $outcome['purchase_order']?->reference,
                'url'            => $outcome['purchase_order'] ? $this->purchaseOrderUrl($outcome['purchase_order']) : null,
                'lines'          => $outcome['lines'],
                'errors'         => $outcome['errors'],
            ];
        }

        foreach ($plan['skipped'] as $skipped) {
            $upload->records()->create([
                'row_number' => $skipped['row'],
                'values'     => ['code' => $skipped['code'], 'cartons' => $skipped['cartons']],
                'errors'     => [$skipped['reason']],
                'status'     => UploadRecordStatusEnum::SKIPPED,
            ]);
        }

        $upload->update([
            'state'          => UploadStateEnum::IMPORTED,
            'number_rows'    => $success + $fails + count($plan['skipped']),
            'number_success' => $success,
            'number_fails'   => $fails + count($plan['skipped']),
            'data'           => [
                'file_hash'       => $plan['file']['hash'],
                'lines_hash'      => $plan['file']['lines_hash'],
                'purchase_orders' => $summary,
            ],
        ]);

        return ['upload_id' => $upload->id, 'purchase_orders' => $result, 'skipped' => count($plan['skipped'])];
    }

    /**
     * @param array<string, mixed> $organisationPlan
     *
     * @return array{purchase_order: ?PurchaseOrder, lines: int, errors: list<string>}
     */
    protected function placeOrganisationLines(array $organisationPlan, Upload $upload, User $user): array
    {
        try {
            return DB::transaction(function () use ($organisationPlan, $upload, $user) {
                /** @var OrgSupplier $orgSupplier */
                $orgSupplier = OrgSupplier::whereKey($organisationPlan['org_supplier_id'])->lockForUpdate()->firstOrFail();

                $purchaseOrder = $orgSupplier->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest('id')->first()
                    ?? StorePurchaseOrder::make()->action($orgSupplier, ['buyer_id' => $user->id]);
                $purchaseOrder->update(['data' => array_merge($purchaseOrder->data ?? [], ['from_upload_id' => $upload->id])]);

                $storeTransaction          = StorePurchaseOrderTransaction::make();
                $storeTransaction->batched = true;

                $lines  = 0;
                $errors = [];
                foreach ($organisationPlan['lines'] as $line) {
                    $error = null;
                    try {
                        DB::transaction(function () use ($purchaseOrder, $storeTransaction, $line) {
                            $transaction = $purchaseOrder->purchaseOrderTransactions()->where('supplier_product_id', $line['supplier_product_id'])->first();
                            if ($transaction) {
                                UpdatePurchaseOrderTransaction::make()->action($transaction, ['quantity_ordered' => $line['units']]);
                            } else {
                                $storeTransaction->addOrgSupplierProduct($purchaseOrder, OrgSupplierProduct::findOrFail($line['org_supplier_product_id']), ['quantity_ordered' => $line['units']]);
                            }
                        });
                        $lines++;
                    } catch (ValidationException $e) {
                        $error = collect($e->errors())->flatten()->implode(' ');
                    } catch (Throwable $e) {
                        Log::warning('Supplier purchase order import: '.$e->getMessage());
                        $error = $e->getMessage();
                    }

                    if ($error !== null) {
                        $errors[] = __('Row :row :code: :error', ['row' => $line['row'], 'code' => $line['code'], 'error' => $error]);
                    }

                    $upload->records()->create([
                        'row_number' => $line['row'],
                        'values'     => ['code' => $line['code'], 'organisation' => $organisationPlan['organisation']['code'], 'cartons' => $line['cartons'], 'units' => $line['units']],
                        'errors'     => $error === null ? [] : [$error],
                        'status'     => $error === null ? UploadRecordStatusEnum::COMPLETE : UploadRecordStatusEnum::FAILED,
                    ]);
                }

                CalculatePurchaseOrderTotalAmounts::run($purchaseOrder);
                PurchaseOrderHydrateTransactions::run($purchaseOrder);

                return ['purchase_order' => $purchaseOrder->refresh(), 'lines' => $lines, 'errors' => $errors];
            });
        } catch (ValidationException $e) {
            return ['purchase_order' => null, 'lines' => 0, 'errors' => collect($e->errors())->flatten()->all()];
        } catch (Throwable $e) {
            Log::warning('Supplier purchase order import: '.$e->getMessage());

            return ['purchase_order' => null, 'lines' => 0, 'errors' => [$e->getMessage()]];
        }
    }

    protected function purchaseOrderUrl(PurchaseOrder $purchaseOrder): string
    {
        return route('grp.org.procurement.org_suppliers.show.purchase-orders.show', [
            $purchaseOrder->organisation->slug,
            $purchaseOrder->parent->slug,
            $purchaseOrder->slug,
        ]);
    }

    public function rules(): array
    {
        return [
            'file'         => ['required', 'file', 'mimes:xlsx,xls'],
            'import_again' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{upload_id: int, purchase_orders: list<array<string, mixed>>, skipped: int}
     * @throws ValidationException
     */
    public function asController(Supplier $supplier, ActionRequest $request): array
    {
        $this->supplier = $supplier;
        $this->initialisationFromGroup($supplier->group, $request);

        return $this->handle($supplier, $this->validatedData['file'], $request->user(), (bool) ($this->validatedData['import_again'] ?? false));
    }
}
