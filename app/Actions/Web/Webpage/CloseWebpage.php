<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Web\Webpage;

use App\Actions\OrgAction;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class CloseWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Webpage $webpage): Webpage
    {

        $webpage->update([
            'state' => WebpageStateEnum::CLOSED->value,
        ]);

        return $webpage;
    }

    public function action(Webpage $webpage): Webpage
    {
        return $this->handle($webpage);
    }
}
