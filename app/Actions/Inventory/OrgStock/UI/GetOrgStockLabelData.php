<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\UI;

use App\Models\Goods\TradeUnit;
use App\Models\Inventory\OrgStock;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Everything the unit label can print, with a null wherever the org stock has no value for it.
 *
 * The nulls are the point: the modal greys out the toggle for any field that comes back null, so
 * nobody ticks "with weight" on an org stock that has no weight and gets a label with a gap in it.
 */
class GetOrgStockLabelData
{
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(OrgStock $orgStock, string $level = 'unit'): array
    {
        $tradeUnits = $orgStock->tradeUnits;
        $tradeUnit  = $tradeUnits->first();
        $barcode    = collect(GetOrgStockBarcodes::run($orgStock))->firstWhere('level', $level);

        return [
            'code'            => $orgStock->code,
            'name'            => $tradeUnit?->name ?? $orgStock->name,
            'packed_in'       => (int) ($orgStock->packed_in ?? 1),
            'made_in'         => $this->getMadeIn($orgStock, $tradeUnits),
            'manufactured_by' => $this->getManufacturedBy($tradeUnits),
            'weight'          => $this->getWeight($barcode['weight'] ?? null),
            'signature'       => $this->getSignature($orgStock),
            'has_image'       => $tradeUnits->contains(fn (TradeUnit $tradeUnit) => (bool) $tradeUnit->image_id),
            'image_path'      => $this->getImagePath($tradeUnits),
            'barcode'         => [
                'number' => $barcode['number'] ?? null,
                'type'   => $this->getBarcodeType($barcode['number'] ?? ''),
            ],
        ];
    }

    /**
     * Aurora's wording, kept verbatim so a label reprinted out of Aiku reads the same as the one
     * already on the shelf: "Imported from India by Ancient Wisdom s.r.o."
     *
     * country_of_origin holds an ISO-3 code (GBR, CHN, IDN), so the printable name comes off the
     * joined country and the raw code is only a fallback for rows whose FK was never filled in.
     */
    private function getMadeIn(OrgStock $orgStock, $tradeUnits): ?string
    {
        $tradeUnit = $this->getSharedTradeUnit($tradeUnits, 'country_of_origin');

        if (!$tradeUnit) {
            return null;
        }

        $country = $tradeUnit->countryOrigin?->name ?? $tradeUnit->country_of_origin;

        if (blank($country)) {
            return null;
        }

        return __('Imported from :country by :organisation', [
            'country'      => $country,
            'organisation' => $orgStock->organisation->name,
        ]);
    }

    /**
     * gpsr_manufacturer is the only field in the system that states who actually made the goods.
     * The supplier deliberately is not used as a stand-in: a supplier is who the organisation buys
     * from, often a trading company or an agent, and printing one as the manufacturer would be a
     * false GPSR claim on a product label.
     */
    private function getManufacturedBy($tradeUnits): ?string
    {
        $tradeUnit    = $this->getSharedTradeUnit($tradeUnits, 'gpsr_manufacturer');
        $manufacturer = $this->collapseWhitespace($tradeUnit?->gpsr_manufacturer);

        if (blank($manufacturer)) {
            return null;
        }

        return __('Manufactured by :manufacturer', ['manufacturer' => $manufacturer]);
    }

    /**
     * An org stock can hold several trade units. They agree on these fields in practice, so the
     * shared value prints; where they disagree the line is left off rather than guessed, because a
     * label naming one country for a box holding goods from two would be wrong rather than partial.
     */
    public function getSharedTradeUnit($tradeUnits, string $field): ?TradeUnit
    {
        $withValue = $tradeUnits->filter(fn (TradeUnit $tradeUnit) => filled($tradeUnit->{$field}));

        if ($withValue->isEmpty() || $withValue->pluck($field)->unique()->count() > 1) {
            return null;
        }

        return $withValue->first();
    }

    /**
     * Weights are held in grams, and a label reads better in the unit that keeps it under four
     * digits, which is how the rest of the inventory screens show them.
     */
    private function getWeight(int|float|null $grams): ?string
    {
        if (blank($grams) || $grams <= 0) {
            return null;
        }

        return $grams >= 1000
            ? trimDecimalZeros(round($grams / 1000, 3)).' kg'
            : trimDecimalZeros(round($grams, 2)).' g';
    }

    /**
     * The signature is the tallest thing on the label, so its address is folded onto fewer lines:
     * the parts are paired up two to a line and the country keeps one of its own, which turns seven
     * lines into five without touching the order the locale's own formatter chose.
     */
    private function getSignature(OrgStock $orgStock): ?string
    {
        $organisation = $orgStock->organisation;
        $lines        = [$organisation->name];

        if ($organisation->address?->hasAnyLine()) {
            $lines = array_merge($lines, $this->getPairedAddressLines($organisation->address->formatted_address));
        }

        if (filled($organisation->phone)) {
            $lines[] = __('tel.:').$organisation->phone;
        }

        $lines = array_values(array_filter(array_map('trim', $lines), 'filled'));

        return $lines ? implode("\n", $lines) : null;
    }

    /**
     * @return array<int, string>
     */
    private function getPairedAddressLines(string $formattedAddress): array
    {
        $parts = array_values(array_filter(array_map('trim', explode("\n", $formattedAddress)), 'filled'));

        if (count($parts) < 2) {
            return $parts;
        }

        $country = array_pop($parts);
        $paired  = array_map(fn (array $pair) => implode(', ', $pair), array_chunk($parts, 2));
        $paired[] = $country;

        return $paired;
    }

    /**
     * The first trade unit that actually has a main image wins, rather than only ever asking the
     * first trade unit: an org stock holding several of them is usually a set where one part
     * carries the photograph and the rest do not.
     *
     * This is the path to draw from, which is separate from whether the org stock has a picture at
     * all. mPDF reads the file off disk, so one whose media is not on this machine yields null here
     * and the label simply prints without it, rather than failing mid render. The tick box stays
     * live off has_image, because a developer whose box has no media synced still needs to be able
     * to turn the image on.
     */
    private function getImagePath($tradeUnits): ?string
    {
        foreach ($tradeUnits as $tradeUnit) {
            if (!$tradeUnit->image_id) {
                continue;
            }

            $path = $tradeUnit->image?->getPath();

            if ($path && is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Only a valid EAN13 can be drawn as one, anything else falls back to CODE128, which is what
     * the barcode preview in the browser does too.
     *
     * The check digit is verified rather than just the length, because mPDF re-checksums whatever
     * it is handed: a 13 digit number with the wrong last digit would print bars that scan back as
     * a different number than the digits underneath them, which is worse than no barcode at all.
     */
    private function getBarcodeType(?string $number): string
    {
        return $this->isValidEan13((string) $number) ? 'EAN13' : 'C128B';
    }

    private function isValidEan13(string $number): bool
    {
        if (!preg_match('/^\d{13}$/', $number)) {
            return false;
        }

        $digits = str_split($number);
        $check  = (int) array_pop($digits);
        $sum    = 0;

        foreach ($digits as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 1 : 3);
        }

        return (10 - $sum % 10) % 10 === $check;
    }

    private function collapseWhitespace(?string $value): ?string
    {
        return $value === null ? null : trim(preg_replace('/\s+/', ' ', $value));
    }
}
