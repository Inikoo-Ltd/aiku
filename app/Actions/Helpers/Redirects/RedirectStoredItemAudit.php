<?php

/*
 * author Louis Perez
 * created on 21-05-2026-13h-02m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Models\Fulfilment\StoredItemAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectStoredItemAudit extends GrpAction
{
    public function handle(StoredItemAudit $storedItemAudit): ?RedirectResponse
    {
        $url = route('grp.org.fulfilments.show.crm.customers.show.stored-item-audits.show', [
            'organisation'          => $storedItemAudit->organisation->slug,
            'fulfilment'            => $storedItemAudit->fulfilment->slug,
            'fulfilmentCustomer'    => $storedItemAudit->fulfilmentCustomer->slug,
            'storedItemAudit'       => $storedItemAudit->slug
        ]);

        return Redirect::to($url);
    }

    public function asController(StoredItemAudit $storedItemAudit, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($storedItemAudit);
    }
    
}
