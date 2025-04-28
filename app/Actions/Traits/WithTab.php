<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 11 Jan 2024 00:57:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Illuminate\Support\Arr;

trait WithTab
{
    protected ?string $tab                = null;

    public function withTab(array $tabs): static
    {
        $tab =  $this->get('tab', Arr::first($tabs));
        if (!in_array($tab, $tabs)) {
            abort(404);
        }
        $this->tab = $tab;

        return $this;
    }
}
