<?php

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Actions\Traits\WithPassKeyAction;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProfileIndexPassKeys extends OrgAction
{
    use AsAction;

    // use WithPassKeyAction;


    // public function asController()
    // {
    //     return $this->generatePasskeyOptions();
    // }
}
