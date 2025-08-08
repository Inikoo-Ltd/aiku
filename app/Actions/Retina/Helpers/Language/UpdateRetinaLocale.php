<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Helpers\Language;

use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Cookie;

class UpdateRetinaLocale extends IrisAction
{
    use WithActionUpdate;

    public function handle(string $locale): void
    {
        if(request()->user()) {
            $webUser = request()->user();
            $this->update($webUser, [
                'language_id' => Language::where('code', $locale)->first()->id,
            ]);
        }
        Cookie::queue('aiku_guest_locale', $locale, 60 * 24 * 120);
        app()->setLocale($locale);
    }

    public function asController(string $locale): void
    {
        $this->handle($locale);
    }
}
