<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Packaging;

use App\Actions\OrgAction;
use App\Models\Billables\Packaging;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeletePackaging extends OrgAction
{
    public function handle(Packaging $packaging): Packaging
    {
        $packaging->delete();

        return $packaging;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function action(Packaging $packaging): Packaging
    {
        $this->asAction = true;

        $this->initialisationFromShop($packaging->shop, []);

        return $this->handle($packaging);
    }

    public function asController(Packaging $packaging, ActionRequest $request): Packaging
    {
        $this->initialisationFromShop($packaging->shop, $request);

        return $this->handle($packaging);
    }

    public function htmlResponse(Packaging $packaging): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Packaging :code deleted successfully.', ['code' => $packaging->code]),
        ]);
    }
}
