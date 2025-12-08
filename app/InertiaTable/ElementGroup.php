<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Jun 2023 13:59:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\InertiaTable;

use AllowDynamicProperties;
use Illuminate\Contracts\Support\Arrayable;

#[AllowDynamicProperties] class ElementGroup implements Arrayable
{
    public function __construct(
        public string $key,
        public string|array $label,
        public array $elements
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'elements' => $this->elements,
        ];
    }
}
