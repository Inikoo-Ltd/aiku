<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

trait WithAuroraOrganisationsArgument
{
    protected function getOrganisations(Command $command): Collection
    {
        return Organisation::query()->where('type', OrganisationTypeEnum::SHOP->value)
            ->when($command->argument('organisations'), function ($query) use ($command) {
                $query->whereIn('slug', $command->argument('organisations'));
            })->orderBy('id')
            ->get();
    }
}
