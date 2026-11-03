<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-02 13:26
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\SupplyChain\SupplierShareLink\UI;

use App\Actions\Helpers\Media\UI\DownloadAttachment;
use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use App\Models\Helpers\Media;
use App\Models\SupplyChain\Supplier;
use App\Models\SupplyChain\SupplierProduct;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * A product image at full size, to view or download from the supplier page. Only the images of the supplier's own
 * products, its own or the trade unit's, can be reached through its link.
 */
class DownloadSupplierPortalImage
{
    use AsAction;

    public function handle(Supplier $supplier, Media $media, bool $inline = false): BinaryFileResponse
    {
        abort_unless($this->isProductImage($supplier, $media), 404);

        return DownloadAttachment::make()->handle($media, $inline);
    }

    public function isProductImage(Supplier $supplier, Media $media): bool
    {
        return SupplierProduct::where('supplier_id', $supplier->id)
            ->where('state', '!=', SupplierProductStateEnum::IN_PROCESS)
            ->where(fn ($query) => $query->where('image_id', $media->id)
                ->orWhereHas('tradeUnits', fn ($tradeUnits) => $tradeUnits->where('trade_units.image_id', $media->id)))
            ->exists();
    }

    public function asController(string $token, Media $media, Request $request): BinaryFileResponse
    {
        $link = SupplierShareLink::findLiveByToken($token);
        abort_if($link->needsCode(), 403);

        return $this->handle($link->supplier, $media, $request->boolean('inline'));
    }
}
