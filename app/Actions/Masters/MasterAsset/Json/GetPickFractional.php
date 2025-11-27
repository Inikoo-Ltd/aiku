<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAsset\Json;

use App\Actions\GrpAction;
use Lorisleiva\Actions\ActionRequest;

class GetPickFractional extends GrpAction
{
    public function asController(ActionRequest $request): array
    {
        $this->initialisation(group(), $request);

        return $this->handle();
    }

    public function handle(): array
    {
        return riseDivisor(divideWithRemainder(findSmallestFactors($this->validatedData['numerator'])), $this->validatedData['denominator']);
    }

    public function jsonResponse(array $pickFractional): array
    {
        return $pickFractional;
    }

    public function rules(): array
    {
        $rules = [
            'numerator'     => ['sometimes', 'numeric'],
            'denominator'       => ['sometimes', 'numeric'],
        ];

        return $rules;
    }

}
