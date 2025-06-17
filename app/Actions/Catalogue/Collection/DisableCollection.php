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
use App\Models\Catalogue\Collection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DisableCollection extends OrgAction
{
    use AsAction;
    use WithAttributes;

    private ?Collection $collection;

    public function handle(Collection $collection, bool $forceDelete = false): Collection
    {
        return $collection;
    }

    public function asController(Collection $collection, ActionRequest $request)
    {
        dd($request->all());
        // return $this->handle($collection, $forceDelete);
    }
}
