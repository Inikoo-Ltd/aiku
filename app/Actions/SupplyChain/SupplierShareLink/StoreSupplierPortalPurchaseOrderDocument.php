<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\Helpers\Media\SaveModelAttachment;
use App\Actions\SupplyChain\SupplierShareLink\UI\IndexSupplierPortalPurchaseOrders;
use App\Actions\SupplyChain\SupplierShareLink\UI\ShowSupplierPortalPurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The supplier uploads the shipping and customs documents of one of its own orders from its portal: proforma, invoice,
 * packing list, bill of lading, certificate of origin. They are kept on the order as its attachments, marked with
 * sub_scope supplier_portal, so the buyer finds them with the order's other documents and the supplier sees only its own.
 * Only open orders take documents. A file the buyer already attached to the order is refused: attachments are shared
 * by checksum, and saving it again would re-type the buyer's attachment and show it to the supplier. Uploading one of
 * its own files again re-types it. The supplier sees the name it uploaded the file with (the caption), never media.name,
 * which belongs to whoever stored those bytes first.
 */
class StoreSupplierPortalPurchaseOrderDocument
{
    use AsAction;

    public const string SUB_SCOPE = 'supplier_portal';

    /**
     * @param  array{scope: string, files: list<UploadedFile>}  $modelData
     */
    public function handle(Supplier $supplier, int $purchaseOrderId, array $modelData): PurchaseOrder
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);
        if (!in_array($purchaseOrder->state, PurchaseOrderStateEnum::open(), true)) {
            throw ValidationException::withMessages(['files' => __('This order is closed.')]);
        }

        $alreadyOnOrder = [];
        foreach ($modelData['files'] as $file) {
            $attachedByBuyer = $purchaseOrder->attachments()
                ->where('media.checksum', md5_file($file->getPathname()))
                ->where(fn ($query) => $query->whereNull('model_has_attachments.sub_scope')->orWhere('model_has_attachments.sub_scope', '!=', self::SUB_SCOPE))
                ->exists();
            if ($attachedByBuyer) {
                $alreadyOnOrder[] = $file->getClientOriginalName();
            }
        }
        if ($alreadyOnOrder) {
            throw ValidationException::withMessages(['files' => __('Already on this order: :files', ['files' => implode(', ', $alreadyOnOrder)])]);
        }

        foreach ($modelData['files'] as $file) {
            SaveModelAttachment::make()->action($purchaseOrder, [
                'path'         => $file->getPathname(),
                'originalName' => $file->getClientOriginalName(),
                'extension'    => strtolower($file->getClientOriginalExtension()),
                'scope'        => $modelData['scope'],
                'sub_scope'    => self::SUB_SCOPE,
                'caption'      => $file->getClientOriginalName(),
            ]);
        }

        return $purchaseOrder;
    }

    public function asController(string $token, int $purchaseOrder, Request $request): JsonResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        $validated = Validator::make($request->all(), [
            'scope'   => ['required', Rule::in(array_map(fn (PurchaseOrderAttachmentScopeEnum $scope) => $scope->value, PurchaseOrderAttachmentScopeEnum::supplierPortal()))],
            'files'   => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'extensions:pdf,jpg,jpeg,png,xls,xlsx,doc,docx', 'mimes:pdf,jpg,jpeg,png,xls,xlsx,doc,docx', 'max:15360'],
        ])->validate();

        $this->handle($link->supplier, $purchaseOrder, $validated);

        return response()->json(ShowSupplierPortalPurchaseOrder::make()->handle($link->supplier, $purchaseOrder, $token));
    }
}
