<?php

namespace App\Actions\Dispatching\DeliveryNote;

use App\Actions\OrgAction;
use App\Models\Dispatching\DeliveryNote;
use App\Actions\Traits\WithExportData;
use App\Models\Dispatching\DeliveryNoteItem;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class PdfPackingList extends OrgAction
{
    use WithExportData;
    /**
     * @throws \Mpdf\MpdfException
     */
    public function handle(DeliveryNote $deliveryNote): Response
    {
        app()->setLocale($deliveryNote->shop->language->code);

        $deliveryNote->loadMissing([
            'orders',
            'deliveryAddress',
            'deliveryNoteItems.orgStock',
            'deliveryNoteItems.transaction.historicAsset',
        ]);

        $filename = 'packing-list-'.$deliveryNote->slug.'-'.Carbon::now()->format('Y-m-d');

        $productsWithSeveralSkos = $deliveryNote->deliveryNoteItems->whereNotNull('transaction_id')->countBy('transaction_id')->filter(fn (int $skos) => $skos > 1);

        $boxes = $deliveryNote->deliveryNoteItems
            ->flatMap(fn (DeliveryNoteItem $item) => collect($item->boxes ?? [])->map(function (array $row) use ($item, $productsWithSeveralSkos) {
                $line = $this->line($item, (float)$row['quantity']);
                if ($productsWithSeveralSkos->has($item->transaction_id)) {
                    $line['description'] = $line['sko_name'].' ('.__('part of :product', ['product' => $line['product_code']]).')';
                }

                return ['box' => $row['box'], ...$line];
            }))
            ->groupBy('box')
            ->sortKeys();

        $pdf = PDF::loadView('deliveryNote.templates.pdf.packing-list', [
            'deliveryNote'    => $deliveryNote,
            'order'           => $deliveryNote->orders->first(),
            'lines'           => $this->lines($deliveryNote),
            'boxes'           => $boxes,
            'numberBoxes'     => max(count($deliveryNote->parcels ?? []), (int)$boxes->keys()->max()),
            'deliveryAddress' => $deliveryNote->deliveryAddress?->formatted_address,
        ]);

        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'.pdf"');
    }

    /**
     * One line per product the customer ordered; a product made of several SKOs lists them as its components.
     *
     * @return Collection<int, array{sko_code: string, product_code: string, description: string, quantity: float, components: array<int, array{sko_code: string, description: string, quantity: float}>}>
     */
    public function lines(DeliveryNote $deliveryNote): Collection
    {
        return $deliveryNote->deliveryNoteItems
            ->groupBy(fn (DeliveryNoteItem $item) => $item->transaction_id ?? 'item-'.$item->id)
            ->map(function (Collection $items) {
                $parts = $items->map(fn (DeliveryNoteItem $item) => $this->line($item, (float)($item->quantity_packed ?? $item->quantity_required)));

                if ($parts->count() == 1) {
                    return [...Arr::except($parts->first(), ['sko_name', 'sko_quantity']), 'components' => []];
                }

                return [
                    'sko_code'     => '',
                    'product_code' => $parts->first()['product_code'],
                    'description'  => $items->first()->transaction?->historicAsset?->name ?? '',
                    'quantity'     => $parts->min('quantity'),
                    'components'   => $parts->map(fn (array $part) => [
                        'sko_code'    => $part['sko_code'],
                        'description' => $part['sko_name'],
                        'quantity'    => $this->isWholeNumber($part['sko_quantity']) ? $part['sko_quantity'] : $part['quantity'],
                    ])->values()->all(),
                ];
            })
            ->filter(fn (array $line) => $line['quantity'] > 0)
            ->values();
    }

    /**
     * Delivery note items count SKOs (a slice of soap is 0.1 of its SKO); the customer counts the products they ordered.
     *
     * @return array{sko_code: string, sko_name: string, sko_quantity: float, product_code: string, description: string, quantity: float}
     */
    public function line(DeliveryNoteItem $item, float $skoQuantity): array
    {
        $transaction      = $item->transaction;
        $productsOrdered  = $transaction ? (float)$transaction->quantity_ordered + (float)$transaction->quantity_bonus : 0;
        $quantityRequired = (float)$item->quantity_required;

        $quantity = $skoQuantity;
        if ($productsOrdered > 0 && $quantityRequired > 0) {
            $quantity = $skoQuantity * $productsOrdered / $quantityRequired;
            $quantity = $this->isWholeNumber($quantity) ? round($quantity) : round($quantity, 2);
        }

        return [
            'sko_code'     => $item->orgStock?->code ?? '',
            'sko_name'     => $item->orgStock?->name ?? '',
            'sko_quantity' => round($skoQuantity, 3),
            'product_code' => $transaction?->historicAsset?->code ?? '',
            'description'  => $transaction?->historicAsset?->name ?? $item->orgStock?->name ?? '',
            'quantity'     => (float)$quantity,
        ];
    }

    private function isWholeNumber(float $quantity): bool
    {
        return $quantity >= 1 && abs($quantity - round($quantity)) < 0.02;
    }

    public function asController(DeliveryNote $deliveryNote): Response
    {
        return $this->handle($deliveryNote);
    }
}
