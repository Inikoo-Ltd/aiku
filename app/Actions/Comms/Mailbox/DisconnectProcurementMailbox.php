<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailbox;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DisconnectProcurementMailbox extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo(['org-admin.'.$this->organisation->id, 'org-supervisor.'.$this->organisation->id.'.procurement']);
    }

    public function handle(Organisation $organisation): Organisation
    {
        $settings = $organisation->settings ?? [];
        Arr::forget($settings, 'procurement.gmail');

        return $this->update($organisation, ['settings' => $settings]);
    }

    public function asController(Organisation $organisation, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($organisation, $request);

        $this->handle($organisation);

        return Redirect::route('grp.org.procurement.settings.edit', [$organisation->slug])
            ->with('notification', [
                'status'      => 'success',
                'title'       => __('Gmail disconnected'),
                'description' => __('The procurement mailbox is no longer connected.'),
            ]);
    }
}
