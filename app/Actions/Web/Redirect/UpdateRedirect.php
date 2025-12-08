<?php

/*
 * author Arya Permana - Kirin
 * created on 12-03-2025-11h-27m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\Web\Redirect;
use App\Rules\NoDomainString;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateRedirect extends OrgAction
{
    use WithActionUpdate;

    public function handle(Redirect $redirect, array $modelData): Redirect
    {
        if (Arr::exists($modelData, 'path')) {
            $path = Arr::get($modelData, 'path');
            $url = $redirect->website->domain.'/'.$path;
            data_set($modelData, 'url', $url);
        }
        $redirect = $this->update($redirect, $modelData);

        return $redirect;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(RedirectTypeEnum::class)],
            'path' => ['sometimes', 'string', new NoDomainString],
        ];
    }

    public function action(Redirect $redirect, array $modelData): Redirect
    {
        $this->asAction = true;
        $this->initialisationFromShop($redirect->shop, $modelData);

        return $this->handle($redirect, $this->validatedData);
    }

    public function asController(Redirect $redirect, ActionRequest $request)
    {
        $this->initialisationFromShop($redirect->shop, $request);

        return $this->handle($redirect, $this->validatedData);
    }
}
