<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 01 Jun 2024 19:35:06 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Language\UI;

use App\Models\Helpers\Language;
use Lorisleiva\Actions\Concerns\AsObject;

class GetLanguagesOptions
{
    use AsObject;

    public function handle($languages): array
    {
        $selectOptions = [];
        /** @var Language $language */
        foreach ($languages as $language) {
            $selectOptions[$language->id] =
                [
                    'name'  => $language->name,
                    'id'    => $language->id,
                    'code'  => $language->code
                ];
        }

        return $selectOptions;
    }

    public function all(): array
    {
        return $this->handle(Language::all());
    }

    public function translated(): array
    {
        return $this->handle(Language::where('status', true)->get());
    }

}
