<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 10:00
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Models\Helpers\Media;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * The transfer receipt of a deposit or balance payment on a purchase order sent to the supplier, to view or download
 * from the supplier page. Only the receipts of payments on the supplier's own sent orders can be reached through its link.
 */
class DownloadSupplierPortalPaymentReceipt
{
    use AsAction;

    public function handle(Supplier $supplier, int $purchaseOrderId, Media $media, bool $inline = false): BinaryFileResponse
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);
        abort_unless(ShowSupplierPortalPurchaseOrder::make()->isPaymentReceipt($purchaseOrder, $media), 404);

        return DownloadAttachment::make()->handle($media, $inline);
    }

    public function asController(string $token, int $purchaseOrder, Media $media, Request $request): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier, $purchaseOrder, $media, $request->boolean('inline'));
    }
}
