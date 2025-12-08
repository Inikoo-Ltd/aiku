<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Jun 2023 13:59:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\InertiaTable;

use AllowDynamicProperties;
use Illuminate\Contracts\Support\Arrayable;

#[AllowDynamicProperties] class PeriodFilter implements Arrayable
{
    public function __construct(
        public string $key,
        public string $label,
        public ?string $date
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->key,
            'label' => $this->label,
            'date' => $this->date,
        ];
    }
}
