<?php

namespace App\Actions\GoodsIn\StockDelivery;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\Traits\Authorisations\WithStockDeliveryDocumentAuthorisation;
use App\Enums\GoodsIn\StockDeliveryItem\StockDeliveryItemStateEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\GoodsIn\StockDeliveryItem;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Mpdf\MpdfException;
use Symfony\Component\HttpFoundation\Response;

class PdfStockDelivery extends OrgAction
{
    use WithStockDeliveryDocumentAuthorisation;
    use WithAgentOrganisation;

    /**
     * @throws MpdfException
     */
    public function handle(StockDelivery $stockDelivery): Response
    {
        $stockDelivery->loadMissing(['organisation', 'currency']);

        $pdf = PDF::loadView('goodsIn.templates.pdf.stock-delivery', [
            'stockDelivery' => $stockDelivery,
            'rows'          => self::rows($stockDelivery),
            'showCbm'       => self::showsCbm($stockDelivery),
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($stockDelivery).'"');
    }

    public static function showsCbm(StockDelivery $stockDelivery): bool
    {
        return !Arr::get($stockDelivery->data, 'hide_cbm', false);
    }

    /**
     * The products of the delivery as the PDF and the CSV print them, one row per product however many purchase orders
     * it came from: the quantity in units, SKOs and cartons, the space it takes in the container, and what it is packed
     * inside or holds.
     *
     * @return Collection<int, array{code: string|null, name: string|null, units: float, skos: float, cartons: float|null, cbm: float|null, packed_inside: string|null, contains: string|null, checked: float, placed: float}>
     */
    public static function rows(StockDelivery $stockDelivery): Collection
    {
        $items = $stockDelivery->items()
            ->where('state', '!=', StockDeliveryItemStateEnum::CANCELLED)
            ->with(['supplierProduct:id,code,name,cbm,units_per_pack,units_per_carton', 'orgStock:id,code,name,packed_in'])
            ->get();

        $codes         = $items->mapWithKeys(fn (StockDeliveryItem $item) => [$item->id => $item->supplierProduct?->code ?? $item->orgStock?->code]);
        $outerCodes    = $codes->only($items->where('state', '!=', StockDeliveryItemStateEnum::NOT_RECEIVED)->modelKeys());
        $packedItemIds = $items->whereNotNull('packed_inside_stock_delivery_item_id')->groupBy('packed_inside_stock_delivery_item_id')->map->pluck('id');

        return $items
            ->map(function (StockDeliveryItem $item) use ($codes, $outerCodes, $packedItemIds) {
                $units          = (float) $item->unit_quantity;
                $unitsPerCarton = (float) $item->supplierProduct?->units_per_carton;
                $packedInside   = $item->packed_inside_stock_delivery_item_id ? $outerCodes->get($item->packed_inside_stock_delivery_item_id) : null;
                $cartonsCbm     = $item->supplierProduct?->cbm !== null && $unitsPerCarton > 0 ? (float) $item->supplierProduct->cbm * $units / $unitsPerCarton : null;
                $contains       = $outerCodes->has($item->id) ? $codes->only($packedItemIds->get($item->id, collect())->all())->filter()->sort()->implode(', ') : '';

                return [
                    'code'          => $codes->get($item->id),
                    'name'          => $item->supplierProduct?->name ?? $item->orgStock?->name,
                    'units'         => $units,
                    'skos'          => round($units / $item->unitsPerSko(), 2),
                    'cartons'       => $unitsPerCarton > 0 ? round($units / $unitsPerCarton, 2) : null,
                    'cbm'           => $packedInside ? 0.0 : ($item->cbm_in_container !== null ? round((float) $item->cbm_in_container, 4) : ($cartonsCbm === null ? null : round($cartonsCbm, 4))),
                    'packed_inside' => $packedInside,
                    'contains'      => $contains ?: null,
                    'checked'       => (float) $item->unit_quantity_checked,
                    'placed'        => (float) $item->unit_quantity_placed,
                ];
            })
            ->groupBy('code')
            ->map(fn (Collection $lines) => self::productRow($lines))
            ->sortBy(fn (array $row) => $row['code'])
            ->values();
    }

    /**
     * A product shipped from two purchase orders sits on two lines of the delivery; customs, the shipper and the
     * warehouse count it as one, so its lines print as a single row.
     */
    private static function productRow(Collection $lines): array
    {
        $sumOrNull = fn (string $key) => $lines->whereNotNull($key)->isEmpty() ? null : round($lines->sum($key), 4);

        return array_merge($lines->first(), [
            'units'         => $lines->sum('units'),
            'skos'          => round($lines->sum('skos'), 2),
            'cartons'       => $sumOrNull('cartons') === null ? null : round($sumOrNull('cartons'), 2),
            'cbm'           => $sumOrNull('cbm'),
            'packed_inside' => $lines->pluck('packed_inside')->filter()->unique()->implode(', ') ?: null,
            'contains'      => $lines->pluck('contains')->filter()->unique()->implode(', ') ?: null,
            'checked'       => $lines->sum('checked'),
            'placed'        => $lines->sum('placed'),
        ]);
    }

    public function filename(StockDelivery $stockDelivery): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', $stockDelivery->reference).'-'.Carbon::now()->format('Y-m-d').'.pdf';
    }

    public function asController(Organisation $organisation, StockDelivery $stockDelivery, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($stockDelivery);

        return $this->handle($stockDelivery);
    }
}
