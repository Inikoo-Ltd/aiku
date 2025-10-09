<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 17-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\OpenWebpage;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class EnableCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;


    public function handle(Collection $collection): void
    {
        $collection->update([
            'state' => CollectionStateEnum::ACTIVE->value,
        ]);

        OpenWebpage::run(
            $collection->webpage,
        );

    }

    public function asController(Collection $collection, ActionRequest $request): void
    {
        $this->initialisationFromShop($collection->shop, $request);
        $this->handle($collection, $this->validatedData);
    }
}
