<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 08-05-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage\UI;

use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetBlueprintShowcase
{
    use AsObject;

    public function handle(Webpage $webpage): array
    {
        return [];
    }
}
