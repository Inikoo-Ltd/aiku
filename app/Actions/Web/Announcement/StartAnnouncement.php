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
use App\Models\CRM\Customer;
use App\Models\Web\Website;
use Lorisleiva\Actions\ActionRequest;

class StartAnnouncement extends OrgAction
{
    use WithActionUpdate;

    private Customer|Website $parent;
    private string $scope;
    private Customer $customer;


    public function handle(Announcement $announcement): void
    {
        $this->update($announcement, [
            'live_at' => now()
        ]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->get('customerUser')->hasPermissionTo("portfolio.banners.edit");
    }

    public function asController(Website $website, Announcement $announcement, ActionRequest $request): void
    {
        $this->scope    = 'website';
        $this->parent   = $website;

        $this->handle($announcement);
    }
}
