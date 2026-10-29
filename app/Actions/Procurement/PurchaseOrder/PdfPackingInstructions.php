<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-10-20 11:15
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Procurement\PurchaseOrder;

use App\Actions\OrgAction;
use App\Actions\Procurement\WithAgentOrganisation;
use App\Actions\SupplyChain\PackingSpec\StorePackingSpec;
use App\Actions\Traits\Authorisations\WithProcurementAuthorisation;
use App\Enums\SupplyChain\PackingSpec\PackingSpecLevelEnum;
use App\Models\Procurement\PurchaseOrder;
use App\Enums\Procurement\PurchaseOrder\PurchaseOrderStateEnum;
use App\Models\Procurement\PurchaseOrderTransaction;
use App\Models\SupplyChain\PackingSpecLine;
use App\Models\SupplyChain\SupplierProductPackingSpec;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\ActionRequest;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sheet a packer works from, one page per product of a purchase order (or the one product asked for): the order
 * and the organisation the cartons go to, the carton barcode, how many units were ordered and how many cartons that
 * makes, the steps to follow and the materials of the packing spec, with the opened-carton photo. The carton size and
 * gross weight are printed when known, for the packer to cross out if the carton differs; the date, the packer's name
 * and any note are written in by hand. Units are always the quantity ordered, even when fewer have arrived.
 */
class PdfPackingInstructions extends OrgAction
{
    use WithProcurementAuthorisation;
    use WithAgentOrganisation;

    private const int PHOTO_EDGE = 320;

    private const array RELATIONS = [
        'organisation.country',
        'purchaseOrderTransactions.supplierProduct.supplier',
        'purchaseOrderTransactions.supplierProduct.stocks',
        'purchaseOrderTransactions.supplierProduct.tradeUnits',
        'purchaseOrderTransactions.supplierProduct.image',
        'purchaseOrderTransactions.supplierProduct.livePackingSpec.lines.packagingComponent',
        'purchaseOrderTransactions.historicSupplierProduct',
        'purchaseOrderTransactions.orgStock.tradeUnits.image',
    ];

    public function handle(PurchaseOrder $purchaseOrder, ?int $transactionId = null, ?User $printedBy = null): string
    {
        $timezone = $printedBy?->timezone_name ?? $purchaseOrder->organisation->timezone?->name ?? config('app.timezone');

        $purchaseOrder->loadMissing([
            ...self::RELATIONS,
            'purchaseOrderTransactions.supplierProduct.livePackingSpec.attachments' => fn ($query) => $query
                ->wherePivot('scope', StorePackingSpec::PHOTO_SCOPE)
                ->orderByDesc('model_has_attachments.id'),
        ]);

        $lines = PdfPurchaseOrder::make()->lines($purchaseOrder)
            ->filter(fn (PurchaseOrderTransaction $line) => $line->supplierProduct && (!$transactionId || $line->id === $transactionId))
            ->values();

        $images = PdfPurchaseOrder::make()->lineImages($lines);
        $photos = $this->openedCartonPhotos($lines);

        return PDF::loadView('procurement.templates.pdf.packing-instructions', [
            'purchaseOrder' => $purchaseOrder,
            'printedAt'     => now($timezone)->format('d M Y H:i').' ('.$timezone.')',
            'organisation'  => $purchaseOrder->organisation->name.($purchaseOrder->organisation->country ? ' ('.$purchaseOrder->organisation->country->name.')' : ''),
            'products'      => $lines->map(fn (PurchaseOrderTransaction $line) => $this->product($line, $images[$line->id] ?? null, $photos[$line->id] ?? null, $purchaseOrder->state === PurchaseOrderStateEnum::IN_PROCESS)),
        ], [], ['autoScriptToLang' => true, 'autoLangToFont' => true])->output();
    }

    /**
     * @return array<string, mixed>
     */
    private function product(PurchaseOrderTransaction $line, ?string $image, ?string $openedCarton, bool $isInProcess): array
    {
        $supplierProduct = $line->supplierProduct;
        $historic        = $line->historicSupplierProduct;
        $spec            = $supplierProduct->livePackingSpec;
        $units           = (float)$line->quantity_ordered;
        $unitsPerCarton  = max(1, (int)(($isInProcess ? null : $historic?->units_per_carton) ?? $supplierProduct->units_per_carton));
        $fullCartons     = (int)floor($units / $unitsPerCarton);
        $remainder       = $units - $fullCartons * $unitsPerCarton;
        $unitGrams       = (float)$supplierProduct->tradeUnits->sum(fn ($tradeUnit) => (float)$tradeUnit->gross_weight * (float)($tradeUnit->pivot->quantity ?? 1));
        $barcode         = $supplierProduct->stocks->pluck('carton_barcode')->filter()->first();

        return [
            'code'             => $line->orgStock?->code ?? $supplierProduct->code,
            'supplier_code'    => $line->orgStock && $line->orgStock->code !== $supplierProduct->code ? $supplierProduct->code : null,
            'name'             => $supplierProduct->name,
            'supplier'         => $supplierProduct->supplier?->name,
            'image'            => $image,
            'barcode'          => $barcode,
            'barcode_type'     => preg_match('/^\d{13}$/', (string)$barcode) ? 'EAN13' : 'C128B',
            'units'            => $units,
            'units_per_carton' => $unitsPerCarton,
            'full_cartons'     => $fullCartons,
            'remainder'        => $remainder,
            'cartons'          => (int)ceil($units / $unitsPerCarton),
            'unit_weight_kg'   => $unitGrams > 0 ? $unitGrams / 1000 : null,
            'carton_tare_kg'   => $spec ? $spec->packagingGramsPerCarton() / 1000 : null,
            'instructions'     => $supplierProduct->packing_instructions ?? [],
            'materials'        => $spec ? $this->materials($spec->lines) : [],
            'opened_carton'    => $openedCarton,
            'carton_size_cm'   => $this->cartonSize($supplierProduct->carton_dimensions, $spec),
            'gross_weight_kg'  => $this->grossWeightKg($supplierProduct->carton_weight, $spec),
        ];
    }

    /**
     * The carton size the packers can check against: the supplier product's, else the packing spec's.
     */
    private function cartonSize(?array $dimensions, ?SupplierProductPackingSpec $spec): ?string
    {
        $centimetres = fn (float $side) => rtrim(rtrim(number_format($side, 1, '.', ''), '0'), '.');

        if (Arr::get($dimensions, 'l') && Arr::get($dimensions, 'w') && Arr::get($dimensions, 'h')) {
            return implode(' × ', array_map(fn ($side) => $centimetres((float)$side), [$dimensions['l'], $dimensions['w'], $dimensions['h']]));
        }

        if ($spec?->carton_length_mm && $spec->carton_width_mm && $spec->carton_height_mm) {
            return implode(' × ', array_map(fn (int $side) => $centimetres($side / 10), [$spec->carton_length_mm, $spec->carton_width_mm, $spec->carton_height_mm]));
        }

        return null;
    }

    /**
     * The gross weight of a full carton: as goods in weighed it, else the supplier product's, else as the agent declared it.
     */
    private function grossWeightKg(?int $cartonWeightG, ?SupplierProductPackingSpec $spec): ?float
    {
        $grams = $spec?->measured_carton_gross_weight_g ?: $cartonWeightG ?: $spec?->declared_carton_gross_weight_g;

        return $grams ? $grams / 1000 : null;
    }

    /**
     * The opened-carton photos shrunk to the size they print at, so a phone photo does not swell the file: read through
     * imgproxy together like the product images, else shrunk here from the file on this server; none when neither works.
     *
     * @param  Collection<int, PurchaseOrderTransaction>  $lines
     * @return array<int, string>
     */
    private function openedCartonPhotos(Collection $lines): array
    {
        $media = $lines
            ->mapWithKeys(fn (PurchaseOrderTransaction $line) => [$line->id => $line->supplierProduct->livePackingSpec?->attachments->first()])
            ->filter();

        $photos = PdfPurchaseOrder::make()->mediaImages($media, self::PHOTO_EDGE);

        foreach ($media->except(array_keys($photos)) as $lineId => $photo) {
            $body = $this->shrinkLocalFile($photo->getPath());
            if ($body) {
                $photos[$lineId] = 'data:image/jpeg;base64,'.base64_encode($body);
            }
        }

        return $photos;
    }

    private function shrinkLocalFile(?string $path): ?string
    {
        if (!$path || !file_exists($path) || !($image = @imagecreatefromstring((string)file_get_contents($path)))) {
            return null;
        }

        $scale   = min(1, self::PHOTO_EDGE / max(imagesx($image), imagesy($image)));
        $resized = imagescale($image, max(1, (int)round(imagesx($image) * $scale)), max(1, (int)round(imagesy($image) * $scale)));

        ob_start();
        imagejpeg($resized ?: $image, null, 80);

        return ob_get_clean() ?: null;
    }

    /**
     * @param Collection<int, PackingSpecLine> $lines
     * @return list<string>
     */
    private function materials(Collection $lines): array
    {
        return $lines->map(fn (PackingSpecLine $line) => sprintf(
            '%s × %s %s',
            rtrim(rtrim(number_format((float)$line->quantity, 3, '.', ''), '0'), '.'),
            $line->description ?: $line->packagingComponent->dropdownLabel(),
            mb_strtolower(PackingSpecLevelEnum::labels()[$line->level->value])
        ))->values()->all();
    }

    public function filename(PurchaseOrder $purchaseOrder): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '-', 'packing-'.$purchaseOrder->reference).'.pdf';
    }

    public function asController(Organisation $organisation, PurchaseOrder $purchaseOrder, ActionRequest $request): Response
    {
        $this->initialisation($organisation, $request);
        $this->authorizeProcurementRecord($purchaseOrder);

        return response($this->handle($purchaseOrder, $request->integer('transaction') ?: null, $request->user()), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$this->filename($purchaseOrder).'"');
    }
}
