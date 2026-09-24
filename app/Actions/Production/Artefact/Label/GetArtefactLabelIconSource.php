<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Label;

use App\Enums\Goods\TradeUnit\TradeUnitPackagingMaterialEnum;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Where the picture of a label icon comes from: the browser gets a URL, mPDF a file path, and the
 * marks drawn here are the same data URI for both, so the preview and the sheet show one drawing.
 */
class GetArtefactLabelIconSource
{
    use AsObject;

    public const HAZARD_PICTOGRAMS = [
        'toxic'       => 'toxic-icon.png',
        'corrosive'   => 'corrosive-icon.png',
        'explosive'   => 'explosive.jpg',
        'flammable'   => 'flammable.png',
        'gas'         => 'gas.png',
        'environment' => 'hazard-env.png',
        'health'      => 'health-hazard.png',
        'oxidising'   => 'oxidising.png',
        'danger'      => 'serious-health-hazard.png',
    ];

    public const CE_MARKING = 'ce';

    public const UKCA_MARKING = 'ukca';

    public const WEEE_SYMBOL = 'weee';

    public function handle(string $icon, bool $isForPdf = false): ?string
    {
        if (isset(self::HAZARD_PICTOGRAMS[$icon])) {
            return $isForPdf
                ? public_path('hazardIcon/'.self::HAZARD_PICTOGRAMS[$icon])
                : '/hazardIcon/'.self::HAZARD_PICTOGRAMS[$icon];
        }

        $svg = match ($icon) {
            self::CE_MARKING   => $this->getCeMarking(),
            self::UKCA_MARKING => $this->getUkcaMarking(),
            self::WEEE_SYMBOL  => $this->getWeeeSymbol(),
            default            => $this->getPackagingMaterialMark($icon),
        };

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    /**
     * @param  array<int, string>  $icons
     * @return array<string, string>
     */
    public function forBrowser(array $icons): array
    {
        return array_filter(array_combine($icons, array_map(fn (string $icon) => $this->handle($icon), $icons)));
    }

    /**
     * Each letter is half a ring and the E adds its middle bar, as the mark is drawn in the regulation.
     */
    private function getCeMarking(): string
    {
        return $this->svg(
            '<path d="M42 14 A36 36 0 0 0 42 86" fill="none" stroke="#000" stroke-width="9"/>'
            .'<path d="M94 14 A36 36 0 0 0 94 86" fill="none" stroke="#000" stroke-width="9"/>'
            .'<line x1="58" y1="50" x2="88" y2="50" stroke="#000" stroke-width="9"/>'
        );
    }

    private function getUkcaMarking(): string
    {
        return $this->svg(
            '<text x="50" y="46" font-family="Arial" font-weight="bold" font-size="46" text-anchor="middle">UK</text>'
            .'<text x="50" y="94" font-family="Arial" font-weight="bold" font-size="46" text-anchor="middle">CA</text>'
        );
    }

    private function getWeeeSymbol(): string
    {
        return $this->svg(
            '<path d="M30 30 L70 30 L66 80 L34 80 Z" fill="none" stroke="#000" stroke-width="5"/>'
            .'<line x1="24" y1="24" x2="76" y2="24" stroke="#000" stroke-width="5"/>'
            .'<line x1="42" y1="18" x2="58" y2="18" stroke="#000" stroke-width="5"/>'
            .'<circle cx="38" cy="85" r="5" fill="#000"/>'
            .'<line x1="12" y1="10" x2="88" y2="88" stroke="#000" stroke-width="5"/>'
            .'<line x1="88" y1="10" x2="12" y2="88" stroke="#000" stroke-width="5"/>'
            .'<rect x="14" y="92" width="72" height="7" fill="#000"/>'
        );
    }

    /**
     * The code of the material inside a triangle of chasing arrows, with its abbreviation under it,
     * as the packaging identification codes are printed: "PET" and 1, "PAP" and 20.
     */
    private function getPackagingMaterialMark(string $icon): ?string
    {
        $material = TradeUnitPackagingMaterialEnum::tryFrom($icon);

        if (!$material) {
            return null;
        }

        [$abbreviation, $number] = explode(' ', TradeUnitPackagingMaterialEnum::labels()[$material->value]);

        return $this->svg(
            '<path d="M50 6 L90 72 L10 72 Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>'
            .'<path d="M74 45 L73 30 L60 38 Z" fill="#000"/>'
            .'<path d="M43 72 L57 64 L57 80 Z" fill="#000"/>'
            .'<path d="M34 33 L33 48 L20 40 Z" fill="#000"/>'
            .'<text x="50" y="60" font-family="Arial" font-weight="bold" font-size="26" text-anchor="middle">'.$number.'</text>'
            .'<text x="50" y="97" font-family="Arial" font-weight="bold" font-size="19" text-anchor="middle">'.$abbreviation.'</text>'
        );
    }

    private function svg(string $content): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="100" height="100">'.$content.'</svg>';
    }
}
