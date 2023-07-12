<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 23 Apr 2023 11:33:30 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Tenancy\Tenant\Hydrators;

use App\Enums\Auth\User\UserTypeEnum;
use App\Models\Auth\User;
use App\Models\Tenancy\Tenant;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class TenantHydrateUsers implements ShouldBeUnique
{
    use AsAction;
    use HasTenantHydrate;

    public function handle(Tenant $tenant): void
    {
        $numberUsers       = User::count();
        $numberActiveUsers = User::where('status', true)->count();

        $stats = [
            'number_users'                 => $numberUsers,
            'number_users_status_active'   => $numberActiveUsers,
            'number_users_status_inactive' => $numberUsers - $numberActiveUsers

        ];

        $statusCounts = User::selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->pluck('total', 'type')->all();
        foreach (UserTypeEnum::cases() as $userType) {
            $stats['number_users_type_'.$userType->snake()] = Arr::get($statusCounts, $userType->value, 0);
        }

        $tenant->stats->update($stats);
    }
}
