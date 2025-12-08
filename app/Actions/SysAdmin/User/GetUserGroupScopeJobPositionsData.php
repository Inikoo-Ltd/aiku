<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 Jan 2025 12:32:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetUserGroupScopeJobPositionsData
{
    use AsAction;

    public function handle(?User $user): array
    {

        return (array) $user?->pseudoJobPositions()->where('scope', 'group')->get()->map(function ($jobPosition) {
            return [$jobPosition->slug];
        })->reduce(function ($carry, $item) {
            return array_merge_recursive($carry, $item);
        }, []);
    }
}
