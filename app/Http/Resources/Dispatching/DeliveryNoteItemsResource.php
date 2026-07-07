<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 22 Feb 2023 22:40:34 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Dispatching;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $org_stock_id
 * @property mixed $id
 * @property mixed $state
 * @property mixed $quantity_required
 * @property mixed $quantity_picked
 * @property mixed $org_stock_code
 * @property mixed $org_stock_name
 * @property mixed $is_handled
 * @property mixed $quantity_packed
 * @property mixed $quantity_not_picked
 * @property mixed $quantity_dispatched
 * @property mixed $packed_in
 * @property mixed $org_stock_slug
 * @property mixed $batch_code
 * @property mixed $batch_code_id
 * @property mixed $expiry_date
 * @property mixed $organisation_id
 * @property mixed $quantity_waiting_warehouse
 * @property mixed $quantity_waiting_crm
 */
class DeliveryNoteItemsResource extends JsonResource
{
    public function toArray($request): array
    {
        $requiredFactionalData = riseDivisor(
            divideWithRemainder(
                findSmallestFactors(
                    $this->quantity_required
                )
            ),
            $this->packed_in
        );

        $packedIn = $this->packed_in;
        if ($packedIn == null) {
            $packedIn = 1;
        }

        $quantityDispatched = $this->quantity_dispatched;
        if ($quantityDispatched == null) {
            $quantityDispatched = 0;
        }

        $packedInMessage = '';
        if ($packedIn == 1) {
            $packedInMessage = '('.__('Individually packed').')';
        } elseif ($packedIn > 1) {
            $packedInMessage = '('.__('Pack of').": $packedIn".")";
        }

        $pickings = @json_decode($this->pickings) ?? [];

        return [
            'id'                             => $this->id,
            'state'                          => $this->state,
            'state_icon'                     => $this->state->stateIcon()[$this->state->value],
            'quantity_required'              => $this->quantity_required,
            'quantity_required_fractional'   => $requiredFactionalData,
            'quantity_dispatched'            => $this->quantity_dispatched,
            'quantity_dispatched_fractional' => riseDivisor(divideWithRemainder(findSmallestFactors($quantityDispatched)), $packedIn),
            'quantity_picked'                => $this->quantity_picked,
            'quantity_picked_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_picked ?? 0)), $packedIn),
            'quantity_packed'                => $this->quantity_packed,
            'quantity_packed_fractional'     => riseDivisor(divideWithRemainder(findSmallestFactors($this->quantity_packed ?? 0)), $packedIn),
            'quantity_not_picked'            => $this->quantity_not_picked,
            'org_stock_code'                 => $this->org_stock_code,
            'org_stock_name'                 => $this->org_stock_name,
            'org_stock_slug'                 => $this->org_stock_slug,
            'org_stock_id'                   => $this->org_stock_id,
            'batch_code'                     => $this->batch_code,
            'batch_code_id'                  => $this->batch_code_id,
            'expiry_date'                    => $this->expiry_date,
            'organisation_id'                => $this->organisation_id,
            'batch_codes_fetch_route'        => [
                'name'       => 'grp.json.org_stock.batch_codes.index',
                'parameters' => [
                    'organisation' => $this->organisation_id,
                    'orgStock'     => $this->org_stock_id,
                ],
            ],
            'packed_in_message'              => $packedInMessage,
            'is_done_packing'                => (bool)$this->packing_id,
            'is_picked'                      => $this->is_picked,
            'is_packed'                      => $this->is_packed,
            'quantity_waiting_warehouse'     => $this->quantity_waiting_warehouse,
            'quantity_waiting_crm'           => $this->quantity_waiting_crm,
            'picking_locations'              => collect($pickings)
                ->map(function ($picking, $pickingId) {
                    return [
                        'id'                       => $pickingId,
                        'quantity_picked'          => (float) $picking->quantity,
                        'location_slug'            => $picking->location_slug,
                        'location_code'            => $picking->location_code,
                        'warehouse_slug'           => $this->warehouse_slug,
                        'warehouse_code'           => $this->warehouse_code,
                        'show_batch_code_ui'       => $this->org_stocks_batch_code_count > 0,
                        'batch_code_id'            => $picking->batch_code_id ?? $this->org_stocks_batch_code_count,
                        'batch_code'               => $picking->batch_code ?? $this->org_stocks_batch_code,
                        'update_route'             => [
                            'name'       => 'grp.models.picking.update',
                            'parameters' => ['picking' => $pickingId],
                            'method'     => 'patch',
                        ],
                        'split_route'              => [
                            'name'       => 'grp.models.picking.split',
                            'parameters' => ['picking' => $pickingId],
                            'method'     => 'post',
                        ],
                        'batch_codes_fetch_route'  => [
                            'name'       => 'grp.json.org_stock.batch_codes.index',
                            'parameters' => [
                                'organisation' => $this->organisation_id,
                                'orgStock'     => $this->org_stock_id,
                            ],
                        ],
                    ];
                })->values()->toArray(),
            'not_picking_route' => [
                'name'       => 'grp.models.delivery_note_item.not_picking.store',
                'parameters' => [
                    'deliveryNoteItem' => $this->id
                ],
                'method'     => 'post'
            ],
            'un_numbers'                     => @json_decode($this->un_numbers) ?? null,
        ];
    }
}
