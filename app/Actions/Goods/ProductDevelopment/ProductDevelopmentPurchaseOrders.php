<?php

/*
 * Author Louis Perez
 * Created on 20-10-2026-10h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Procurement\PurchaseOrder\MarkPurchaseOrderStage;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderAttachmentScopeEnum;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentFile;
use App\Models\Procurement\PurchaseOrder;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The purchase orders of a development: every PO that orders one of its SKUs' supplier products, and the
 * ones linked to it by hand.
 */
class ProductDevelopmentPurchaseOrders
{
    public const string AUTO = 'auto';
    public const string MANUAL = 'manual';

    /**
     * @return array<int, int>
     */
    public static function supplierProductIds(ProductDevelopment $productDevelopment): array
    {
        return $productDevelopment->items()->whereNotNull('supplier_product_id')->pluck('supplier_product_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * @return Collection<int, PurchaseOrder>
     */
    public static function automatic(ProductDevelopment $productDevelopment): Collection
    {
        $supplierProductIds = self::supplierProductIds($productDevelopment);
        if ($supplierProductIds === []) {
            return collect();
        }

        return PurchaseOrder::query()
            ->where('group_id', $productDevelopment->group_id)
            ->whereHas('purchaseOrderTransactions', fn (Builder $transactions) => $transactions->whereIn('supplier_product_id', $supplierProductIds))
            ->with('organisation')
            ->get();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(ProductDevelopment $productDevelopment): array
    {
        $automatic = self::automatic($productDevelopment)->keyBy('id');
        $manual    = $productDevelopment->purchaseOrders()->with('organisation')->get()->keyBy('id');

        return $automatic->map(fn (PurchaseOrder $purchaseOrder) => self::row($purchaseOrder, self::AUTO))
            ->union($manual->reject(fn (PurchaseOrder $purchaseOrder) => $automatic->has($purchaseOrder->id))->map(fn (PurchaseOrder $purchaseOrder) => self::row($purchaseOrder, self::MANUAL)))
            ->sortByDesc('date')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(PurchaseOrder $purchaseOrder, string $linked): array
    {
        return [
            'id'        => $purchaseOrder->id,
            'reference' => $purchaseOrder->reference,
            'state'     => PurchaseOrderStateEnum::labels()[$purchaseOrder->state->value] ?? $purchaseOrder->state->value,
            'date'      => $purchaseOrder->date?->toDateString(),
            'linked'    => $linked,
            'sample'    => match (true) {
                !$purchaseOrder->sample_required        => null,
                $purchaseOrder->sample_approved_at !== null => 'approved',
                filled($purchaseOrder->sample_override_reason) => 'overridden',
                default                                 => 'required',
            },
            'route'     => ['name' => 'grp.org.procurement.purchase_orders.show', 'parameters' => ['organisation' => $purchaseOrder->organisation->slug, 'purchaseOrder' => $purchaseOrder->slug]],
        ];
    }

    /**
     * The developments a purchase order belongs to, with how: linked by hand, or through a SKU it orders.
     *
     * @return array<int, string> link type by development id
     */
    public static function developmentLinks(PurchaseOrder $purchaseOrder): array
    {
        $manual = ProductDevelopment::query()
            ->whereHas('purchaseOrders', fn (Builder $purchaseOrders) => $purchaseOrders->where('purchase_orders.id', $purchaseOrder->id))
            ->pluck('id')
            ->all();

        $supplierProductIds = $purchaseOrder->purchaseOrderTransactions()->whereNotNull('supplier_product_id')->pluck('supplier_product_id')->unique()->all();
        $automatic          = $supplierProductIds === [] ? [] : ProductDevelopment::query()
            ->whereHas('items', fn (Builder $items) => $items->whereIn('supplier_product_id', $supplierProductIds))
            ->pluck('id')
            ->all();

        $links = array_fill_keys($manual, self::MANUAL);
        foreach ($automatic as $productDevelopmentId) {
            $links[$productDevelopmentId] = self::AUTO;
        }

        return $links;
    }

    /**
     * Ticks "Sample approved" on the purchase orders that were waiting for this artwork's sample, with the sample
     * photos as the evidence. A purchase order buying SKUs of the development waits for the samples of every design
     * block of those SKUs; one linked on the buyer's list to the trade unit made from this artwork waits for this
     * sample only. Drafts are left alone, their stages start once they are submitted.
     *
     * @return array<int, string> references of the purchase orders released
     */
    public static function releaseSamples(ProductDevelopmentArtwork $artwork, User $user): array
    {
        $productDevelopment = $artwork->productDevelopment;
        $released           = [];

        foreach (self::waitingForSamples($artwork) as [$purchaseOrder, $artworks]) {
            self::attachSamplePhotos($purchaseOrder, $artworks);

            $mark   = MarkPurchaseOrderStage::make();
            $today  = MarkPurchaseOrderStage::today($purchaseOrder->organisation);
            $reason = $mark->reasonsNeeded($purchaseOrder, 'sample_approved_at', $today)
                ? __('Sample approved in product development :code by :name', ['code' => $productDevelopment->code, 'name' => $user->contact_name ?: $user->username])
                : null;

            try {
                $mark->action($purchaseOrder, ['field' => 'sample_approved_at', 'date' => $today, 'reason' => $reason]);
                $released[] = $purchaseOrder->reference;
            } catch (ValidationException) {
                continue;
            }
        }

        return $released;
    }

    /**
     * @return array<int, array{0: PurchaseOrder, 1: Collection<int, ProductDevelopmentArtwork>}>
     */
    private static function waitingForSamples(ProductDevelopmentArtwork $artwork): array
    {
        $productDevelopment = $artwork->productDevelopment;
        $artworks           = $productDevelopment->artworks()->with('packagingSamplePhotos.media')->get();
        $sections           = $productDevelopment->designSections()->count();
        $itemsBySupplierProduct = $productDevelopment->items()->whereNotNull('supplier_product_id')->pluck('id', 'supplier_product_id');
        $allItemIds         = $productDevelopment->items()->pluck('id');
        $waiting            = [];

        $productOrders = self::automatic($productDevelopment)->keyBy('id')->union($productDevelopment->purchaseOrders()->get()->keyBy('id'));
        foreach ($productOrders as $purchaseOrder) {
            $orderedItemIds = $purchaseOrder->purchaseOrderTransactions()->whereNotNull('supplier_product_id')->pluck('supplier_product_id')
                ->map(fn ($supplierProductId) => $itemsBySupplierProduct->get($supplierProductId))
                ->filter()
                ->unique()
                ->values();
            $itemIds = $orderedItemIds->isEmpty() ? $allItemIds : $orderedItemIds;
            $samples = $artworks->whereIn('product_development_item_id', $itemIds->all());

            if ($itemIds->contains($artwork->product_development_item_id)
                && $samples->where('packaging_sample_status', ProductDevelopmentArtwork::PACKAGING_SAMPLE_APPROVED)->count() === $sections * $itemIds->count()) {
                $waiting[$purchaseOrder->id] = [$purchaseOrder, $samples];
            }
        }

        if ($artwork->trade_unit_id) {
            $componentOrders = $productDevelopment->componentPurchaseOrders()->wherePivot('trade_unit_id', $artwork->trade_unit_id)->get();
            foreach ($componentOrders as $purchaseOrder) {
                $waiting[$purchaseOrder->id] ??= [$purchaseOrder, $artworks->where('id', $artwork->id)];
            }
        }

        return collect($waiting)
            ->filter(fn (array $entry) => self::isWaitingForSample($entry[0]))
            ->values()
            ->all();
    }

    private static function isWaitingForSample(PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->sample_required
            && $purchaseOrder->sample_approved_at === null
            && !in_array($purchaseOrder->state, [PurchaseOrderStateEnum::IN_PROCESS, PurchaseOrderStateEnum::CANCELLED, PurchaseOrderStateEnum::NOT_RECEIVED], true);
    }

    /**
     * @param Collection<int, ProductDevelopmentArtwork> $artworks
     */
    private static function attachSamplePhotos(PurchaseOrder $purchaseOrder, Collection $artworks): void
    {
        $attached = $purchaseOrder->attachments()->pluck('media.id')->all();

        $artworks->flatMap(fn (ProductDevelopmentArtwork $artwork) => $artwork->packagingSamplePhotos)
            ->filter(fn (ProductDevelopmentFile $photo) => $photo->media && !in_array($photo->media_id, $attached, true))
            ->unique('media_id')
            ->each(fn (ProductDevelopmentFile $photo) => $purchaseOrder->attachments()->attach($photo->media_id, [
                'group_id' => $purchaseOrder->group_id,
                'scope'    => PurchaseOrderAttachmentScopeEnum::SAMPLE_PHOTO->value,
                'caption'  => pathinfo((string) $photo->media->name, PATHINFO_FILENAME),
                'data'     => '{}',
            ]));
    }
}
