<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 22 Jul 2025 15:47:23 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class WebhookAction
{
    use AsAction;
    use WithAttributes;


    protected array $validatedData;

    public function initialisation(ActionRequest $request): static
    {

        $this->fillFromRequest($request);
        $this->validatedData = $this->validateAttributes();
        return $this;
    }

}
