<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 May 2024 19:44:10 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithSupplyChainEditAuthorisation;
use App\Models\SupplyChain\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteSupplier extends OrgAction
{
    use WithSupplyChainEditAuthorisation;
    use AsController;
    use WithAttributes;

    public function handle(Supplier $supplier): Supplier
    {
        $supplier->delete();

        return $supplier;
    }

    public function action(Supplier $supplier): Supplier
    {
        $this->asAction = true;

        return $this->handle($supplier);
    }

    public function asController(Supplier $supplier, ActionRequest $request): Supplier
    {
        $request->validate();

        return $this->handle($supplier);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::route('grp.supply-chain.agents.index');
    }

}
