<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Enums\SysAdmin\Authorisation\RolesEnum;
use App\Models\Catalogue\Shop;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SeedStaffChatAudiences
{
    use AsAction;

    public string $commandSignature = 'chat:seed_staff_audiences {--d|dry-run} {--overwrite}';

    public string $commandDescription = 'Fill empty staff chat audience lists on shops and organisations from the users who currently hold the matching roles, so managers have a starting point to curate';

    public function handle(bool $dryRun = false, bool $overwrite = false, ?Command $command = null): int
    {
        $seeded = 0;

        foreach (Organisation::all() as $organisation) {
            setPermissionsTeamId($organisation->group_id);
            foreach (['crm', 'warehouse'] as $audience) {
                $seeded += $this->seed($organisation, $audience, $this->organisationRoleHolders($organisation, $audience), $dryRun, $overwrite, $command);
            }
        }

        foreach (Shop::all() as $shop) {
            setPermissionsTeamId($shop->group_id);
            $seeded += $this->seed($shop, 'crm', $this->shopRoleHolders($shop), $dryRun, $overwrite, $command);
        }

        $command?->info($dryRun ? "Would seed $seeded lists" : "Seeded $seeded lists");

        return $seeded;
    }

    private function seed(Organisation|Shop $scope, string $audience, array $userIds, bool $dryRun, bool $overwrite, ?Command $command): int
    {
        $current = Arr::get($scope->settings ?? [], "staff_chat.{$audience}_user_ids", []);

        if (!$overwrite && !empty($current)) {
            return 0;
        }

        if (empty($userIds)) {
            return 0;
        }

        $command?->line(class_basename($scope).' '.$scope->slug." $audience: ".count($userIds).' users');

        if ($dryRun) {
            return 1;
        }

        $settings = $scope->settings ?? [];
        Arr::set($settings, "staff_chat.{$audience}_user_ids", $userIds);
        $scope->update(['settings' => $settings]);

        return 1;
    }

    /**
     * @return array<int, int>
     */
    private function shopRoleHolders(Shop $shop): array
    {
        return $this->holdersOf([RolesEnum::CUSTOMER_SERVICE_CLERK, RolesEnum::CUSTOMER_SERVICE_SUPERVISOR], $shop);
    }

    /**
     * @return array<int, int>
     */
    private function organisationRoleHolders(Organisation $organisation, string $audience): array
    {
        if ($audience === 'crm') {
            $userIds = [];
            foreach ($organisation->shops as $shop) {
                $userIds = array_merge($userIds, $this->shopRoleHolders($shop));
            }

            return array_values(array_unique($userIds));
        }

        $userIds = [];
        foreach (Warehouse::where('organisation_id', $organisation->id)->get() as $warehouse) {
            $userIds = array_merge($userIds, $this->holdersOf(
                [RolesEnum::DISPATCH_CLERK, RolesEnum::DISPATCH_SUPERVISOR, RolesEnum::STOCK_CONTROLLER],
                $warehouse
            ));
        }

        return array_values(array_unique($userIds));
    }

    /**
     * @param  array<int, RolesEnum>  $roles
     * @return array<int, int>
     */
    private function holdersOf(array $roles, Shop|Warehouse $scope): array
    {
        $roleNames = array_map(fn (RolesEnum $role) => RolesEnum::getRoleName($role->value, $scope), $roles);

        return User::where('status', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', $roleNames))
            ->pluck('id')
            ->all();
    }

    public function asCommand(Command $command): int
    {
        $this->handle(
            dryRun: (bool) $command->option('dry-run'),
            overwrite: (bool) $command->option('overwrite'),
            command: $command
        );

        return 0;
    }
}
