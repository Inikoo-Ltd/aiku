<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use App\Services\Gmail\GmailClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ConnectProcurementMailbox extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'org-supervisor.'.$this->organisation->id.'.procurement']);
    }

    public function handle(Organisation $organisation, int $userId): RedirectResponse
    {
        $state = Crypt::encryptString(json_encode([
            'procurement_organisation_id' => $organisation->id,
            'user_id'                     => $userId,
            'return'                      => route('grp.org.procurement.settings.edit', [$organisation->slug]).'?mailbox_connected=1',
        ]));

        return Redirect::away(GmailClient::authorizationUrl($state, route('grp.gmail.callback')));
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $request->user()->id);
    }
}
