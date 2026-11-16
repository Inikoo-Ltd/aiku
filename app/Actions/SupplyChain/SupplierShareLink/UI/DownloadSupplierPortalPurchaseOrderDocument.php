<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Nov 2026, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
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
 * A document the supplier uploaded to one of its own orders from its portal, to view or download again. Documents
 * the buyer or the agent attached to the order are never reachable through the supplier's link.
 */
class DownloadSupplierPortalPurchaseOrderDocument
{
    use AsAction;

    public function handle(Supplier $supplier, int $purchaseOrderId, Media $media, bool $inline = false): BinaryFileResponse
    {
        /** @var PurchaseOrder $purchaseOrder */
        $purchaseOrder = IndexSupplierPortalPurchaseOrders::make()->sent($supplier)->findOrFail($purchaseOrderId);
        abort_unless(ShowSupplierPortalPurchaseOrder::make()->supplierDocumentsQuery($purchaseOrder)->where('media.id', $media->id)->exists(), 404);

        return DownloadAttachment::make()->handle($media, $inline);
    }

    public function asController(string $token, int $purchaseOrder, Media $media, Request $request): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier, $purchaseOrder, $media, $request->boolean('inline'));
    }
}
