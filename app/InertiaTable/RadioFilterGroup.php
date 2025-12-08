<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 13 Jun 2023 13:59:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\InertiaTable;

use AllowDynamicProperties;
use Illuminate\Contracts\Support\Arrayable;

#[AllowDynamicProperties] class RadioFilterGroup implements Arrayable
{
    public function __construct(
        public string $key,
        public array $options,
        public string $value
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'options' => $this->options,
            'value' => $this->value,
        ];
    }
}
