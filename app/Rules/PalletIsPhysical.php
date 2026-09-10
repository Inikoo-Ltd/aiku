<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Created: Thu, 20 Aug 2026 10:12:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class PalletIsPhysical implements ValidationRule
{
    public function __construct(protected string $column = 'id')
    {
    }

    public static function message(): string
    {
        return __('Virtual pallets can not be part of a pallet return or a pallet delivery, only their SKOs can be moved.');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $isVirtual = DB::table('pallets')
            ->where($this->column, $value)
            ->value('is_virtual');

        if ($isVirtual) {
            $fail(static::message());
        }
    }
}
