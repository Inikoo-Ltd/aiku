<?php

namespace App\Actions\Web\Redirect;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Actions\Web\Website\HydrateRedirect;
use App\Models\Web\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRedirect extends OrgAction
{
    use WithWebEditAuthorisation;

    public function handle(Redirect $redirect): void
    {
        $redirect->delete();
        HydrateRedirect::run($redirect->redirectTo);
    }

    public function asController(Redirect $redirect, ActionRequest $request)
    {
        $this->initialisationFromShop($redirect->shop, $request);

        $this->handle($redirect);
    }
}
