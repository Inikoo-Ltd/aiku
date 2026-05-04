<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 11 Oct 2023 17:06:28 Malaysia Time, Office, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin;

trait WithLogRequest
{
    public function getDeviceIcon($deviceType): string
    {
        if ($deviceType == 'Desktop') {
            return 'far fa-desktop-alt';
        } elseif ($deviceType == 'Bot') {
            return 'fas fa-robot';
        }

        return 'fas fa-mobile-alt';
    }

    public function getBrowserIcon($browser): string
    {
        if (explode(' ', $browser)[0] == 'chrome') {
            return 'fab fa-chrome';
        } elseif ($browser == 'microsoft') {
            return 'fab fa-edge';
        }

        return 'fab fa-firefox-browser';
    }

    public function getPlatformIcon($platform): string
    {
        if ($platform == 'android') {
            return 'fab fa-android';
        } elseif ($platform == 'apple') {
            return 'fab fa-apple';
        }

        return 'fab fa-windows';
    }


}
