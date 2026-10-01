<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Rules;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Holds an uploaded image to the aspect ratio of a page size.
 *
 * A pixel grid carries no physical units, so the mm check PdfPageSize does on a PDF's
 * MediaBox cannot apply here; matching the aspect ratio is the closest a photo or scan
 * can get to "A6", short of trusting DPI metadata that web-sourced images often lack or
 * misreport. Rotated images pass: a landscape A6 photo is still A6 proportioned.
 */
class ImagePageSize implements ValidationRule
{
    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png'];

    public function __construct(
        private float $widthMm,
        private float $heightMm,
        private string $label,
        private float $toleranceRatio = 0.05
    ) {
    }

    public function validate($attribute, $value, $fail): void
    {
        if (!$value instanceof UploadedFile || !in_array(strtolower($value->getClientOriginalExtension()), self::IMAGE_EXTENSIONS, true)) {
            return;
        }

        $size = @getimagesize($value->getPathname());

        if ($size === false) {
            $fail('The image :file could not be read. Export the leaflet as :label and upload it again.')->translate([
                'file'  => $value->getClientOriginalName(),
                'label' => $this->label,
            ]);

            return;
        }

        [$width, $height] = $size;

        if ($this->matchesRatio($width, $height)) {
            return;
        }

        $fail('Image is :width × :height px, which is not :label proportioned (:expected ratio). Crop it to :label and upload it again.')->translate([
            'width'    => $width,
            'height'   => $height,
            'label'    => $this->label,
            'expected' => $this->format($this->widthMm).' × '.$this->format($this->heightMm),
        ]);
    }

    private function matchesRatio(int $width, int $height): bool
    {
        $targetRatio = $this->widthMm / $this->heightMm;
        $imageRatio  = $width / max($height, 1);

        return $this->withinTolerance($imageRatio, $targetRatio)
            || $this->withinTolerance($imageRatio, 1 / $targetRatio);
    }

    private function withinTolerance(float $ratio, float $target): bool
    {
        return abs($ratio - $target) <= $target * $this->toleranceRatio;
    }

    private function format(float $mm): string
    {
        return rtrim(rtrim(number_format($mm, 1, '.', ''), '0'), '.');
    }
}
