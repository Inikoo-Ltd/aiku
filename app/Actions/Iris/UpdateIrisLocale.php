<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris;

use App\Actions\Dropshipping\Portfolio\StorePortfolio;
use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\Portfolio;
use App\Models\Helpers\Language;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cookie;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Http\Request;    

class UpdateIrisLocale extends IrisAction
{
    use WithActionUpdate;

    public function handle(string $locale, Request $request, Closure $next)
    {
        if($request->user()) {
            $webUser = $request->user();
            $this->update($webUser, [
                'language_id' => Language::where('code', $locale)->first()->id,
            ]);
        }
        Cookie::queue('aiku_guest_locale', $locale, 60 * 24 * 120);
        app()->setLocale($locale);
        return $next($request);
    }
}
