<?php

namespace App\Actions\SupplyChain\SupplierProduct\Upload\UI;

use App\Actions\InertiaAction;
use App\Actions\SupplyChain\SupplierProduct\Upload\CheckSupplierProductSheet;
use App\Actions\SupplyChain\SupplierProduct\Upload\ImportSupplierProductUpload;
use App\Actions\SupplyChain\Supplier\UI\ShowSupplier;
use App\Actions\Traits\Authorisations\WithSupplyChainAuthorisation;
use App\Enums\Helpers\Import\UploadStateEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Helpers\Upload;
use App\Models\Helpers\UploadRecord;
use App\Models\SupplyChain\Supplier;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;

class ShowSupplierProductUpload extends InertiaAction
{
    use WithSupplyChainAuthorisation;

    public function asController(Supplier $supplier, Upload $upload, ActionRequest $request): Upload
    {
        $this->initialisation($request);
        abort_unless($upload->parent_type === $supplier->getMorphClass() && $upload->parent_id === $supplier->id, 404);

        return $upload;
    }

    public function htmlResponse(Upload $upload, ActionRequest $request): Response
    {
        /** @var Supplier $supplier */
        $supplier = $upload->parent;

        return Inertia::render('SupplyChain/SupplierProductUploadPreview', [
            'title'       => __('Upload preview'),
            'breadcrumbs' => array_merge(
                ShowSupplier::make()->getBreadcrumbs($supplier, 'grp.supply-chain.suppliers.show', ['supplier' => $supplier->slug]),
                [[
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.supply-chain.suppliers.supplier_products.uploads.show',
                            'parameters' => ['supplier' => $supplier->slug, 'upload' => $upload->id],
                        ],
                        'label' => $upload->original_filename,
                    ],
                ]]
            ),
            'pageHead'    => [
                'title' => $upload->original_filename,
                'model' => __('Supplier products upload'),
                'icon'  => ['icon' => ['fal', 'file-upload'], 'title' => __('Upload')],
            ],
            'upload'      => [
                'id'          => $upload->id,
                'state'       => $upload->state?->value,
                'state_label' => $upload->state ? __(str_replace('_', ' ', ucfirst($upload->state->value))) : null,
                'filename'    => $upload->original_filename,
                'errors'      => Arr::get($upload->data, 'errors', []),
                'can_edit'    => $this->canEdit && $upload->state === UploadStateEnum::WAITING_CONFIRMATION,
                'problems'    => $upload->state === UploadStateEnum::WAITING_CONFIRMATION ? ImportSupplierProductUpload::make()->problems($upload) : [],
                'review'      => Arr::get($upload->data, 'review'),
                'purchase_orders' => Arr::get($upload->data, 'purchase_orders'),
            ],
            'supplier'    => [
                'code'     => $supplier->code,
                'name'     => $supplier->name,
                'currency' => $supplier->currency?->code,
                'products_route' => [
                    'name'       => 'grp.supply-chain.suppliers.supplier_products.index',
                    'parameters' => ['supplier' => $supplier->slug],
                ],
            ],
            'rows'        => $upload->records()->whereNotNull('row_number')->orderBy('row_number')->get()->map(fn (UploadRecord $record) => [
                'id'        => $record->id,
                'row'       => $record->row_number,
                'status'    => $record->status,
                'values'    => $record->values,
                'findings'  => Arr::get($record->data, 'findings', []),
                'decisions' => Arr::get($record->data, 'decisions', []),
                'skip'      => (bool)Arr::get($record->data, 'skip'),
                'errors'    => $record->errors,
            ])->values(),
            'draft_orders' => $upload->state === UploadStateEnum::WAITING_CONFIRMATION ? $this->draftOrders($supplier, $upload) : [],
            'routes'      => [
                'record'    => ['name' => 'grp.models.supplier_product_upload.record.update', 'parameters' => ['upload' => $upload->id]],
                'new_draft' => ['name' => 'grp.models.supplier_product_upload.new_draft', 'parameters' => ['upload' => $upload->id]],
                'import'    => ['name' => 'grp.models.supplier_product_upload.import', 'parameters' => ['upload' => $upload->id]],
                'cancel'    => ['name' => 'grp.models.supplier_product_upload.cancel', 'parameters' => ['upload' => $upload->id]],
            ],
        ]);
    }

    /**
     * @return list<array{key: string, organisation: ?string, cartons: int, lines: int, purchase_order: ?string, new_draft: bool, error: ?string}>
     */
    protected function draftOrders(Supplier $supplier, Upload $upload): array
    {
        $totals = [];
        foreach ($upload->records()->whereNotNull('row_number')->get() as $record) {
            if (Arr::get($record->data, 'skip')) {
                continue;
            }
            foreach (Arr::get($record->values, 'order', []) as $key => $cartons) {
                $totals[$key]['cartons'] = ($totals[$key]['cartons'] ?? 0) + $cartons;
                $totals[$key]['lines']   = ($totals[$key]['lines'] ?? 0) + 1;
            }
        }

        $orders = [];
        foreach ($totals as $key => $total) {
            $organisation = CheckSupplierProductSheet::make()->organisationForOrderColumn($supplier, $key);
            $orgSupplier  = $organisation ? $supplier->orgSuppliers()->where('organisation_id', $organisation->id)->first() : null;
            $parent       = $orgSupplier ? ($orgSupplier->org_agent_id ? $orgSupplier->orgAgent : $orgSupplier) : null;
            $openDraft    = $parent?->purchaseOrders()->where('state', PurchaseOrderStateEnum::IN_PROCESS)->latest('id')->first();

            $orders[] = [
                'key'            => $key,
                'organisation'   => $organisation?->name,
                'via_agent'      => (bool)$orgSupplier?->org_agent_id,
                'cartons'        => $total['cartons'],
                'lines'          => $total['lines'],
                'purchase_order' => $openDraft?->reference,
                'purchase_order_lines' => $openDraft?->purchaseOrderTransactions()->count(),
                'new_draft'      => (bool)Arr::get($upload->data, 'new_draft.'.$key),
                'error'          => $orgSupplier ? null : __('No organisation buying from this supplier matches ":key".', ['key' => $key]),
            ];
        }

        return $orders;
    }
}
