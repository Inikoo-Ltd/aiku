<?php

/*
 * Author: Vika Aqordi
 * Created on 06-11-2025-14h-47m
 * Github: https://github.com/aqordeon
 * Copyright: 2025
*/

namespace App\Actions\CRM\WebUser\Retina\Json;

use App\Actions\IrisAction;
use App\Actions\Traits\WithRetinaAuthRedirect;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetRedirectUrl extends IrisAction
{
    use AsController;
    use WithRetinaAuthRedirect;

    public function handle($modelData): array
    {
        $ref = Arr::get($modelData, 'ref');

        $retinaHome = Arr::get($modelData, 'registered', false)
            ? $this->getRetinaRegistrationRedirectUrl($this->website, $ref)
            : $this->getRetinaLoginRedirectUrl($this->website, $ref);

        return [
            'ref_page'     => $ref,
            'redirect_url' => $retinaHome,
            'redirected'   => $retinaHome !== '',
        ];
    }

    public function rules(): array
    {
        return [
            'ref'        => ['sometimes'],
            'registered' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($this->validatedData);
    }
}
