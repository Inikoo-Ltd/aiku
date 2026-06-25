<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Exports\Web;

use App\Enums\Web\Webpage\WebpageSubTypeEnum;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class WebpagesExport implements WithMultipleSheets
{
    public function __construct(protected Website $website)
    {
    }

    public function sheets(): array
    {
        $labels = WebpageSubTypeEnum::labels();
        $order  = array_flip(array_map(fn ($case) => $case->value, WebpageSubTypeEnum::cases()));

        $subTypes = Webpage::query()
            ->where('website_id', $this->website->id)
            ->distinct()
            ->pluck('sub_type')
            ->map(fn ($subType) => $subType instanceof \BackedEnum ? $subType->value : $subType)
            ->sortBy(fn ($subType) => $order[$subType] ?? PHP_INT_MAX)
            ->values();

        if ($subTypes->isEmpty()) {
            return [new WebpagesSubTypeSheet($this->website, null, 'Webpages')];
        }

        return $subTypes
            ->map(fn ($subType) => new WebpagesSubTypeSheet($this->website, $subType, $labels[$subType] ?? $subType))
            ->all();
    }
}
