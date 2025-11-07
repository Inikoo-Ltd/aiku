<?php

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\Traits\WithPassKeyAction;
use App\Actions\UI\WithInertia;
use App\Enums\UI\SysAdmin\ProfileTabsEnum;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProfileIndexPassKeys extends OrgAction
{
    use AsAction;

    use WithPassKeyAction;

    
    public function asController()
    {
        return $this->generatePasskeyOptions();
    }
}
