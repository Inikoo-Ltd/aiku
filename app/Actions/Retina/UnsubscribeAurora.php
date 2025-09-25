<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Sept 2025 15:34:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\RetinaAction;
use Lorisleiva\Actions\ActionRequest;

class UnsubscribeAurora extends RetinaAction
{
    public function handle(array $modelData): bool
    {

        return true;

    }

    public function rules(): array
    {
        return [
            's' => ['required', 'string'],
            'a' => ['required', 'string'],
        ];
    }


    public function asController(ActionRequest $request): bool
    {
        $this->initialisation($request);
        return $this->handle($this->validatedData);
    }

}
