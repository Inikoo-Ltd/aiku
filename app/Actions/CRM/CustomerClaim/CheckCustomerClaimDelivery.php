<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\CustomerClaim;

use App\Enums\Dispatching\DeliveryNote\DeliveryNoteTypeEnum;
use App\Enums\Dispatching\DeliveryNoteItem\DeliveryNoteItemReplacementReasonEnum;
use App\Models\CRM\CustomerClaim\CustomerClaim;
use App\Models\CRM\CustomerClaim\CustomerClaimLine;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\Dispatching\Packing;
use App\Models\Dispatching\Shipment;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * What our own records say about a claimed delivery (HELP-4869), for whoever reviews the claim.
 * Lost parcels: where the carrier last saw each parcel of the order, from the tracking aiku keeps on
 * the shipment (only carriers that report tracking back, GLS Spain since Oct 2026). A parcel the
 * carrier still has on its way stops the claim (the customer is asked to wait); delivered, coming
 * back to us, overdue or untracked parcels go to a person. Missing items: how many of each line
 * were packed, by whom and when. Parcels are not weighed at packing (the recorded weight is the
 * estimate), so there is no weight check. Nothing here rejects a claim.
 */
class CheckCustomerClaimDelivery
{
    use AsAction;

    public const int TRACKING_FRESH_HOURS = 48;

    public const int TRANSIT_PATIENCE_DAYS = 7;

    private const array RETURN_WORDS = ['DEVOL', 'DEVUELTA', 'RETURN', 'RETOUR'];

    private const array DELIVERED_WORDS = ['ENTREG', 'DELIVERED'];

    public function handle(CustomerClaim $claim): CustomerClaim
    {
        $claim->loadMissing(['lines', 'order']);
        $check = [];

        if ($claim->order && $claim->lines->contains(fn (CustomerClaimLine $line) => $line->reason === DeliveryNoteItemReplacementReasonEnum::LOST_IN_TRANSIT)) {
            $check['tracking'] = $this->tracking($claim->order);
        }

        $missing = $claim->lines->filter(fn (CustomerClaimLine $line) => $line->reason === DeliveryNoteItemReplacementReasonEnum::MISSING_FROM_PARCEL);
        if ($missing->isNotEmpty()) {
            $check['packing'] = $missing->map(fn (CustomerClaimLine $line) => $this->packing($line))->values()->all();
        }

        if ($check) {
            $claim->update(['result' => array_merge($claim->result ?? [], ['delivery_check' => $check])]);
        }

        return $claim;
    }

    /**
     * The order's parcels as the carrier last reported them, and one state for the whole order:
     * in_transit only when every parcel is tracked and still on its way.
     *
     * @return array{state: string, shipments: array<int, array{carrier: ?string, tracking: ?string, status: ?string, state: string, tracked_at: ?string, delivered_at: ?string, url: ?string}>}
     */
    public function tracking(Order $order): array
    {
        $shipments = Shipment::query()
            ->whereHas('deliveryNotes', fn ($notes) => $notes->whereIn('delivery_notes.id', $order->deliveryNotes()->where('delivery_notes.type', DeliveryNoteTypeEnum::ORDER)->pluck('delivery_notes.id')))
            ->with('shipper:id,name,trade_as')
            ->get()
            ->map(fn (Shipment $shipment) => [
                'carrier'      => $shipment->shipper?->trade_as ?: $shipment->shipper?->name,
                'tracking'     => $shipment->tracking,
                'status'       => $shipment->carrier_status,
                'state'        => $this->shipmentState($shipment, $order),
                'tracked_at'   => $shipment->tracked_at?->toIso8601String(),
                'delivered_at' => $shipment->delivered_at?->toIso8601String(),
                'url'          => collect($shipment->tracking_urls ?: [])->flatten()->filter(fn ($url) => is_string($url) && str_starts_with($url, 'http'))->first(),
            ])
            ->values();

        $states = $shipments->pluck('state')->unique();

        return [
            'state'     => match (true) {
                $shipments->isEmpty()          => 'untracked',
                $states->count() === 1         => $states->first(),
                $states->contains('returning') => 'returning',
                $states->contains('untracked') => 'untracked',
                $states->intersect(['delivered', 'partly_delivered'])->isNotEmpty() => 'partly_delivered',
                default                        => 'overdue',
            },
            'shipments' => $shipments->all(),
        ];
    }

    public function shipmentState(Shipment $shipment, Order $order): string
    {
        $status = mb_strtoupper((string) $shipment->carrier_status);

        return match (true) {
            $shipment->delivered_at !== null                                                    => 'delivered',
            !$shipment->tracked_at || $status === ''                                            => 'untracked',
            $this->mentions($status, self::RETURN_WORDS)                                        => 'returning',
            $this->mentions($status, self::DELIVERED_WORDS)                                     => 'partly_delivered',
            $shipment->tracked_at->lt(now()->subHours(self::TRACKING_FRESH_HOURS))              => 'overdue',
            (bool) $order->dispatched_at?->lt(now()->subDays(self::TRANSIT_PATIENCE_DAYS))       => 'overdue',
            default                                                                             => 'in_transit',
        };
    }

    /**
     * @return array{code: string, claimed: float, dispatched: float, packed: float, packers: array<int, string>, packed_at: ?string, is_packed: bool}
     */
    public function packing(CustomerClaimLine $line): array
    {
        $itemIds  = collect($line->delivery_note_items ?: [])->pluck('id')->filter()->all();
        $packings = Packing::whereIn('delivery_note_item_id', $itemIds)->with('packer:id,contact_name,username')->get();
        $items    = DeliveryNoteItem::whereIn('id', $itemIds)->get(['quantity_packed', 'quantity_dispatched']);

        $packed     = (float) $items->sum('quantity_packed');
        $dispatched = (float) $items->sum('quantity_dispatched');

        return [
            'code'       => $line->code,
            'claimed'    => (float) $line->quantity,
            'dispatched' => $dispatched,
            'packed'     => $packed,
            'packers'    => $packings->map(fn (Packing $packing) => $packing->packer?->contact_name ?: $packing->packer?->username)->filter()->unique()->values()->all(),
            'packed_at'  => $packings->max('done_at')?->toIso8601String(),
            'is_packed'  => $dispatched > 0 && $packed >= $dispatched,
        ];
    }

    /**
     * @param  array<int, string>  $words
     */
    private function mentions(string $status, array $words): bool
    {
        foreach ($words as $word) {
            if (str_contains($status, $word)) {
                return true;
            }
        }

        return false;
    }
}
