<?php

/*
 * author Arya Permana - Kirin
 * created on 08-05-2025-16h-49m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Iris;

use App\Actions\IrisAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\Language;
use Illuminate\Support\Facades\Cookie;
use Lorisleiva\Actions\ActionRequest;

class UpdateIrisLocale extends IrisAction
{
    use WithActionUpdate;

    public function handle($modelData): \Illuminate\Http\RedirectResponse
    {

        $locale = $modelData['locale'];

        if (request()->user()) {
            $webUser = request()->user();
            $this->update($webUser, [
                'language_id' => Language::where('code', $locale)->first()->id,
            ]);
        }


        Cookie::queue('aiku_guest_locale', $locale, 60 * 24 * 120);
        app()->setLocale($locale);
        return redirect()->back()->withHeaders(['Refresh' => '0']);

    }


    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', 'exists:languages,code']
        ];
    }


    public function asController(ActionRequest $request): \Illuminate\Http\RedirectResponse
    {
        $this->initialisation($request);
        return $this->handle($this->validatedData);
    }


}
