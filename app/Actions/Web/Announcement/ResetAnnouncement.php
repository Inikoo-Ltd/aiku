<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 18 Sep 2023 18:42:14 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Announcement;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Announcement;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class ResetAnnouncement extends OrgAction
{
    use WithActionUpdate;

    private Customer|Website $parent;

    private string $scope;

    private Customer $customer;

    public function handle(Announcement $announcement): void
    {
        $this->update($announcement, [
            'fields' => Arr::get($announcement->liveSnapshot->layout, 'fields'),
            'container_properties' => Arr::get($announcement->liveSnapshot->layout, 'container_properties'),
            'is_dirty' => false,
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return true;
    }

    public function asController(Shop $shop, Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->scope = 'website';
        $this->parent = $website;

        $this->initialisation($website->organisation, $request);

        $this->handle($announcement);
    }
}
