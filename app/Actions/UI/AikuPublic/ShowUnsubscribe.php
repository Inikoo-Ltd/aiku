<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 26 Sept 2025 09:20:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\UI\AikuPublic;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowUnsubscribe
{
    use AsController;

    public function asController(ActionRequest $request): Response
    {

        $data=[
            's'=>'s',
            'a'=>'a'
        ];

        return $this->htmlResponse($data);
    }


    public function htmlResponse($modelData): Response
    {
        return Inertia::render('Unsubscribe');
    }

}
