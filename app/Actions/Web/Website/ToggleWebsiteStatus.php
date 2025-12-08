<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 24 Jun 2023 13:12:05 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class ToggleWebsiteStatus extends OrgAction
{
    use AsController;
    use WithActionUpdate;

    public function handle(Website $website): Website
    {
        return $this->update($website, [
            'status' => ! $website->status,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("supervisor-products.{$this->shop->id}");
    }

    public function asController(Website $website, ActionRequest $request): Website
    {
        $this->initialisationFromShop($website->shop, $request);

        return $this->handle($website);
    }

    public function action(Website $website): Website
    {
        $this->asAction = true;
        $this->initialisationFromShop($website->shop, []);

        return $this->handle($website);
    }
}
