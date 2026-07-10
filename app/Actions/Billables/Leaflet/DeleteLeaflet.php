<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 14:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Billables\Leaflet;

use App\Actions\OrgAction;
use App\Models\Billables\Leaflet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteLeaflet extends OrgAction
{
    public function handle(Leaflet $leaflet): Leaflet
    {
        $leaflet->delete();

        return $leaflet;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("products.{$this->shop->id}.edit");
    }

    public function action(Leaflet $leaflet): Leaflet
    {
        $this->asAction = true;

        $this->initialisationFromShop($leaflet->shop, []);

        return $this->handle($leaflet);
    }

    public function asController(Leaflet $leaflet, ActionRequest $request): Leaflet
    {
        $this->initialisationFromShop($leaflet->shop, $request);

        return $this->handle($leaflet);
    }

    public function htmlResponse(Leaflet $leaflet): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Leaflet :name deleted successfully.', ['name' => $leaflet->name]),
        ]);
    }
}
