<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 27 Oct 2025 23:42:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

/*
 * Author: Refactor by Junie (JetBrains AI)
 * Created: Mon, 27 Oct 2025
 */

namespace App\Actions\Helpers\TaxNumber\Concerns;

use App\Models\Helpers\TaxNumber;
use Illuminate\Console\Command;

trait AsTaxNumberCommand
{
    public function asCommand(Command $command): int
    {
        $taxNumber = TaxNumber::findOrFail($command->argument('id'));
        $taxNumber = $this->handle($taxNumber);

        $fields = [
            'id'                         => $taxNumber->id,
            'type'                       => $taxNumber->type->value,
            'country_code'               => $taxNumber->country_code,
            'number'                     => $taxNumber->number,
            'valid'                      => $taxNumber->valid ? 'true' : 'false',
            'status'                     => $taxNumber->status->value,
            'checked_at'                 => $taxNumber->checked_at,
            'invalid_checked_at'         => $taxNumber->invalid_checked_at,
            'external_service_failed_at' => $taxNumber->external_service_failed_at,
        ];

        foreach ($fields as $key => $value) {
            $command->line(str_pad($key, 28) . ': ' . ($value ?? ''));
        }

        if (!empty($taxNumber->data)) {
            $command->line('data:');
            $command->line(json_encode($taxNumber->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        return 0;
    }
}
