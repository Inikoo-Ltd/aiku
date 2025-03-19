<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 19 Mar 2025 20:42:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Catalogue\Shop;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateDispatchedEmails
{
    use AsAction;
    use WithEnumStats;

    private Shop $shop;

    public string $jobQueue = 'low-priority';

    public function __construct(Shop $shop)
    {
        $this->shop = $shop;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->shop->id))->dontRelease()];
    }

    public function handle(Shop $shop): void
    {
        $stats = [
            'number_dispatched_emails' => $shop->dispatchedEmails()->count(),
        ];

        $shop->commsStats()->update($stats);
    }


}
