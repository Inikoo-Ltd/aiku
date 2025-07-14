<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Tue, 11 Jul 2023 10:03:34 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\GoogleDrive\Traits;

use App\Models\SysAdmin\Organisation;

trait WithTokenPath
{
    public function getTokenPath(Organisation $organisation): string
    {
        return base_path('resources/private/google/'.$organisation->slug.'-token.json');
    }
}
