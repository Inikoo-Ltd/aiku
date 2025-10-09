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
use App\Actions\Web\Website\Hydrators\WebsiteHydrateWebpages;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class OpenWebpage extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Webpage $webpage): Webpage
    {
        $webpage->redirectedTo->delete();

        $webpage->update([
            'state' => WebpageStateEnum::LIVE->value,
        ]);
        WebsiteHydrateWebpages::dispatch($webpage->website);

        return $webpage;
    }

    public function action(Webpage $webpage): Webpage
    {
        $this->asAction = true;
        $this->initialisationFromShop($webpage->shop, []);

        return $this->handle($webpage);
    }
}
