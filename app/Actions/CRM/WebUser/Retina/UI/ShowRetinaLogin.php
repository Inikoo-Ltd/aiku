<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsController;

class ShowRetinaLogin
{
    use AsController;


    public function handle(): Response
    {
        return Inertia::render('Auth/RetinaLogin', [
            "login_message" => request()->website->shop->type === ShopTypeEnum::DROPSHIPPING ?
                '<p>' . trans('Hey, as you notice we just got a brand new system for our website.') . '</p>
                <p class="py-3">' . trans('You can log in with your old username and password or use your google account to login (if the emails match)') . '.</p>
                <p>' . trans('If the password is not working, you can reset it from the forgot password page and all will be ok.') . '</p>' 
                : null,
            'google'    => [
                'client_id' => config('services.google.client_id')
            ]
        ]);
    }
}
