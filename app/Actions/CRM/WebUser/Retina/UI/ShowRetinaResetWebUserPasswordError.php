<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Actions\RetinaAction;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ShowRetinaResetWebUserPasswordError extends RetinaAction
{
    use AsController;

    public function handle(ActionRequest $request): Response
    {

        if (is_array($request->input('errors'))) {
            $parsedErrors['errors'] = $request->input('errors');
        } else {
            parse_str($request->input('errors'), $parsedErrors);
        }



        $error = '';

        foreach (Arr::get($parsedErrors, 'errors.token', []) as $tokenError) {
            $error .= ' '.$tokenError;
        }
        $error = trim($error);

        if ($error == '') {
            foreach (Arr::get($parsedErrors, 'errors.id', []) as $tokenError) {
                $error .= ' '.$tokenError;
            }
            $error = trim($error);
        }

        if ($error == '') {
            $error = __('Unknown error');
        }


        return Inertia::render('Auth/ResetUserPasswordError', [
            'title' => __('Error resetting password'),
            'error' => $error
        ]);
    }




}
