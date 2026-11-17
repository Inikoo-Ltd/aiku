<?php

/*
    * Author: Vika Aqordi
    * Created on: 2026-11-16 14:24
    * Github: https://github.com/aqordeon
    * Copyright: 2026
*/

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * The supplier portal pages answer to supply chain staff under supply chain, and to the agent organisation's procurement staff under the agent's supplier page.
 */
trait WithSupplierPortalAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $organisation = $request->route('organisation');
        if ($organisation instanceof Organisation) {
            $this->canEdit = $request->user()->authTo("procurement.$organisation->id.edit");

            return $request->user()->authTo("procurement.$organisation->id.view");
        }

        $this->canEdit = $request->user()->authTo('supply-chain.edit');

        return $request->user()->authTo(['supply-chain.view', 'compliance.view']);
    }
}
