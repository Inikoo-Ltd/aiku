<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:36:40 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\TariffCode;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Helpers\TariffCode;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateTariffCode extends OrgAction
{
    use WithActionUpdate;

    public function handle(TariffCode $tariffCode, array $modelData): TariffCode
    {
        $translations = $tariffCode->name_i8n ?? [];
        foreach (['sk', 'es'] as $locale) {
            if (array_key_exists("name_$locale", $modelData)) {
                $translations[$locale] = Arr::pull($modelData, "name_$locale");
            }
        }
        $modelData['name_i8n'] = array_filter($translations) ?: null;

        return $this->update($tariffCode, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo('goods.edit');
    }

    public function rules(): array
    {
        return [
            'name'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'name_sk' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name_es' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(TariffCode $tariffCode, ActionRequest $request): TariffCode
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($tariffCode, $this->validatedData);
    }
}
