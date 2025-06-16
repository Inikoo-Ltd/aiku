<?php

/*
 * author Arya Permana - Kirin
 * created on 16-06-2025-09h-31m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Web\ModelHasContent;

use App\Actions\OrgAction;
use App\Models\Web\ModelHasContent;
use Lorisleiva\Actions\ActionRequest;

class DeleteModelHasContent extends OrgAction
{
    public function handle(ModelHasContent $modelHasContent): bool
    {
        $modelHasContent->delete();

        return true;
    }

    public function asController(ModelHasContent $modelHasContent, ActionRequest $request)
    {
        $this->initialisationFromShop($modelHasContent->model->shop, $request);

        $this->handle($modelHasContent);
    }
}
