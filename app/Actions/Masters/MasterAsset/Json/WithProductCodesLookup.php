<?php

/*
 * Author Louis Perez
 * Created on 21-09-2026-11h-25m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Masters\MasterAsset\Json;

use Illuminate\Support\Arr;

trait WithProductCodesLookup
{
    private const int MAX_CODES = 1000;

    public function rules(): array
    {
        return [
            'codes' => ['required', 'string', 'max:20000'],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function requestedCodes(): array
    {
        return collect(explode(',', (string) Arr::get($this->validatedData, 'codes')))
            ->map(fn (string $code) => mb_strtolower(trim($code)))
            ->filter()
            ->unique()
            ->take(self::MAX_CODES)
            ->values()
            ->all();
    }
}
