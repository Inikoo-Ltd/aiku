<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Dispatching;

use App\Actions\Dispatching\PartnerShipment\GetPartnerShipmentCustomsLines;
use App\Actions\Dispatching\PartnerShipment\UI\ShowPartnerShipment;
use App\Models\Dispatching\PartnerShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * The consolidated delivery note to a partner (HELP-4482): a summary of its delivery notes, then
 * one part per customs split of the partner (cosmetics and the rest for ES, GB origin and the rest
 * for SK), as the pallets are built and as its customs invoices are split. Issued in English.
 */
class PartnerShipmentPackingListExport implements FromArray, ShouldAutoSize
{
    public function __construct(public PartnerShipment $partnerShipment)
    {
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        $locale = app()->getLocale();
        app()->setLocale('en');
        try {
            return $this->rows();
        } finally {
            app()->setLocale($locale);
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function rows(): array
    {
        $consolidation = $this->partnerShipment;
        $orgPartner    = $consolidation->orgPartner;
        $notes         = collect(ShowPartnerShipment::noteRows($consolidation));

        $rows = [];
        if ($consolidation->voided_at) {
            $rows[] = [sprintf('VOID %s: %s', $consolidation->voided_at->format('d/m/Y'), $consolidation->void_reason)];
        }

        $rows = [
            ...$rows,
            ['CONSOLIDATED DELIVERY NOTE', '', '', $consolidation->reference, $consolidation->date->format('d/m/Y')],
            [],
            ['Shipper', $consolidation->organisation->name],
            ['Consignee', $orgPartner->partner->name],
            ['Number of pallets', $consolidation->number_pallets],
            [],
            ['Delivery note', 'Invoice', 'Shop', 'Parcels', 'Items', 'Weight (kg)'],
        ];
        foreach ($notes as $note) {
            $rows[] = [$note['reference'], $note['is_invoiced'] ? $note['invoice'] : '', $note['shop_code'], $note['number_parcels'], $note['number_items'], $note['weight']];
        }
        $rows[] = ['TOTAL', $notes->count().' delivery notes', '', $notes->sum('number_parcels'), $notes->sum('number_items'), round($notes->sum('weight'), 1)];

        foreach ($this->itemsBySplit() as $split => $items) {
            $rows[] = [];
            $rows[] = [strtoupper(GetPartnerShipmentCustomsLines::splitLabel($orgPartner, $split))];
            $rows[] = ['Delivery note', 'SKO', 'Description', 'Quantity (SKOs)', 'Weight (kg)'];
            foreach ($items as $item) {
                $rows[] = [$item->delivery_note, $item->sko, $item->description, (float) $item->quantity, (float) $item->weight];
            }
            $rows[] = ['TOTAL', $items->count().' lines', '', $items->sum(fn ($item) => (float) $item->quantity), round($items->sum(fn ($item) => (float) $item->weight), 3)];
        }

        return $rows;
    }

    /**
     * The packed lines on the consolidation, by the partner's customs split, in split order.
     *
     * @return Collection<string, Collection<int, object>>
     */
    private function itemsBySplit(): Collection
    {
        $orgPartner = $this->partnerShipment->orgPartner;
        $split      = GetPartnerShipmentCustomsLines::splitSql($orgPartner) ?? "'main'";

        $items = DB::table('partner_shipment_delivery_notes as psdn')
            ->join('delivery_notes as dn', 'dn.id', '=', 'psdn.delivery_note_id')
            ->join('delivery_note_items as dni', 'dni.delivery_note_id', '=', 'dn.id')
            ->leftJoin('org_stocks as os', 'os.id', '=', 'dni.org_stock_id')
            ->leftJoin('stocks as st', 'st.id', '=', 'os.stock_id')
            ->leftJoin('model_has_trade_units as mhtu', fn ($join) => $join->on('mhtu.model_id', '=', 'dni.org_stock_id')->where('mhtu.model_type', 'OrgStock'))
            ->leftJoin('trade_units as tu', 'tu.id', '=', 'mhtu.trade_unit_id')
            ->where('psdn.partner_shipment_id', $this->partnerShipment->id)
            ->where('dni.quantity_packed', '>', 0)
            ->groupBy('dni.id', 'dn.reference', 'os.code', 'os.name', 'st.is_cosmetic', 'st.is_gb_origin')
            ->orderBy('dn.reference')
            ->orderBy('os.code')
            ->select([
                'dn.reference as delivery_note',
                'os.code as sko',
                'os.name as description',
                DB::raw('MAX(dni.quantity_packed) as quantity'),
                DB::raw('ROUND(COALESCE(SUM(tu.gross_weight * mhtu.quantity * dni.quantity_packed), 0)::numeric / 1000, 3) as weight'),
                DB::raw("$split as split"),
            ])
            ->get()
            ->groupBy('split');

        return collect(GetPartnerShipmentCustomsLines::SPLIT_ORDER)
            ->filter(fn (string $key) => $items->has($key))
            ->mapWithKeys(fn (string $key) => [$key => $items->get($key)]);
    }
}
