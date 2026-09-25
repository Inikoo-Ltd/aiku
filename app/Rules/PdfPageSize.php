<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;
use Throwable;

/**
 * Holds an uploaded PDF to one page size, in millimetres.
 *
 * Printing a leaflet that is not the size the envelope was cut for wastes the print run,
 * so the size is checked at upload rather than discovered at the packing bench. Rotated
 * pages pass: a landscape A6 is still an A6 sheet.
 */
class PdfPageSize implements ValidationRule
{
    private const POINTS_PER_MM = 72 / 25.4;

    public function __construct(
        private float $widthMm,
        private float $heightMm,
        private string $label,
        private float $toleranceMm = 2.0
    ) {
    }

    public function validate($attribute, $value, $fail): void
    {
        if (!$value instanceof UploadedFile) {
            return;
        }

        $sizes = $this->pageSizes($value->getPathname());

        if ($sizes === []) {
            $fail('The page size of :file could not be read. Export the leaflet as :label and upload it again.')->translate([
                'file'  => $value->getClientOriginalName(),
                'label' => $this->label,
            ]);

            return;
        }

        foreach ($sizes as $page => $size) {
            if ($this->matches($size[0], $size[1])) {
                continue;
            }

            $fail('Page :page is :width × :height mm. Leaflets must be :label (:expected mm).')->translate([
                'page'     => $page + 1,
                'width'    => $this->format($size[0]),
                'height'   => $this->format($size[1]),
                'label'    => $this->label,
                'expected' => $this->format($this->widthMm).' × '.$this->format($this->heightMm),
            ]);

            return;
        }
    }

    /** @return array<int, array{0: float, 1: float}> */
    private function pageSizes(string $path): array
    {
        $sizes = [];

        try {
            foreach ((new Parser())->parseFile($path)->getPages() as $page) {
                $box = $page->getDetails()['MediaBox'] ?? null;

                if (!is_array($box) || count($box) < 4 || !is_numeric($box[2]) || !is_numeric($box[3])) {
                    continue;
                }

                $sizes[] = [
                    abs((float) $box[2] - (float) $box[0]) / self::POINTS_PER_MM,
                    abs((float) $box[3] - (float) $box[1]) / self::POINTS_PER_MM,
                ];
            }
        } catch (Throwable) {
            return $this->pageSizesFromRawFile($path);
        }

        return $sizes !== [] ? $sizes : $this->pageSizesFromRawFile($path);
    }

    /**
     * A page inherits its MediaBox from the Pages node when it declares none of its own,
     * and a damaged cross reference table stops the parser before any of that is reached.
     * Reading the boxes straight out of the file still says what the sheet measures.
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function pageSizesFromRawFile(string $path): array
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        preg_match_all(
            '/MediaBox\s*\[\s*(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s+(-?[\d.]+)\s*\]/',
            $contents,
            $matches,
            PREG_SET_ORDER
        );

        return array_map(
            fn (array $match) => [
                abs((float) $match[3] - (float) $match[1]) / self::POINTS_PER_MM,
                abs((float) $match[4] - (float) $match[2]) / self::POINTS_PER_MM,
            ],
            $matches
        );
    }

    private function matches(float $width, float $height): bool
    {
        return $this->within($width, $this->widthMm) && $this->within($height, $this->heightMm)
            || $this->within($width, $this->heightMm) && $this->within($height, $this->widthMm);
    }

    private function within(float $value, float $target): bool
    {
        return abs($value - $target) <= $this->toleranceMm;
    }

    private function format(float $mm): string
    {
        return rtrim(rtrim(number_format($mm, 1, '.', ''), '0'), '.');
    }
}
