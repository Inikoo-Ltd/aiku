<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 24 Sep 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Label;

use App\Enums\Goods\TradeUnit\TradeUnitBestBeforeEnum;
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

    public const TRIMAN = 'triman';

    public const INFO_TRI = 'info_tri';

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
            self::TRIMAN       => $this->getTriman(),
            self::INFO_TRI     => $this->getInfoTri(),
            default            => self::isPeriodAfterOpening($icon)
                ? $this->getPeriodAfterOpening($icon)
                : $this->getPackagingMaterialMark($icon),
        };

        return $svg ? 'data:image/svg+xml;base64,'.base64_encode($svg) : null;
    }

    public static function isPeriodAfterOpening(string $icon): bool
    {
        return str_starts_with($icon, 'pao_') && TradeUnitBestBeforeEnum::tryFrom($icon) !== null;
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
     * An open jar with the months the product keeps once opened written on it, "12M".
     */
    private function getPeriodAfterOpening(string $icon): string
    {
        $months = strtoupper(substr($icon, strlen('pao_')));

        return $this->svg(
            '<path d="M14 34 L80 12 L84 24 L18 46 Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>'
            .'<path d="M16 48 L84 48 L80 94 L20 94 Z" fill="none" stroke="#000" stroke-width="5" stroke-linejoin="round"/>'
            .'<text x="50" y="82" font-family="Arial" font-weight="bold" font-size="26" text-anchor="middle">'.$months.'</text>'
        );
    }

    /**
     * The French sorting logo, a figure inside three arrows turning around it, drawn as an
     * approximation of the official artwork.
     */
    private function getTriman(): string
    {
        return $this->svg(
            '<path d="M50 8 A42 42 0 0 1 90 42" fill="none" stroke="#000" stroke-width="5"/>'
            .'<path d="M84 64 A42 42 0 0 1 30 88" fill="none" stroke="#000" stroke-width="5"/>'
            .'<path d="M14 72 A42 42 0 0 1 28 16" fill="none" stroke="#000" stroke-width="5"/>'
            .'<path d="M96 38 L88 52 L80 38 Z" fill="#000"/>'
            .'<path d="M34 80 L22 90 L36 96 Z" fill="#000"/>'
            .'<path d="M22 8 L38 12 L28 24 Z" fill="#000"/>'
            .'<circle cx="50" cy="30" r="8" fill="#000"/>'
            .'<path d="M30 46 L70 46 M50 42 L50 64 M50 64 L38 82 M50 64 L62 82" fill="none" stroke="#000" stroke-width="7" stroke-linecap="round"/>'
        );
    }

    /**
     * The French sorting instruction printed next to the logo: the packaging goes in the sorting bin.
     */
    private function getInfoTri(): string
    {
        return $this->svg(
            '<text x="6" y="26" font-family="Arial" font-weight="bold" font-size="24">FR</text>'
            .'<path d="M64 14 L90 14 L87 42 L67 42 Z" fill="none" stroke="#000" stroke-width="4" stroke-linejoin="round"/>'
            .'<line x1="60" y1="10" x2="94" y2="10" stroke="#000" stroke-width="4"/>'
            .'<text x="50" y="62" font-family="Arial" font-weight="bold" font-size="10.5" text-anchor="middle">CET EMBALLAGE</text>'
            .'<text x="50" y="80" font-family="Arial" font-weight="bold" font-size="16" text-anchor="middle">SE TRIE</text>'
            .'<text x="50" y="95" font-family="Arial" font-size="7.5" text-anchor="middle">quefairedemesdechets.fr</text>'
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
