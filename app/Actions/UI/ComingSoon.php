<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 06 Jun 2023 15:11:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\UI;

use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\Concerns\AsAction;

class ComingSoon
{
    use AsAction;
    use WithInertia;

    public function asController(): void
    {
        $this->validateAttributes();
    }

    public function htmlResponse(): Response
    {

        return Inertia::render(
            'ComingSoon',
            [
                'breadcrumbs' => [],
                'title' => __('coming soon'),

            ]
        );
    }
}
