<?php

/*
 * Author Louis Perez
 * Created on 11-09-2026-14h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\TradeUnit;

use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLabelInfoLanguages
{
    use AsObject;

    public function handle(?array $labelInfo): array
    {
        $codes = array_filter((array) data_get($labelInfo, 'languages', []));

        $languages = $codes
            ? Language::whereIn('code', $codes)->orderBy('name')->get(['code', 'name', 'flag'])
                ->map(fn (Language $language) => $language->only(['code', 'name', 'flag']))
                ->values()
                ->all()
            : [];

        return [
            'show'  => $languages !== [],
            'value' => $languages,
        ];
    }
}
