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
use App\Actions\Web\Webpage\CloseWebpage;
use App\Actions\Web\Webpage\OpenWebpage;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\Collection;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Validation\Validator;

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
